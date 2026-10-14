<?php

/**
 * MD.Viewer 3.0 — feature toggle resolver.
 *
 * Same three-level chain as MD.Viewer v2, formalised:
 *  1. a key explicitly present in `.md.ini` is a HARD override (admin wins);
 *  2. otherwise a per-visitor cookie `mdv_feat_{KEY}` (set by the Settings
 *     panel after cookie consent) may override the default;
 *  3. otherwise the schema default applies.
 *
 * Only keys flagged `feature => true` in config/defaults.php are cookie-
 * overridable; security keys never are.
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class FeatureFlags
{
    public const string COOKIE_PREFIX = 'mdv_feat_';

    /** @var array<string, bool|string> resolved cache */
    private array $resolved = [];

    /** @var array<string, string> */
    private array $cookies;

    public function __construct(
        private readonly Config $config,
        ?array $cookies = null,
    ) {
        $this->cookies = $cookies ?? $_COOKIE;
    }

    /** Feature keys that a visitor may toggle from the Settings panel. */
    public static function toggleableKeys(): array
    {
        $keys = [];
        foreach (Config::schema() as $key => $meta) {
            if (($meta['feature'] ?? false) === true) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function cookieName(string $key): string
    {
        return self::COOKIE_PREFIX . $key;
    }

    public function enabled(string $key): bool
    {
        return $this->get($key) === true;
    }

    public function get(string $key): bool|string
    {
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        $meta = Config::schema()[$key] ?? null;
        if ($meta === null) {
            throw new \InvalidArgumentException("Unknown feature key: {$key}");
        }

        $value = $this->resolve($key, $meta);

        return $this->resolved[$key] = $value;
    }

    private function resolve(string $key, array $meta): bool|string
    {
        $default = $meta['default'];
        $isBool = $meta['type'] === 'bool';

        // 1. Hard override from .md.ini
        if ($this->config->isExplicit($key)) {
            $raw = (string) $this->config->rawValue($key);

            return $isBool ? Config::parseBool($raw) : $raw;
        }

        // 2. Cookie override (only for toggleable feature keys)
        if (($meta['feature'] ?? false) === true) {
            $cookie = $this->cookies[self::cookieName($key)] ?? null;
            if (is_string($cookie) && $cookie !== '') {
                if ($isBool) {
                    return $cookie === '1';
                }
                // String features accept a small vocabulary only.
                if ($key === 'PARAGRAPH_BREAK_STYLE') {
                    return in_array($cookie, ['double-br', 'paragraph'], true) ? $cookie : $default;
                }

                return mb_substr($cookie, 0, 64);
            }
        }

        // 3. Schema default
        return $isBool ? (bool) $default : (string) $default;
    }

    /**
     * Snapshot for window.MDV_CONFIG.
     *
     * @return array<string, bool|string>
     */
    public function snapshot(): array
    {
        $out = [];
        foreach (self::toggleableKeys() as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    /**
     * A fingerprint of every toggle that changes rendered output — part of the
     * render-cache key so two visitors with different cookies never share HTML.
     */
    public function cacheFingerprint(): string
    {
        $parts = [];
        foreach (self::toggleableKeys() as $key) {
            $value = $this->get($key);
            $parts[] = $key . '=' . (is_bool($value) ? ($value ? '1' : '0') : $value);
        }

        return substr(hash('xxh128', implode('|', $parts)), 0, 16);
    }
}
