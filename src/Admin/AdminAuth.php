<?php

/**
 * MD.Viewer 3.0 — admin authentication.
 *
 * Session login with password_hash verification, per-IP rate limiting
 * (15-minute sliding window, cap from ADMIN_RATE_LIMIT) and a first-run
 * setup flow: when no password hash exists yet, the panel offers a setup
 * screen that is only actionable from localhost or with the one-time setup
 * code written to storage/logs/setup.log (readable via SSH/FTP) — so a
 * stranger who finds /admin.php first cannot claim the installation.
 */

declare(strict_types=1);

namespace MdViewer\Admin;

use MdViewer\Core\AuditLog;
use MdViewer\Core\Config;
use MdViewer\Core\Logger;
use MdViewer\Core\Paths;
use MdViewer\Core\Request;
use MdViewer\Core\Session;

final class AdminAuth
{
    public const string SESSION_KEY = 'mdv_admin';
    private const int WINDOW_SECONDS = 900;

    public function __construct(
        private readonly Config $config,
        private readonly Session $session,
        private readonly Request $request,
        private readonly Logger $logger,
        private readonly AuditLog $audit,
    ) {
    }

    public function isPanelEnabled(): bool
    {
        return $this->config->bool('ADMIN_ENABLED');
    }

    public function isPasswordSet(): bool
    {
        $hash = $this->config->str('ADMIN_PASSWORD_HASH');

        return $hash !== '' && str_starts_with($hash, '$');
    }

    public function isAuthenticated(): bool
    {
        $user = $this->session->get(self::SESSION_KEY);

        return is_array($user) && isset($user['user']) && $this->isPasswordSet();
    }

    public function user(): string
    {
        $user = $this->session->get(self::SESSION_KEY);

        return is_array($user) ? (string) ($user['user'] ?? 'admin') : '';
    }

    /**
     * @return array{ok: bool, error?: string, locked?: int}
     */
    public function attempt(string $username, string $password): array
    {
        $ip = $this->request->ip($this->config->str('TRUSTED_PROXIES'));

        if (($locked = $this->lockSeconds($ip)) > 0) {
            return ['ok' => false, 'error' => 'Too many attempts.', 'locked' => $locked];
        }

        $expectedUser = $this->config->str('ADMIN_USERNAME') ?: 'admin';
        $hash = $this->config->str('ADMIN_PASSWORD_HASH');
        $userOk = hash_equals($expectedUser, $username);
        $passOk = $hash !== '' && password_verify($password, $hash);

        if ($userOk && $passOk) {
            $this->clearAttempts($ip);
            $this->session->regenerate();
            $this->session->set(self::SESSION_KEY, ['user' => $username, 'login_at' => time()]);
            $this->audit->record('admin.login', $username, ['ip' => $ip]);
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $this->config->set('ADMIN_PASSWORD_HASH', password_hash($password, PASSWORD_DEFAULT));
            }

            return ['ok' => true];
        }

        $this->recordAttempt($ip);
        $this->audit->record('admin.login_failed', $username !== '' ? $username : 'anonymous', ['ip' => $ip]);
        $this->logger->warning('admin login failed', ['user' => $username, 'ip' => $ip]);

        return ['ok' => false, 'error' => 'Invalid credentials.', 'locked' => $this->lockSeconds($ip)];
    }

    public function logout(): void
    {
        $user = $this->user();
        $this->session->remove(self::SESSION_KEY);
        $this->session->regenerate();
        $this->audit->record('admin.logout', $user !== '' ? $user : 'anonymous');
    }

    // ── First-run setup ────────────────────────────────────────────────────

    public function setupAllowed(): bool
    {
        if ($this->isPasswordSet()) {
            return false;
        }
        $ip = $this->request->ip($this->config->str('TRUSTED_PROXIES'));

        return $ip === '127.0.0.1' || $ip === '::1';
    }

    /** One-time setup code, generated once into storage/logs/setup.log. */
    public function setupCode(): string
    {
        $file = Paths::logs() . '/setup.log';
        if (is_file($file)) {
            $existing = trim((string) @file_get_contents($file));
            if (preg_match('/^[a-f0-9]{32}$/', $existing) === 1) {
                return $existing;
            }
        }
        $code = bin2hex(random_bytes(16));
        @file_put_contents($file, $code, LOCK_EX);
        @chmod($file, 0o600);

        return $code;
    }

    public function setup(string $code, string $password): bool
    {
        if ($this->isPasswordSet()) {
            return false;
        }
        // Localhost may skip the code; remote callers must present it.
        if (!$this->setupAllowed() && !hash_equals($this->setupCode(), $code)) {
            $this->audit->record('admin.setup_failed', 'anonymous', ['ip' => $this->request->ip()]);

            return false;
        }
        if (strlen($password) < 10) {
            return false;
        }
        $this->config->set('ADMIN_PASSWORD_HASH', password_hash($password, PASSWORD_DEFAULT));
        @unlink(Paths::logs() . '/setup.log');
        $this->audit->record('admin.setup', 'installer', ['ip' => $this->request->ip()]);

        return true;
    }

    // ── Rate limiting ──────────────────────────────────────────────────────

    private function stateFile(string $ip): string
    {
        return Paths::state() . '/login-' . substr(hash('sha256', $ip), 0, 16) . '.json';
    }

    private function recordAttempt(string $ip): void
    {
        $file = $this->stateFile($ip);
        $attempts = $this->attempts($ip);
        $attempts[] = time();
        @file_put_contents($file, (string) json_encode($attempts), LOCK_EX);
    }

    /** @return list<int> */
    private function attempts(string $ip): array
    {
        $file = $this->stateFile($ip);
        if (!is_file($file)) {
            return [];
        }
        $raw = (string) @file_get_contents($file);
        $data = json_validate($raw) ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            return [];
        }
        $cutoff = time() - self::WINDOW_SECONDS;

        return array_values(array_filter(array_map(intval(...), $data), static fn (int $t): bool => $t > $cutoff));
    }

    private function clearAttempts(string $ip): void
    {
        @unlink($this->stateFile($ip));
    }

    /** Seconds remaining in the lockout (0 when not locked). */
    public function lockSeconds(?string $ip = null): int
    {
        $ip ??= $this->request->ip($this->config->str('TRUSTED_PROXIES'));
        $attempts = $this->attempts($ip);
        $limit = max(3, $this->config->int('ADMIN_RATE_LIMIT'));
        if (count($attempts) < $limit) {
            return 0;
        }
        $oldest = min($attempts);

        return max(0, $oldest + self::WINDOW_SECONDS - time());
    }
}
