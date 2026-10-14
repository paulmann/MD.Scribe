<?php

/**
 * MD.Scribe 3.0 — configuration loader for `.md.ini`.
 *
 * Behaviour preserved from MD.Viewer v2 (and hardened):
 *  - `.md.ini` is created automatically next to the project root on first load;
 *  - missing schema keys are appended with safe defaults on every load, so
 *    upgrading never requires manual edits;
 *  - values are parsed strictly: trimmed, optional paired quotes stripped, a
 *    value containing a NUL byte, a newline or more than 255 bytes is rejected;
 *  - keys explicitly present in the file are "hard overrides" — they win over
 *    the per-visitor cookie toggles managed by FeatureFlags;
 *  - per-directory `.md.ini` files inside the browse root may override the
 *    three DISABLE_* interface keys (deeper file wins).
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Config
{
    public const int MAX_VALUE_BYTES = 255;

    /** Keys honoured in a per-directory `.md.ini` (everything else is ignored + logged). */
    public const array PER_DIR_KEYS = [
        'DISABLE_UPLOAD',
        'DISABLE_CLIPBOARD',
        'DISABLE_SAVE_CLIPBOARD_TO_FILE',
    ];

    /** @var array<string, array{type: string, default: mixed, section: string, label: string, description: string}> */
    private static ?array $schema = null;

    /** @var array<string, string> raw parsed values from .md.ini */
    private array $values = [];

    /** @var array<string, true> keys explicitly present in .md.ini (hard overrides) */
    private array $explicit = [];

    private bool $writable = true;
    private string $iniPath;

    private static ?self $instance = null;

    private function __construct(string $iniPath)
    {
        $this->iniPath = $iniPath;
        $this->load();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(Paths::iniFile());
    }

    /** Test seam: load configuration from an arbitrary file. */
    public static function loadFrom(string $iniPath): self
    {
        return self::$instance = new self($iniPath);
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /** @return array<string, array{type: string, default: mixed, section: string, label: string, description: string}> */
    public static function schema(): array
    {
        if (self::$schema === null) {
            /** @var array<string, array{type: string, default: mixed, section: string, label: string, description: string}> $schema */
            $schema = require Paths::config() . '/defaults.php';
            self::$schema = $schema;
        }

        return self::$schema;
    }

    public function iniPath(): string
    {
        return $this->iniPath;
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    // ── Typed accessors ────────────────────────────────────────────────────

    public function get(string $key): string|int|bool
    {
        $schema = self::schema()[$key] ?? null;
        $type = (string) ($schema['type'] ?? 'string');
        $default = $schema['default'] ?? '';
        $raw = $this->values[$key] ?? null;

        if ($raw === null) {
            return $default;
        }

        return match ($type) {
            'bool' => self::parseBool($raw),
            'int' => is_numeric($raw) ? (int) $raw : $default,
            default => $raw,
        };
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function str(string $key): string
    {
        return (string) $this->get($key);
    }

    /** True when the key was explicitly present in `.md.ini` (hard override). */
    public function isExplicit(string $key): bool
    {
        return isset($this->explicit[$key]);
    }

    /** Raw string values exactly as they appear in the file (for the settings editor). */
    public function rawValue(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    // ── Derived values ─────────────────────────────────────────────────────

    /**
     * Resolve BROWSE_DIR the way v2 did: absolute path, DOCUMENT_ROOT-relative,
     * then project-root-relative. Empty value → the bundled content/ directory.
     */
    public function browseDir(): string
    {
        $value = trim($this->str('BROWSE_DIR'));
        if ($value === '') {
            return Paths::content();
        }

        $candidates = [];
        if (str_starts_with($value, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $value) === 1) {
            $candidates[] = $value;
        } else {
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
            if (is_string($docRoot) && $docRoot !== '') {
                $candidates[] = rtrim($docRoot, '/\\') . '/' . ltrim($value, '/\\');
            }
            $candidates[] = Paths::root() . '/' . ltrim($value, '/\\');
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && is_dir($real) && is_readable($real)) {
                return $real;
            }
        }

        error_log("[MD.Scribe] BROWSE_DIR '{$value}' does not resolve to a readable directory; using content/");

        return Paths::content();
    }

    // ── Per-directory flag layering ────────────────────────────────────────

    /**
     * Effective DISABLE_* flags for a directory relative to the browse root.
     * Base file first, then every `.md.ini` from the root down to the target
     * directory; a deeper file wins. Null segment list → base flags only.
     *
     * @param list<string>|null $segments validated directory segments ('' = root)
     * @return array{DISABLE_UPLOAD: bool, DISABLE_CLIPBOARD: bool, DISABLE_SAVE_CLIPBOARD_TO_FILE: bool}
     */
    public function flagsFor(?array $segments): array
    {
        $flags = [];
        foreach (self::PER_DIR_KEYS as $key) {
            $flags[$key] = $this->bool($key);
        }

        if ($segments === null) {
            /** @var array{DISABLE_UPLOAD: bool, DISABLE_CLIPBOARD: bool, DISABLE_SAVE_CLIPBOARD_TO_FILE: bool} $flags */
            return $flags;
        }

        $root = $this->browseDir();
        $realRoot = realpath($root);
        $current = $root;
        $walked = [];

        foreach ($segments as $segment) {
            if ($segment !== '') {
                $walked[] = $segment;
                $current = $root . '/' . implode('/', $walked);
            }
            $ini = $current . '/.md.ini';
            if (!is_file($ini) || !is_readable($ini)) {
                continue;
            }
            $real = realpath($ini);
            if ($real === false || $realRoot === false || !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
                error_log("[MD.Scribe] refused directory ini outside browse root: {$ini}");
                continue;
            }
            foreach (self::readIniFile($real) as $key => $value) {
                if (in_array($key, self::PER_DIR_KEYS, true)) {
                    $flags[$key] = self::parseBool($value);
                } elseif (isset(self::schema()[$key])) {
                    error_log("[MD.Scribe] ignored installation-level key {$key} in directory ini {$ini}");
                }
            }
        }

        /** @var array{DISABLE_UPLOAD: bool, DISABLE_CLIPBOARD: bool, DISABLE_SAVE_CLIPBOARD_TO_FILE: bool} $flags */
        return $flags;
    }

    // ── Loading & persistence ──────────────────────────────────────────────

    private function load(): void
    {
        $schema = self::schema();

        if (!is_file($this->iniPath)) {
            $this->values = [];
            $this->createFile($schema);
            $this->applyAutogenerate($schema);

            return;
        }

        $parsed = self::readIniFile($this->iniPath);
        $this->values = $parsed;
        $this->explicit = array_fill_keys(array_keys($parsed), true);

        $this->applyAutogenerate($schema);
        $this->bootstrapAdminPassword();
        $this->appendMissingKeys($schema);
    }

    private function applyAutogenerate(array $schema): void
    {
        foreach ($schema as $key => $meta) {
            if (($meta['autogenerate'] ?? null) === 'api_key' && ($this->values[$key] ?? '') === '') {
                $this->values[$key] = self::generateApiKey();
                $this->explicit[$key] = true;
                $this->persistKey($key);
            }
        }
    }

    /**
     * ADMIN_PASSWORD (plaintext, one-shot) → ADMIN_PASSWORD_HASH, then clear.
     */
    private function bootstrapAdminPassword(): void
    {
        $plain = $this->values['ADMIN_PASSWORD'] ?? '';
        if ($plain === '') {
            return;
        }
        $this->values['ADMIN_PASSWORD_HASH'] = password_hash($plain, PASSWORD_DEFAULT);
        $this->values['ADMIN_PASSWORD'] = '';
        $this->explicit['ADMIN_PASSWORD_HASH'] = true;
        $this->persistKey('ADMIN_PASSWORD_HASH');
        $this->persistKey('ADMIN_PASSWORD');
    }

    private function createFile(array $schema): void
    {
        $lines = [
            '; MD.Scribe ' . Version::VERSION . ' — server-side configuration',
            '; Created automatically on first load. Missing installation-level',
            '; keys are appended with safe defaults on every load.',
            ';',
            '; Rendering-feature keys (AUTO_TOC, FEATURE_*, …) are deliberately',
            '; NOT written here: their defaults live in config/defaults.php and',
            '; visitors may toggle them per-browser. Adding a feature key to',
            '; this file HARD-LOCKS it (ini wins over visitor cookies) — which',
            '; is exactly what the admin Settings editor does when you save one.',
            '; This file is never served to the browser.',
            '',
        ];
        $lastSection = null;
        foreach ($schema as $key => $meta) {
            if (($meta['feature'] ?? false) === true) {
                continue; // visitor-toggleable — keep out of the generated ini
            }
            if ($meta['section'] !== $lastSection) {
                $lines[] = '';
                $lines[] = '; ── ' . ucfirst($meta['section']) . ' ──';
                $lastSection = $meta['section'];
            }
            $lines[] = '; ' . $meta['label'];
            $lines[] = $key . ' = ' . self::renderDefault($meta['default']);
        }
        $this->writeIni(implode("\n", $lines) . "\n");
    }

    /**
     * Append schema keys missing from an existing file, grouped by section.
     * Existing content (including comments and unknown keys) is preserved.
     */
    private function appendMissingKeys(array $schema): void
    {
        $missing = [];
        foreach ($schema as $key => $meta) {
            if (($meta['feature'] ?? false) === true) {
                continue; // feature keys are never auto-appended (see createFile)
            }
            if (!array_key_exists($key, $this->values)) {
                $missing[$key] = $meta;
            }
        }
        if ($missing === []) {
            return;
        }

        $bySection = [];
        foreach ($missing as $key => $meta) {
            $bySection[$meta['section']][$key] = $meta;
        }

        $lines = ['', '; ── Keys added automatically by MD.Scribe ' . Version::VERSION . ' ──'];
        foreach ($bySection as $section => $keys) {
            $lines[] = '; ' . ucfirst((string) $section);
            foreach ($keys as $key => $meta) {
                $lines[] = $key . ' = ' . self::renderDefault($meta['default']);
            }
            $lines[] = '';
        }

        $existing = is_file($this->iniPath) ? (string) file_get_contents($this->iniPath) : '';
        if ($existing !== '' && !str_ends_with($existing, "\n")) {
            $existing .= "\n";
        }
        $this->writeIni($existing . implode("\n", $lines));
    }

    /** Rewrite a single key's value inside the ini file (or append it). */
    private function persistKey(string $key): void
    {
        if (!is_file($this->iniPath)) {
            return;
        }
        $content = (string) file_get_contents($this->iniPath);
        $value = self::renderDefault($this->values[$key] ?? '');
        // [ \t]* (not \s*) — a \s would swallow the newline and eat the next
        // line; `.*` stops at end-of-line under /m.
        $pattern = '/^([ \t]*' . preg_quote($key, '/') . '[ \t]*=[ \t]*).*/m';
        if (preg_match($pattern, $content) === 1) {
            // Callback replacement: values like password hashes ($2y$10$…)
            // must never be interpreted as preg backreferences.
            $content = (string) preg_replace_callback(
                $pattern,
                static fn (array $m): string => $m[1] . $value,
                $content,
                1,
            );
        } else {
            $content = rtrim($content, "\n") . "\n" . $key . ' = ' . $value . "\n";
        }
        $this->writeIni($content);
    }

    /** Persist arbitrary key => value changes made through the admin panel. */
    public function set(string $key, string|int|bool $value): void
    {
        if (!isset(self::schema()[$key])) {
            throw new \InvalidArgumentException("Unknown configuration key: {$key}");
        }
        $rendered = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $this->values[$key] = $rendered;
        $this->explicit[$key] = true;
        $this->persistKey($key);
    }

    private function writeIni(string $content): void
    {
        $dir = dirname($this->iniPath);
        if (!is_dir($dir)) {
            $this->writable = false;

            return;
        }
        $tmp = $this->iniPath . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            $this->writable = false;
            error_log("[MD.Scribe] cannot write {$this->iniPath} — running with in-memory defaults");

            return;
        }
        @chmod($tmp, 0o640);
        if (!@rename($tmp, $this->iniPath)) {
            @unlink($tmp);
            $this->writable = false;
        }
    }

    /**
     * Strict per-line parser (same rules as v2): KEY = VALUE, `;`/`#` comments,
     * optional paired quotes, reject NUL/newline/oversized values.
     *
     * @return array<string, string>
     */
    public static function readIniFile(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $out = [];
        foreach (preg_split('/\r\n|\n|\r/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }
            $len = strlen($value);
            if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            if (str_contains($value, "\0") || str_contains($value, "\n") || strlen($value) > self::MAX_VALUE_BYTES) {
                error_log("[MD.Scribe] discarded unusable value for {$key} in {$path}");
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public static function parseBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function renderDefault(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    public static function generateApiKey(): string
    {
        return 'mdv_' . bin2hex(random_bytes(20));
    }
}
