<?php

/**
 * MD.Viewer 3.0 — rendered-document cache.
 *
 * One JSON file per (document, mtime, size, feature fingerprint) tuple in
 * storage/cache. A changed file or a changed visitor feature set misses the
 * cache automatically; stale entries are pruned opportunistically.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

use MdViewer\Core\Logger;
use MdViewer\Core\Version;

final class RenderCache
{
    private int $gcCounter = 0;

    public function __construct(
        private readonly bool $enabled,
        private readonly Logger $logger = new Logger(),
        private readonly string $dir = '',
    ) {
    }

    private function dir(): string
    {
        $dir = $this->dir !== '' ? $this->dir : \MdViewer\Core\Paths::cache();
        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }

        return $dir;
    }

    public function key(string $identity, int $mtime, int $size, string $fingerprint): string
    {
        return hash('sha256', implode('|', [Version::VERSION, $identity, $mtime, $size, $fingerprint]));
    }

    public function get(string $key): ?RenderResult
    {
        if (!$this->enabled) {
            return null;
        }
        $file = $this->dir() . '/' . $key . '.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = (string) @file_get_contents($file);
        if (!json_validate($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['v'] ?? '') !== Version::VERSION) {
            return null;
        }

        try {
            return RenderResult::fromArray($data['r'] ?? []);
        } catch (\Throwable) {
            return null;
        }
    }

    public function put(string $key, RenderResult $result): void
    {
        if (!$this->enabled) {
            return;
        }
        $payload = (string) json_encode(
            ['v' => Version::VERSION, 'r' => $result->toArray()],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $file = $this->dir() . '/' . $key . '.json';
        $tmp = $file . '.' . bin2hex(random_bytes(3)) . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
        $this->maybeGc();
    }

    /** Delete entries older than $maxAge seconds. Returns count removed. */
    public function gc(int $maxAge = 86400): int
    {
        $removed = 0;
        $cutoff = time() - $maxAge;
        foreach ((array) glob($this->dir() . '/*.json') as $file) {
            if (is_string($file) && (int) @filemtime($file) < $cutoff && @unlink($file)) {
                $removed++;
            }
        }
        if ($removed > 0) {
            $this->logger->debug('render cache gc', ['removed' => $removed]);
        }

        return $removed;
    }

    private function maybeGc(): void
    {
        if (++$this->gcCounter % 50 !== 0) {
            return;
        }
        $this->gc();
    }

    public function clear(): int
    {
        $removed = 0;
        foreach ((array) glob($this->dir() . '/*') as $file) {
            if (is_string($file) && is_file($file) && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}
