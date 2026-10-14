<?php

/**
 * MD.Viewer 3.0 — health & readiness checks (admin dashboard + /health JSON).
 */

declare(strict_types=1);

namespace MdViewer\Admin;

use MdViewer\Core\Config;
use MdViewer\Core\Paths;
use MdViewer\Core\Version;

final class HealthCheck
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{ok: bool, version: string, checks: list<array{id: string, label: string, status: 'ok'|'warn'|'fail', detail: string}>}
     */
    public function run(): array
    {
        $checks = [
            $this->phpVersion(),
            ...$this->extensions(),
            ...$this->writability(),
            $this->iniFile(),
            $this->adminPassword(),
            $this->apiKey(),
            $this->debugMode(),
            $this->opcache(),
            $this->contentRoot(),
            $this->securityFlags(),
        ];

        $ok = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $ok = false;
            }
        }

        return ['ok' => $ok, 'version' => Version::VERSION, 'checks' => $checks];
    }

    private function check(string $id, string $label, string $status, string $detail): array
    {
        return ['id' => $id, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }

    private function phpVersion(): array
    {
        $future = PHP_VERSION_ID >= 80500;

        return $this->check(
            'php',
            'PHP version',
            'ok',
            PHP_VERSION . ($future ? ' — PHP 8.5 ready' : ' — meets the 8.3 minimum'),
        );
    }

    /** @return list<array<string, string>> */
    private function extensions(): array
    {
        $out = [];
        $required = ['mbstring' => true, 'dom' => true, 'libxml' => true, 'json' => true];
        $recommended = ['curl' => 'updater & machine API', 'zip' => 'backup archives', 'phar' => 'applying updates', 'intl' => 'optional', 'opcache' => 'performance'];

        foreach ($required as $ext => $_) {
            $loaded = extension_loaded((string) $ext);
            $out[] = $this->check('ext-' . $ext, 'ext/' . $ext, $loaded ? 'ok' : 'fail', $loaded ? 'loaded' : 'MISSING — required');
        }
        foreach ($recommended as $ext => $why) {
            $loaded = extension_loaded((string) $ext);
            $out[] = $this->check('ext-' . $ext, 'ext/' . $ext, $loaded ? 'ok' : 'warn', $loaded ? 'loaded' : 'not loaded (' . $why . ')');
        }

        return $out;
    }

    /** @return list<array<string, string>> */
    private function writability(): array
    {
        $out = [];
        foreach ([
            'storage' => Paths::storage(),
            'cache' => Paths::cache(),
            'logs' => Paths::logs(),
            'index' => Paths::index(),
            'backups' => Paths::backups(),
        ] as $id => $dir) {
            $writable = is_dir($dir) && is_writable($dir);
            $out[] = $this->check('writable-' . $id, $id . '/ writable', $writable ? 'ok' : 'fail', $writable ? $dir : 'NOT WRITABLE: ' . $dir);
        }

        return $out;
    }

    private function iniFile(): array
    {
        $file = Paths::iniFile();
        $exists = is_file($file);
        $perms = $exists ? (int) substr((string) decoct((int) @fileperms($file)), -3) : 0;
        $status = !$exists ? 'warn' : ($perms <= 640 || $perms === 644 ? 'ok' : 'warn');

        return $this->check('ini', '.md.ini', $status, !$exists ? 'not created yet' : 'present, mode 0' . $perms . ($perms > 644 ? ' — consider chmod 640' : ''));
    }

    private function adminPassword(): array
    {
        if (!$this->config->bool('ADMIN_ENABLED')) {
            return $this->check('admin-pass', 'Admin password', 'ok', 'admin panel disabled');
        }
        $hash = $this->config->str('ADMIN_PASSWORD_HASH');
        $set = $hash !== '' && str_starts_with($hash, '$');

        return $this->check('admin-pass', 'Admin password', $set ? 'ok' : 'fail', $set ? 'set (hashed)' : 'NOT SET — open /admin.php to run first-time setup');
    }

    private function apiKey(): array
    {
        $key = $this->config->str('API_KEY');
        $ok = str_starts_with($key, 'mdv_') && strlen($key) >= 24;

        return $this->check('api-key', 'Machine API key', $ok ? 'ok' : 'warn', $ok ? 'generated' : 'missing or custom-short');
    }

    private function debugMode(): array
    {
        $debug = $this->config->bool('APP_DEBUG') || $this->config->str('APP_ENV') !== 'production';
        $displayErrors = strtolower((string) ini_get('display_errors')) === '1';

        return $this->check('debug', 'Debug posture', !$debug && !$displayErrors ? 'ok' : 'warn', $debug || $displayErrors ? 'development/debug settings active — disable in production' : 'production settings');
    }

    private function opcache(): array
    {
        $enabled = function_exists('opcache_get_status') && (@opcache_get_status(false)['opcache_enabled'] ?? false);

        return $this->check('opcache', 'OPcache', $enabled ? 'ok' : 'warn', $enabled ? 'enabled' : 'not enabled — recommended for production');
    }

    private function contentRoot(): array
    {
        $dir = $this->config->browseDir();
        $readable = is_dir($dir) && is_readable($dir);
        $count = 0;
        if ($readable) {
            $count = count(glob($dir . '/*.md') ?: []);
        }

        return $this->check('content', 'Content root', $readable ? 'ok' : 'fail', ($readable ? 'readable' : 'UNREADABLE') . ': ' . $dir . ' (' . $count . ' .md at top level)');
    }

    private function securityFlags(): array
    {
        $parts = [];
        foreach (['SECURITY_HEADERS', 'SECURITY_CSP'] as $key) {
            $parts[] = $key . '=' . ($this->config->bool($key) ? 'on' : 'OFF');
        }
        $parts[] = 'raw_html=' . ($this->config->bool('ALLOW_RAW_HTML') ? 'ON' : 'off');
        $parts[] = 'iframes=' . ($this->config->bool('ALLOW_IFRAMES') ? 'ON' : 'off');
        $risky = $this->config->bool('ALLOW_RAW_HTML') || $this->config->bool('ALLOW_IFRAMES')
            || !$this->config->bool('SECURITY_HEADERS') || !$this->config->bool('SECURITY_CSP');

        return $this->check('security', 'Security flags', $risky ? 'warn' : 'ok', implode(' ', $parts));
    }
}
