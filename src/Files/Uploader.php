<?php

/**
 * MD.Viewer 3.0 — reader-side upload & clipboard-save.
 *
 * Every write goes through: per-directory flag check → filename sanitisation
 * → size cap → collision-safe unique name → atomic write → audit log.
 * The v2 three-layer rule stands: UI gate, JS gate, and this server gate —
 * the server gate is the only one that counts.
 */

declare(strict_types=1);

namespace MdViewer\Files;

use MdViewer\Core\AuditLog;
use MdViewer\Core\Config;
use MdViewer\Core\Logger;

final class Uploader
{
    public function __construct(
        private readonly Config $config,
        private readonly MarkdownRepository $repo,
        private readonly Logger $logger = new Logger(),
        private readonly ?AuditLog $audit = null,
    ) {
    }

    /**
     * Handle a classic $_FILES upload of one .md file.
     *
     * @param array<string, mixed> $file single entry from $_FILES
     * @return array{ok: bool, error?: string, status?: int, path?: string}
     */
    public function uploadMarkdown(array $file, string $dirRel, string $actor = 'reader'): array
    {
        $flags = $this->flagsFor($dirRel);
        if ($flags['DISABLE_UPLOAD']) {
            return ['ok' => false, 'status' => 403, 'error' => 'Uploads are disabled for this directory.'];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'status' => 400, 'error' => 'Upload failed with PHP error code ' . $error . '.'];
        }

        $size = (int) ($file['size'] ?? 0);
        $maxBytes = max(1, $this->config->int('MAX_UPLOAD_MB')) * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'status' => 413, 'error' => 'File is empty or larger than the ' . $this->config->int('MAX_UPLOAD_MB') . ' MB cap.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'status' => 400, 'error' => 'Missing upload payload.'];
        }

        $content = @file_get_contents($tmp);
        if ($content === false) {
            return ['ok' => false, 'status' => 500, 'error' => 'Cannot read the uploaded file.'];
        }

        return $this->saveMarkdown($content, $name, $dirRel, $actor);
    }

    /**
     * Write markdown content as a new .md file (upload or clipboard save).
     *
     * @return array{ok: bool, error?: string, status?: int, path?: string}
     */
    public function saveMarkdown(string $content, string $filename, string $dirRel, string $actor = 'reader', bool $fromClipboard = false): array
    {
        $flags = $this->flagsFor($dirRel);
        if ($fromClipboard && $flags['DISABLE_SAVE_CLIPBOARD_TO_FILE']) {
            return ['ok' => false, 'status' => 403, 'error' => 'Saving clipboard content is disabled for this directory.'];
        }
        if (!$fromClipboard && $flags['DISABLE_UPLOAD']) {
            return ['ok' => false, 'status' => 403, 'error' => 'Uploads are disabled for this directory.'];
        }

        $filename = $this->sanitizeFilename($filename);
        if ($filename === null) {
            return ['ok' => false, 'status' => 400, 'error' => 'Invalid filename: only letters, digits, spaces, dashes, underscores and dots are allowed; must end in .md.'];
        }

        $segments = $this->repo->validator()->dirSegments($dirRel);
        if ($segments === null) {
            return ['ok' => false, 'status' => 400, 'error' => 'Invalid target directory.'];
        }

        $dirAbs = $this->repo->validator()->validateDir($dirRel, $this->repo->browseDir());
        if ($dirAbs === null) {
            return ['ok' => false, 'status' => 400, 'error' => 'Target directory does not exist inside the browse root.'];
        }

        $target = $this->uniqueTarget($dirAbs, $filename);
        $bytes = strlen($content);
        $maxBytes = max(1, $this->config->int('MAX_UPLOAD_MB')) * 1024 * 1024;
        if ($bytes > $maxBytes) {
            return ['ok' => false, 'status' => 413, 'error' => 'Content exceeds the size cap.'];
        }

        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            $this->logger->error('upload: write failed', ['target' => $target]);

            return ['ok' => false, 'status' => 500, 'error' => 'Cannot write to the target directory (permissions?).'];
        }
        @chmod($tmp, 0o644);
        if (!@rename($tmp, $target)) {
            @unlink($tmp);

            return ['ok' => false, 'status' => 500, 'error' => 'Cannot place the file (rename failed).'];
        }

        $relative = $this->repo->validator()->relativize($target, $this->repo->browseDir()) ?? basename($target);
        $this->audit?->record($fromClipboard ? 'clipboard.save' : 'upload.markdown', $actor, [
            'path' => $relative,
            'bytes' => $bytes,
        ]);
        $this->logger->info(($fromClipboard ? 'clipboard save' : 'upload') . ': ' . $relative, ['bytes' => $bytes, 'actor' => $actor]);

        return ['ok' => true, 'path' => $relative];
    }

    /** Effective per-directory flags for a relative dir (null-safe). */
    public function flagsFor(string $dirRel): array
    {
        $segments = $dirRel === '' ? [] : $this->repo->validator()->dirSegments($dirRel);

        return $this->config->flagsFor($segments);
    }

    /**
     * v2 filename rule: basename only, no slashes, no NUL, must end in .md,
     * characters restricted to the path whitelist minus separators.
     */
    public function sanitizeFilename(string $name): ?string
    {
        $name = trim($name);
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '/') || str_contains($name, '\\')) {
            return null;
        }
        if (strlen($name) > PathValidator::MAX_PARAM_LENGTH) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            return null;
        }
        if (!preg_match('#^[A-Za-z0-9._\- \p{L}\p{M}\p{Pd}]+$#u', $name)) {
            return null;
        }
        if (!str_ends_with(mb_strtolower($name, 'UTF-8'), '.md')) {
            return null; // v2 rule: .md only — never rename silently
        }
        if ($name === '.md' || str_starts_with($name, '.')) {
            return null;
        }

        return $name;
    }

    /** Collision-safe target: name.md → name-1.md → name-2.md … */
    private function uniqueTarget(string $dir, string $filename): string
    {
        $target = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($target)) {
            return $target;
        }
        $stem = substr($filename, 0, -3);
        for ($i = 1; $i <= 999; $i++) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $stem . '-' . $i . '.md';
            if (!file_exists($candidate)) {
                return $candidate;
            }
        }

        return $dir . DIRECTORY_SEPARATOR . $stem . '-' . bin2hex(random_bytes(3)) . '.md';
    }
}
