<?php

/**
 * MD.Scribe 3.3 — document revisions.
 *
 * Every successful editor save keeps the written content as a revision, one
 * directory per document (`storage/revisions/<sha256(path)>/`), capped at
 * REVISIONS_MAX (default 20, newest kept). A revision is only stored when its
 * hash differs from the newest one, so pressing Save twice does not create a
 * duplicate. Restoring is a normal write, audited like any other save, and the
 * pre-restore content is kept as a revision first.
 */

declare(strict_types=1);

namespace MdViewer\Files;

use MdViewer\Core\Config;
use MdViewer\Core\Logger;
use MdViewer\Core\Paths;

final class RevisionService
{
    private const string ID_PATTERN = '/^\d{16}-[a-f0-9]{8}$/';

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger = new Logger(),
        private readonly string $dir = '',
    ) {
    }

    public function enabled(): bool
    {
        return $this->config->bool('REVISIONS_ENABLED');
    }

    public function maxRevisions(): int
    {
        return max(1, min(200, $this->config->int('REVISIONS_MAX')));
    }

    /**
     * Store one revision of $relativePath.
     *
     * @return array{id: string, time: int, actor: string, bytes: int, sha256: string}|null null when disabled or unchanged
     */
    public function save(string $relativePath, string $content, string $actor = 'admin'): ?array
    {
        if (!$this->enabled()) {
            return null;
        }
        $relativePath = self::normalizePath($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $sha = hash('sha256', $content);
        $latest = $this->listRevisions($relativePath)[0] ?? null;
        if ($latest !== null && $latest['sha256'] === $sha) {
            return null;
        }

        $dir = $this->documentDir($relativePath);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $this->logger->warning('revision: storage not writable', ['dir' => $dir]);

            return null;
        }

        $time = time();
        $now = microtime(true);
        $micro = min(999999, max(0, (int) round(($now - (float) $time) * 1_000_000)));
        // 16-digit prefix keeps lexical order equal to chronological order even
        // when several revisions land inside the same second.
        $id = sprintf('%010d%06d-%s', $time, $micro, substr($sha, 0, 8));
        $meta = [
            'path' => $relativePath,
            'time' => $time,
            'actor' => $actor,
            'bytes' => strlen($content),
            'sha256' => $sha,
        ];

        if (@file_put_contents($dir . '/' . $id . '.md', $content, LOCK_EX) === false) {
            $this->logger->warning('revision: write failed', ['path' => $relativePath]);

            return null;
        }
        @file_put_contents(
            $dir . '/' . $id . '.json',
            (string) json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX,
        );
        @chmod($dir . '/' . $id . '.md', 0o644);
        @chmod($dir . '/' . $id . '.json', 0o644);

        $this->prune($relativePath);

        return [
            'id' => $id,
            'time' => $time,
            'actor' => $actor,
            'bytes' => strlen($content),
            'sha256' => $sha,
        ];
    }

    /**
     * Revisions of one document, newest first.
     *
     * @return list<array{id: string, time: int, actor: string, bytes: int, sha256: string}>
     */
    public function listRevisions(string $relativePath): array
    {
        $relativePath = self::normalizePath($relativePath);
        if ($relativePath === '') {
            return [];
        }

        $dir = $this->documentDir($relativePath);
        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        foreach (glob($dir . '/*.md') ?: [] as $file) {
            $id = basename($file, '.md');
            if (preg_match(self::ID_PATTERN, $id) !== 1) {
                continue;
            }
            $meta = $this->readMeta($dir, $id);
            $items[] = [
                'id' => $id,
                'time' => (int) ($meta['time'] ?? (int) @filemtime($file)),
                'actor' => (string) ($meta['actor'] ?? 'unknown'),
                'bytes' => (int) ($meta['bytes'] ?? (int) @filesize($file)),
                'sha256' => (string) ($meta['sha256'] ?? ''),
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($b['id'], $a['id']));

        return $items;
    }

    /** Content of one revision, or null when the id is unknown or malformed. */
    public function read(string $relativePath, string $id): ?string
    {
        $relativePath = self::normalizePath($relativePath);
        if ($relativePath === '' || preg_match(self::ID_PATTERN, $id) !== 1) {
            return null;
        }

        $file = $this->documentDir($relativePath) . '/' . $id . '.md';
        if (!is_file($file)) {
            return null;
        }
        $content = @file_get_contents($file);

        return is_string($content) ? $content : null;
    }

    /**
     * Documents that have revisions, newest activity first.
     *
     * @return list<array{path: string, count: int, last: int, actor: string}>
     */
    public function documents(): array
    {
        $root = $this->root();
        if (!is_dir($root)) {
            return [];
        }

        $out = [];
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $revisions = $this->listFromDir($dir);
            if ($revisions === []) {
                continue;
            }
            $newest = $revisions[0];
            $meta = $this->readMeta($dir, $newest['id']);
            $out[] = [
                'path' => (string) ($meta['path'] ?? ''),
                'count' => count($revisions),
                'last' => $newest['time'],
                'actor' => $newest['actor'],
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['last'] <=> $a['last']);

        return $out;
    }

    /** Keep only the newest REVISIONS_MAX entries of one document. */
    public function prune(string $relativePath): int
    {
        $relativePath = self::normalizePath($relativePath);
        if ($relativePath === '') {
            return 0;
        }

        $dir = $this->documentDir($relativePath);
        if (!is_dir($dir)) {
            return 0;
        }

        $removed = 0;
        foreach (array_slice($this->listFromDir($dir), $this->maxRevisions()) as $old) {
            if (@unlink($dir . '/' . $old['id'] . '.md')) {
                $removed++;
            }
            @unlink($dir . '/' . $old['id'] . '.json');
        }

        return $removed;
    }

    // ── Internals ──────────────────────────────────────────────────────────

    /**
     * @return list<array{id: string, time: int, actor: string, bytes: int, sha256: string}>
     */
    private function listFromDir(string $dir): array
    {
        $items = [];
        foreach (glob($dir . '/*.md') ?: [] as $file) {
            $id = basename($file, '.md');
            if (preg_match(self::ID_PATTERN, $id) !== 1) {
                continue;
            }
            $meta = $this->readMeta($dir, $id);
            $items[] = [
                'id' => $id,
                'time' => (int) ($meta['time'] ?? (int) @filemtime($file)),
                'actor' => (string) ($meta['actor'] ?? 'unknown'),
                'bytes' => (int) ($meta['bytes'] ?? (int) @filesize($file)),
                'sha256' => (string) ($meta['sha256'] ?? ''),
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($b['id'], $a['id']));

        return $items;
    }

    /** @return array<string, mixed> */
    private function readMeta(string $dir, string $id): array
    {
        $file = $dir . '/' . $id . '.json';
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || !json_validate($raw)) {
            return [];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function documentDir(string $relativePath): string
    {
        return $this->root() . '/' . hash('sha256', $relativePath);
    }

    private function root(): string
    {
        $dir = $this->dir !== '' ? $this->dir : Paths::revisions();

        return rtrim($dir, '/\\');
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return ltrim($path, '/');
    }
}
