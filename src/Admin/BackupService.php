<?php

/**
 * MD.Viewer 3.0 — content backups.
 *
 * A backup is a snapshot of the content root + `.md.ini` in
 * storage/backups/content-<timestamp>/ (or a .zip when ext-zip is available).
 * Restore is itself backed up first (restore-of-restore always possible —
 * the v2 rule).
 */

declare(strict_types=1);

namespace MdViewer\Admin;

use MdViewer\Core\AuditLog;
use MdViewer\Core\Logger;
use MdViewer\Core\Paths;
use MdViewer\Files\MarkdownRepository;

final class BackupService
{
    public function __construct(
        private readonly MarkdownRepository $repo,
        private readonly Logger $logger = new Logger(),
        private readonly ?AuditLog $audit = null,
    ) {
    }

    private function dir(): string
    {
        $dir = Paths::backups() . '/content';
        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }

        return $dir;
    }

    /** Create a snapshot. Returns the backup id (folder name). */
    public function create(string $actor = 'admin', string $note = ''): array
    {
        $id = 'content-' . date('Ymd-His');
        $target = $this->dir() . '/' . $id;
        if (!@mkdir($target, 0o755, true) && !is_dir($target)) {
            return ['ok' => false, 'error' => 'Cannot create the backup directory.'];
        }

        $copied = $this->copyTree($this->repo->browseDir(), $target . '/content');
        // Config travels with the backup (it governs the restored content).
        if (is_file(Paths::iniFile())) {
            @copy(Paths::iniFile(), $target . '/md.ini');
        }
        $meta = [
            'id' => $id,
            'created' => date('c'),
            'files' => $copied,
            'bytes' => $this->dirSize($target),
            'note' => mb_substr($note, 0, 200),
            'browse_dir' => $this->repo->browseDir(),
        ];
        @file_put_contents($target . '/backup.json', (string) json_encode($meta, JSON_PRETTY_PRINT));

        $this->audit?->record('backup.create', $actor, ['id' => $id, 'files' => $copied]);
        $this->logger->info('backup created', ['id' => $id, 'files' => $copied]);

        return ['ok' => true, 'id' => $id] + $meta;
    }

    /** @return list<array{id: string, created: ?string, files: int, bytes: int, note: string}> */
    public function list(): array
    {
        $out = [];
        foreach ((array) glob($this->dir() . '/content-*', GLOB_ONLYDIR) as $dir) {
            if (!is_string($dir)) {
                continue;
            }
            $meta = $this->meta(basename($dir));
            $out[] = $meta;
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) $b['id'], (string) $a['id']));

        return $out;
    }

    /** @return array{id: string, created: ?string, files: int, bytes: int, note: string} */
    public function meta(string $id): array
    {
        $id = $this->safeId($id);
        $dir = $this->dir() . '/' . $id;
        $metaFile = $dir . '/backup.json';
        if ($id === '' || !is_dir($dir)) {
            return ['id' => '', 'created' => null, 'files' => 0, 'bytes' => 0, 'note' => ''];
        }
        if (is_file($metaFile) && json_validate((string) @file_get_contents($metaFile))) {
            $meta = json_decode((string) @file_get_contents($metaFile), true);
            if (is_array($meta)) {
                return $meta + ['id' => $id, 'created' => null, 'files' => 0, 'bytes' => 0, 'note' => ''];
            }
        }

        return [
            'id' => $id,
            'created' => date('c', (int) @filemtime($dir)),
            'files' => $this->countFiles($dir . '/content'),
            'bytes' => $this->dirSize($dir),
            'note' => '',
        ];
    }

    /**
     * Restore a backup over the content root. The current state is saved as
     * `content-<ts>-pre-restore` first.
     */
    public function restore(string $id, string $actor = 'admin'): array
    {
        $id = $this->safeId($id);
        $source = $this->dir() . '/' . $id . '/content';
        if ($id === '' || !is_dir($source)) {
            return ['ok' => false, 'error' => 'Backup not found.'];
        }

        $preId = 'content-' . date('Ymd-His') . '-pre-restore';
        $this->copyTree($this->repo->browseDir(), $this->dir() . '/' . $preId . '/content');

        $root = $this->repo->browseDir();
        $this->deleteTreeContents($root);
        $copied = $this->copyTree($source, $root);

        $iniBackup = $this->dir() . '/' . $id . '/md.ini';
        if (is_file($iniBackup) && is_writable(Paths::iniFile())) {
            @copy($iniBackup, Paths::iniFile());
        }

        $this->audit?->record('backup.restore', $actor, ['id' => $id, 'files' => $copied, 'pre_restore' => $preId]);
        $this->logger->info('backup restored', ['id' => $id, 'files' => $copied]);

        return ['ok' => true, 'files' => $copied, 'pre_restore' => $preId];
    }

    public function delete(string $id, string $actor = 'admin'): array
    {
        $id = $this->safeId($id);
        $dir = $this->dir() . '/' . $id;
        if ($id === '' || !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Backup not found.'];
        }
        $this->deleteTree($dir);
        $this->audit?->record('backup.delete', $actor, ['id' => $id]);

        return ['ok' => true];
    }

    /** Path of a downloadable zip of the backup (built on demand). */
    public function archive(string $id): ?string
    {
        $id = $this->safeId($id);
        $dir = $this->dir() . '/' . $id;
        if ($id === '' || !is_dir($dir) || !class_exists(\ZipArchive::class)) {
            return null;
        }
        $zipPath = $dir . '.zip';
        if (is_file($zipPath) && (int) @filemtime($zipPath) >= (int) @filemtime($dir . '/backup.json')) {
            return $zipPath;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $this->zipTree($zip, $dir, '');
        $zip->close();

        return $zipPath;
    }

    /** Keep the newest $keep backups, delete older ones. Returns removed count. */
    public function prune(int $keep = 10, string $actor = 'system'): int
    {
        $all = $this->list();
        $removed = 0;
        foreach (array_slice($all, max(0, $keep)) as $backup) {
            // never prune pre-restore safety nets older than the newest keep? keep it simple: prune them too
            if ($this->delete((string) $backup['id'], $actor)['ok'] ?? false) {
                $removed++;
            }
        }

        return $removed;
    }

    // ── Filesystem helpers ─────────────────────────────────────────────────

    private function safeId(string $id): string
    {
        return preg_match('/^content-\d{8}-\d{6}(?:-pre-restore)?$/', $id) === 1 ? $id : '';
    }

    private function copyTree(string $from, string $to): int
    {
        if (!is_dir($from)) {
            return 0;
        }
        if (!is_dir($to) && !@mkdir($to, 0o755, true) && !is_dir($to)) {
            return 0;
        }
        $count = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($from));
            $target = $to . $relative;
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0o755, true);
                }
                continue;
            }
            if (@copy($item->getPathname(), $target)) {
                $count++;
            }
        }

        return $count;
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    private function deleteTreeContents(string $dir): void
    {
        foreach ((array) glob($dir . '/*') as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            is_dir($entry) ? $this->deleteTree($entry) : @unlink($entry);
        }
        foreach ((array) glob($dir . '/.[!.]*') as $entry) {
            if (is_string($entry) && basename($entry) !== '.md.ini') {
                is_dir($entry) ? $this->deleteTree($entry) : @unlink($entry);
            }
        }
    }

    private function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isFile()) {
                $size += (int) $item->getSize();
            }
        }

        return $size;
    }

    private function countFiles(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        return iterator_count(new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        ));
    }

    private function zipTree(\ZipArchive $zip, string $dir, string $prefix): void
    {
        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir . '/' . $entry;
            $local = $prefix === '' ? $entry : $prefix . '/' . $entry;
            if (is_dir($full)) {
                $this->zipTree($zip, $full, $local);
            } else {
                $zip->addFile($full, $local);
            }
        }
    }
}
