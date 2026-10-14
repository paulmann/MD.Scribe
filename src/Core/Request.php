<?php

/**
 * MD.Viewer 3.0 — immutable-ish request wrapper.
 *
 * Knows how the app is mounted: every entry script (public/index.php,
 * public/admin.php, the root compatibility shims) boots the App with the web
 * prefix of its own directory, so generated URLs and asset paths stay correct
 * for root installs, sub-directory installs and docroot-at-public installs.
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Request
{
    /** @var array<string, mixed> */
    private readonly array $query;
    /** @var array<string, mixed> */
    private readonly array $post;
    /** @var array<string, mixed> */
    private readonly array $cookies;
    /** @var array<string, mixed> */
    private readonly array $files;
    /** @var array<string, string> */
    private readonly array $server;

    private readonly string $method;

    /**
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     * @param array<string, mixed>|null $server
     */
    public function __construct(
        private readonly string $scriptUrl,
        private readonly string $assetBase,
        ?array $query = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null,
        ?array $server = null,
    ) {
        $this->server = $server ?? $_SERVER;
        $this->query = $query ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->cookies = $cookies ?? $_COOKIE;
        $this->files = $files ?? $_FILES;
        $this->method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function fromGlobals(string $scriptUrl, string $assetBase): self
    {
        return new self($scriptUrl, $assetBase);
    }

    // ── Basics ─────────────────────────────────────────────────────────────

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        if (($this->query['format'] ?? null) === 'json') {
            return true;
        }
        $accept = $this->header('Accept') ?? '';

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }
        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $serverKey => $headerName) {
            if (strcasecmp($name, $headerName) === 0 && isset($this->server[$serverKey])) {
                return (string) $this->server[$serverKey];
            }
        }

        return null;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function queryAll(): array
    {
        return $this->query;
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    public function post(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function postAll(): array
    {
        return $this->post;
    }

    /** Raw JSON body decoded (or [] when absent/invalid). @return array<string, mixed> */
    public function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '' || !str_starts_with(ltrim($raw), '{')) {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $value = $this->cookies[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    // ── Client IP (trusted-proxy aware) ────────────────────────────────────

    public function ip(?string $trustedProxiesCsv = null): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if ($trustedProxiesCsv === null || trim($trustedProxiesCsv) === '') {
            return $remote;
        }
        $trusted = array_map(trim(...), explode(',', $trustedProxiesCsv));
        if (!in_array($remote, $trusted, true)) {
            return $remote;
        }
        $forwarded = $this->header('X-Forwarded-For');
        if ($forwarded !== null && $forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        return $remote;
    }

    // ── URL building ───────────────────────────────────────────────────────

    /** Web path of the current entry script, e.g. "/docs/public/index.php". */
    public function scriptUrl(): string
    {
        return $this->scriptUrl;
    }

    /** Web prefix where /assets lives, e.g. "/docs/public" or "/". */
    public function assetBase(): string
    {
        return $this->assetBase;
    }

    public function assetUrl(string $path): string
    {
        return rtrim($this->assetBase, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Build a URL against the current entry script.
     *
     * @param array<string, string|int|bool|null> $params null values are dropped
     */
    public function url(array $params = []): string
    {
        $query = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $this->scriptUrl . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /** Absolute origin, e.g. "https://docs.example.com" (proxy-aware). */
    public function origin(?string $trustedProxiesCsv = null): string
    {
        $https = ($this->server['HTTPS'] ?? '') !== '' && ($this->server['HTTPS'] ?? '') !== 'off';
        if (!$https && $trustedProxiesCsv !== null) {
            $trusted = array_map(trim(...), explode(',', $trustedProxiesCsv));
            $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');
            if (in_array($remote, $trusted, true)) {
                $proto = strtolower((string) ($this->header('X-Forwarded-Proto') ?? ''));
                $https = $proto === 'https';
            }
        }
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost');
        // Strip port for well-known ports; keep it otherwise.
        $host = preg_replace('/:(80|443)$/', '', $host) ?? $host;

        return $scheme . '://' . $host;
    }

    public function isSecure(?string $trustedProxiesCsv = null): bool
    {
        return str_starts_with($this->origin($trustedProxiesCsv), 'https://');
    }
}
