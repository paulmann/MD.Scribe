<?php

/**
 * MD.Viewer 3.0 — single source of truth for the application version.
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Version
{
    public const string VERSION = '3.0.1';
    public const string CODENAME = 'Aurora';
    public const string PHP_MIN = '8.3.0';
    public const string REPO = 'https://github.com/paulmann/MD.Viewer';
    public const string RAW_BASE = 'https://raw.githubusercontent.com/paulmann/MD.Viewer/main';
    public const string API_BASE = 'https://api.github.com/repos/paulmann/MD.Viewer';

    public static function userAgent(): string
    {
        return 'MD.Viewer/' . self::VERSION . ' (+https://github.com/paulmann/MD.Viewer) PHP/' . PHP_VERSION;
    }

    /** "3.0.0" → [3, 0, 0] */
    public static function parts(string $version): array
    {
        $parts = array_map(intval(...), explode('.', preg_replace('/[^0-9.].*$/', '', $version) ?? ''));

        return [$parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0];
    }

    /** -1 when $a < $b, 0 when equal, 1 when $a > $b (semver-ish). */
    public static function compare(string $a, string $b): int
    {
        return self::parts($a) <=> self::parts($b);
    }
}
