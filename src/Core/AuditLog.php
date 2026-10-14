<?php

/**
 * MD.Viewer 3.0 — append-only audit trail for security-relevant actions
 * (logins, uploads, deletions, restores, updates, config changes).
 *
 * Format: one JSON object per line in storage/logs/audit.log — trivially
 * greppable, trivially parsed by the admin viewer.
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class AuditLog
{
    public function __construct(
        private readonly ?Request $request = null,
        private readonly ?string $dir = null,
    ) {
    }

    public function record(string $action, string $actor = 'anonymous', array $details = []): void
    {
        $entry = [
            'ts' => date('c'),
            'action' => $action,
            'actor' => $actor,
            'ip' => $this->request?->ip() ?? 'cli',
            'details' => $details === [] ? null : $details,
        ];

        $dir = $this->dir ?? Paths::logs();
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            return;
        }

        @file_put_contents(
            $dir . '/audit.log',
            json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX,
        );
    }

    /** @return list<array<string, mixed>> newest first */
    public static function read(int $limit = 200, ?string $dir = null): array
    {
        $file = ($dir ?? Paths::logs()) . '/audit.log';
        if (!is_file($file)) {
            return [];
        }
        $lines = array_filter(explode("\n", (string) @file_get_contents($file)));
        $lines = array_slice($lines, -$limit);
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $out[] = $decoded;
            }
        }

        return $out;
    }
}
