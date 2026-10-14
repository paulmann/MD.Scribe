<?php

/**
 * MD.Viewer 3.0 — hardened session wrapper (admin auth + flash messages).
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Session
{
    public const string NAME = 'MDVSESSION';
    public const int DEFAULT_LIFETIME = 3600;

    private bool $started = false;

    public function __construct(
        private readonly int $idleTimeout = self::DEFAULT_LIFETIME,
        private readonly bool $secure = false,
    ) {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) max($this->idleTimeout, 600));

        $this->started = @session_start();

        $this->enforceIdleTimeout();
    }

    private function enforceIdleTimeout(): void
    {
        $now = time();
        $last = $_SESSION['_mdv_last_activity'] ?? null;
        if (is_int($last) && $now - $last > $this->idleTimeout) {
            $this->destroy();
            $this->started = @session_start();
        }
        $_SESSION['_mdv_last_activity'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
            session_destroy();
        }
    }

    // ── Flash messages ─────────────────────────────────────────────────────

    public function flash(string $type, string $message): void
    {
        $_SESSION['_mdv_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public function pullFlashes(): array
    {
        $flashes = $_SESSION['_mdv_flash'] ?? [];
        unset($_SESSION['_mdv_flash']);

        return is_array($flashes) ? array_values($flashes) : [];
    }
}
