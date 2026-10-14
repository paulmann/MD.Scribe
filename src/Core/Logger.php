<?php

/**
 * MD.Viewer 3.0 — small PSR-3-style file logger with daily rotation.
 *
 * Writes to storage/logs/app-YYYY-MM-DD.log. Level threshold and retention
 * come from `.md.ini` (LOG_LEVEL, LOG_RETENTION_DAYS). Never throws: a logger
 * that breaks the page is worse than no logger.
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Logger
{
    public const array LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    private int $threshold;
    private int $retentionDays;
    private ?string $dir = null;

    public function __construct(
        ?Config $config = null,
        ?string $dir = null,
    ) {
        $this->threshold = self::LEVELS[strtolower($config?->str('LOG_LEVEL') ?? 'warning')] ?? 2;
        if ($config?->bool('APP_DEBUG') === true) {
            $this->threshold = 0;
        }
        $this->retentionDays = max(1, $config?->int('LOG_RETENTION_DAYS') ?? 14);
        $this->dir = $dir ?? Paths::logs();
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if ((self::LEVELS[$level] ?? 2) < $this->threshold) {
            return;
        }
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0o755, true) && !is_dir($this->dir)) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $this->interpolate($message, $context),
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        @file_put_contents($this->file(), $line, FILE_APPEND | LOCK_EX);
        $this->prune();
    }

    private function file(): string
    {
        return $this->dir . '/app-' . date('Y-m-d') . '.log';
    }

    /** @param array<string, mixed> $context */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    private ?string $lastPrune = null;

    private function prune(): void
    {
        $today = date('Y-m-d');
        if ($this->lastPrune === $today) {
            return;
        }
        $this->lastPrune = $today;

        $cutoff = time() - $this->retentionDays * 86400;
        foreach ((array) glob($this->dir . '/app-*.log') as $file) {
            if (is_string($file) && (int) @filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    /** Recent log lines for the admin log viewer. @return list<array{file: string, lines: list<string>}> */
    public function tail(int $days = 3, int $linesPerFile = 200): array
    {
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $file = $this->dir . '/app-' . date('Y-m-d', time() - $i * 86400) . '.log';
            if (!is_file($file)) {
                continue;
            }
            $content = (string) @file_get_contents($file);
            $lines = array_slice(array_filter(explode("\n", $content), static fn ($l) => $l !== ''), -$linesPerFile);
            $out[] = ['file' => basename($file), 'lines' => array_values($lines)];
        }

        return $out;
    }
}
