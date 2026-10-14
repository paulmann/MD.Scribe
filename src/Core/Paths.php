<?php

/**
 * MD.Viewer 3.0 — filesystem path registry.
 *
 * Every path in the application is resolved through this class so that the
 * project can live anywhere on disk (and be served either through
 * `public/index.php` or through the compatibility shims at the project root).
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Paths
{
    private static ?string $root = null;

    /** Absolute path of the project root (the directory that contains `public/`). */
    public static function root(): string
    {
        return self::$root ??= dirname(__DIR__, 2);
    }

    /** Override the project root — used by the test suite and the CLI tools. */
    public static function setRoot(string $root): void
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw new \RuntimeException("Project root does not exist: {$root}");
        }
        self::$root = $real;
    }

    public static function public(): string
    {
        return self::root() . '/public';
    }

    public static function src(): string
    {
        return self::root() . '/src';
    }

    public static function templates(): string
    {
        return self::root() . '/templates';
    }

    public static function config(): string
    {
        return self::root() . '/config';
    }

    public static function storage(): string
    {
        return self::root() . '/storage';
    }

    public static function cache(): string
    {
        return self::storage() . '/cache';
    }

    public static function backups(): string
    {
        return self::storage() . '/backups';
    }

    public static function logs(): string
    {
        return self::storage() . '/logs';
    }

    public static function index(): string
    {
        return self::storage() . '/index';
    }

    public static function state(): string
    {
        return self::storage() . '/state';
    }

    public static function customThemes(): string
    {
        return self::storage() . '/themes';
    }

    /** Default content directory (overridable via BROWSE_DIR in `.md.ini`). */
    public static function content(): string
    {
        return self::root() . '/content';
    }

    /** Location of the installation-level `.md.ini`. */
    public static function iniFile(): string
    {
        return self::root() . '/.md.ini';
    }

    /**
     * Make sure every writable storage directory exists.
     *
     * @return list<string> directories that could not be created
     */
    public static function ensureStorage(): array
    {
        $missing = [];
        foreach ([
            self::storage(),
            self::cache(),
            self::backups(),
            self::logs(),
            self::index(),
            self::state(),
            self::customThemes(),
        ] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
                $missing[] = $dir;
            }
        }

        return $missing;
    }
}
