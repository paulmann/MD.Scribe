<?php

/**
 * MD.Viewer 3.0 — admin-side content manager.
 *
 * Create / read / write / rename / move / delete documents and folders inside
 * the browse root, plus image uploads for the editor. Deletes are *soft*:
 * files move to storage/backups/trash/ and can be restored from the admin.
 * Writes support optimistic concurrency (compare content hash before save).
 */

declare(strict_types=1);

namespace MdViewer\Files;

use MdViewer\Core\AuditLog;
use MdViewer\Core\Config;
use MdViewer\Core\Logger;

final class FileManager
{
    /** Image extensions accepted by the editor's asset upload. */
    public const array IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];

    public function __construct(
        private readonly Config $config,
        private readonly MarkdownRepository $repo,
        private readonly Logger $logger = new Logger(),
        private readonly ?AuditLog $audit = null,
    ) {
    }

    private function validator(): PathValidator
    {
        return $this->repo->validator();
    }

    private function root(): string
    {
        return $this->repo->browseDir();
    }

    // ── Tree ───────────────────────────────────────────────────────────────

    /**
     * Full content tree for the admin file manager UI.
     *
     * @return array{directories: list<array{path: string, name: string, depth: int}>, files: list<array{path: string, name: string, dir: string, size: int, modified: int}>}
     */
    public function tree(): array
    {
        $root = $this->root();
        $directories = [];
        $files = [];

        $stack = [[$root, '', 0]];
        $maxDepth = max(1, $this->config->int('MAX_SCAN_DEPTH'));
        while ($stack !== []) {
            [$dir, $rel, $depth] = array_pop($stack);
            $entries = @scandir($dir);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $dir . DIRECTORY_SEPARATOR . $entry;
                $relPath = $rel === '' ? $entry : $rel . '/' . $entry;
                if (is_dir($full)) {
                    if (str_starts_with($entry, '.') || in_array($entry, ['node_modules', 'vendor', 'md.backup', 'storage'], true)) {
                        continue;
                    }
                    $directories[] = ['path' => $relPath, 'name' => $entry, 'depth' => $depth];
                    if ($depth < $maxDepth) {
                        $stack[] = [$full, $relPath, $depth + 1];
                    }
                    continue;
                }
                $lower = strtolower($entry);
                if (!str_ends_with($lower, '.md') || str_starts_with($entry, '.')) {
                    if (!in_array(pathinfo($entry, PATHINFO_EXTENSION), self::IMAGE_EXTENSIONS, true)) {
                        continue;
                    }
                }
                $stat = @stat($full);
                $files[] = [
                    'path' => $relPath,
                    'name' => $entry,
                    'dir' => $rel === '' ? '' : $rel,
                    'size' => is_array($stat) ? (int) $stat['size'] : 0,
                    'modified' => is_array($stat) ? (int) $stat['mtime'] : 0,
                    'type' => str_ends_with($lower, '.md') ? 'markdown' : 'image',
                ];
            }
        }

        usort($directories, static fn ($a, $b) => strnatcasecmp($a['path'], $b['path']));
        usort($files, static fn ($a, $b) => strnatcasecmp($a['path'], $b['path']));

        return ['directories' => $directories, 'files' => $files];
    }

    // ── Documents ──────────────────────────────────────────────────────────

    /** @return array{ok: bool, error?: string, content?: string, hash?: string, modified?: int} */
    public function read(string $relative): array
    {
        $abs = $this->validator()->validateFile($relative, $this->root());
        if ($abs === null) {
            return ['ok' => false, 'error' => 'File not found or path refused.'];
        }
        $content = MarkdownRepository::read($abs);
        if ($content === null) {
            return ['ok' => false, 'error' => 'File is not readable.'];
        }

        return ['ok' => true, 'content' => $content, 'hash' => hash('sha256', $content), 'modified' => (int) @filemtime($abs)];
    }

    /**
     * Create or overwrite a document. Parent folders are created on demand
     * (validated segment-by-segment first). When $expectedHash is given and
     * differs from the current content, refuse with a conflict (the editor
     * was open while somebody else saved).
     *
     * @return array{ok: bool, error?: string, status?: int, path?: string, hash?: string}
     */
    public function write(string $relative, string $content, string $actor = 'admin', ?string $expectedHash = null): array
    {
        if ($this->ensureParentDirs($relative) === false) {
            return ['ok' => false, 'status' => 400, 'error' => 'Invalid path.'];
        }
        $abs = $this->validator()->validateNewPath($relative, $this->root());
        if ($abs === null) {
            return ['ok' => false, 'status' => 400, 'error' => 'Invalid path.'];
        }
        $isNew = !file_exists($abs);
        if (!$isNew && $expectedHash !== null) {
            $current = (string) @file_get_contents($abs);
            if (hash('sha256', $current) !== $expectedHash) {
                return ['ok' => false, 'status' => 409, 'error' => 'The file changed on disk since it was opened. Reload to merge.'];
            }
        }
        if (strlen($content) > 8 * 1024 * 1024) {
            return ['ok' => false, 'status' => 413, 'error' => 'Document exceeds the 8 MB editor cap.'];
        }

        if ($this->atomicWrite($abs, $content) === false) {
            $this->logger->error('file write failed', ['path' => $relative]);

            return ['ok' => false, 'status' => 500, 'error' => 'Write failed (permissions?).'];
        }

        $this->audit?->record($isNew ? 'content.create' : 'content.update', $actor, ['path' => $relative, 'bytes' => strlen($content)]);

        return ['ok' => true, 'path' => $relative, 'hash' => hash('sha256', $content)];
    }

    /** @return array{ok: bool, error?: string, status?: int, path?: string} */
    public function rename(string $from, string $to, string $actor = 'admin'): array
    {
        $absFrom = $this->validator()->validateFile($from, $this->root());
        $this->ensureParentDirs($to);
        $absTo = $this->validator()->validateNewPath($to, $this->root());
        if ($absFrom === null) {
            return ['ok' => false, 'status' => 404, 'error' => 'Source not found or refused.'];
        }
        if ($absTo === null) {
            return ['ok' => false, 'status' => 400, 'error' => 'Target path refused.'];
        }
        if (file_exists($absTo)) {
            return ['ok' => false, 'status' => 409, 'error' => 'Target already exists.'];
        }
        if (!@rename($absFrom, $absTo)) {
            return ['ok' => false, 'status' => 500, 'error' => 'Rename failed.'];
        }
        $this->audit?->record('content.rename', $actor, ['from' => $from, 'to' => $to]);

        return ['ok' => true, 'path' => $this->validator()->relativize($absTo, $this->root()) ?? $to];
    }

    /** @return array{ok: bool, error?: string, status?: int, path?: string} */
    public function move(string $from, string $toDir, string $actor = 'admin'): array
    {
        $target = rtrim($toDir, '/') === '' ? basename($from) : trim($toDir, '/') . '/' . basename($from);

        return $this->rename($from, $target, $actor);
    }

    /** Soft delete → storage/backups/trash/<timestamp>-<flattened-name>. */
    public function delete(string $relative, string $actor = 'admin'): array
    {
        $abs = $this->validator()->validateFile($relative, $this->root());
        if ($abs === null) {
            return ['ok' => false, 'status' => 404, 'error' => 'File not found or refused.'];
        }
        $trash = \MdViewer\Core\Paths::backups() . '/trash';
        if (!is_dir($trash) && !@mkdir($trash, 0o755, true) && !is_dir($trash)) {
            return ['ok' => false, 'status' => 500, 'error' => 'Trash directory unavailable.'];
        }
        $safeName = str_replace('/', '__', $relative);
        $dest = $trash . '/' . date('Ymd-His') . '-' . $safeName;
        if (!@rename($abs, $dest)) {
            return ['ok' => false, 'status' => 500, 'error' => 'Delete failed.'];
        }
        $this->audit?->record('content.delete', $actor, ['path' => $relative, 'trash' => basename($dest)]);

        return ['ok' => true, 'path' => $relative];
    }

    /** Restore a file from trash. */
    public function restoreFromTrash(string $trashName, string $actor = 'admin'): array
    {
        $trash = \MdViewer\Core\Paths::backups() . '/trash';
        $trashName = basename($trashName);
        $src = $trash . '/' . $trashName;
        if (!is_file($src)) {
            return ['ok' => false, 'status' => 404, 'error' => 'Trash entry not found.'];
        }
        // "<timestamp>-<flattened path>" → original relative path
        $flattened = preg_replace('/^\d{8}-\d{6}-/', '', $trashName) ?? '';
        $relative = str_replace('__', '/', $flattened);
        $abs = $this->validator()->validateNewPath($relative, $this->root());
        if ($abs === null || file_exists($abs)) {
            return ['ok' => false, 'status' => 409, 'error' => 'Cannot restore: invalid path or file already exists.'];
        }
        if (!@rename($src, $abs)) {
            return ['ok' => false, 'status' => 500, 'error' => 'Restore failed.'];
        }
        $this->audit?->record('content.restore', $actor, ['path' => $relative]);

        return ['ok' => true, 'path' => $relative];
    }

    /** @return list<array{name: string, size: int, modified: int}> */
    public function trashList(): array
    {
        $trash = \MdViewer\Core\Paths::backups() . '/trash';
        if (!is_dir($trash)) {
            return [];
        }
        $out = [];
        foreach ((array) glob($trash . '/*') as $file) {
            if (is_string($file) && is_file($file)) {
                $out[] = ['name' => basename($file), 'size' => (int) @filesize($file), 'modified' => (int) @filemtime($file)];
            }
        }
        usort($out, static fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $out;
    }

    // ── Folders ────────────────────────────────────────────────────────────

    public function createFolder(string $relative, string $actor = 'admin'): array
    {
        $segments = $this->validator()->dirSegments($relative);
        if ($segments === null || $segments === []) {
            return ['ok' => false, 'status' => 400, 'error' => 'Invalid folder path.'];
        }
        $target = $this->root() . '/' . implode('/', $segments);
        $real = realpath($target);
        if ($real !== false && is_dir($real)) {
            return ['ok' => false, 'status' => 409, 'error' => 'Folder already exists.'];
        }
        if (!@mkdir($target, 0o755, true) && !is_dir($target)) {
            return ['ok' => false, 'status' => 500, 'error' => 'Cannot create folder.'];
        }
        $this->audit?->record('content.mkdir', $actor, ['path' => implode('/', $segments)]);

        return ['ok' => true, 'path' => implode('/', $segments)];
    }

    public function deleteFolder(string $relative, string $actor = 'admin'): array
    {
        $dir = $this->validator()->validateDir($relative, $this->root());
        if ($dir === null || $dir === realpath($this->root())) {
            return ['ok' => false, 'status' => 400, 'error' => 'Folder not found, refused, or is the browse root.'];
        }
        $entries = @scandir($dir) ?: [];
        $entries = array_values(array_diff($entries, ['.', '..']));
        if ($entries !== []) {
            return ['ok' => false, 'status' => 409, 'error' => 'Folder is not empty (' . count($entries) . ' entries).'];
        }
        if (!@rmdir($dir)) {
            return ['ok' => false, 'status' => 500, 'error' => 'Cannot remove folder.'];
        }
        $this->audit?->record('content.rmdir', $actor, ['path' => $relative]);

        return ['ok' => true, 'path' => $relative];
    }

    // ── Image assets ───────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $file single $_FILES entry
     * @return array{ok: bool, error?: string, status?: int, url_path?: string}
     */
    public function uploadImage(array $file, string $dirRel, string $actor = 'admin'): array
    {
        if ((int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'status' => 400, 'error' => 'Upload failed.'];
        }
        $maxBytes = max(1, $this->config->int('MAX_UPLOAD_MB')) * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'status' => 413, 'error' => 'Image exceeds the size cap.'];
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            return ['ok' => false, 'status' => 415, 'error' => 'Only ' . implode(', ', self::IMAGE_EXTENSIONS) . ' images are accepted (SVG is refused by design).'];
        }
        // Content sniffing: refuse anything that is not really an image.
        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF, IMAGETYPE_BMP], true)) {
            return ['ok' => false, 'status' => 415, 'error' => 'File is not a valid image.'];
        }

        $dir = $this->validator()->validateDir($dirRel, $this->root());
        if ($dir === null) {
            return ['ok' => false, 'status' => 400, 'error' => 'Invalid target directory.'];
        }
        $name = preg_replace('/[^A-Za-z0-9._\- \p{L}\p{Pd}]/u', '_', pathinfo((string) $file['name'], PATHINFO_FILENAME)) ?? 'image';
        $name = trim(substr($name, 0, 80)) . '.' . $ext;
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        $i = 1;
        while (file_exists($target)) {
            $target = $dir . DIRECTORY_SEPARATOR . pathinfo($name, PATHINFO_FILENAME) . '-' . $i++ . '.' . $ext;
        }
        if (!is_uploaded_file((string) $file['tmp_name']) || !@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return ['ok' => false, 'status' => 500, 'error' => 'Cannot store the image.'];
        }
        @chmod($target, 0o644);

        $relative = $this->validator()->relativize($target, $this->root()) ?? $name;
        $this->audit?->record('content.upload_image', $actor, ['path' => $relative]);

        return ['ok' => true, 'url_path' => $relative];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Create missing parent directories for a validated relative path.
     * Returns false when any segment fails validation.
     */
    private function ensureParentDirs(string $relative): bool
    {
        $parent = dirname(str_replace('\\', '/', trim($relative, '/')));
        if ($parent === '.' || $parent === '' || $parent === '/') {
            return true;
        }
        $segments = $this->validator()->dirSegments($parent);
        if ($segments === null || $segments === []) {
            return $segments === [];
        }
        $current = $this->root();
        foreach ($segments as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (!is_dir($current) && !@mkdir($current, 0o755) && !is_dir($current)) {
                return false;
            }
        }

        return true;
    }

    private function atomicWrite(string $abs, string $content): bool
    {
        $dir = dirname($abs);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            return false;
        }
        $tmp = $abs . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        @chmod($tmp, 0o644);
        if (!@rename($tmp, $abs)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }
}
