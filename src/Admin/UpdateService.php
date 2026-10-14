<?php

/**
 * MD.Viewer 3.0 — self-update service.
 *
 * Modernised v2 updater: instead of crawling dozens of raw files, it asks the
 * GitHub API for the latest release, compares versions, and (on apply)
 * downloads the release tarball once, verifies every file against the
 * tarball's own manifest (sha256), backs the current installation up to
 * storage/backups/app-<version>/ and swaps only the files that changed.
 *
 * The machine API keeps the v2 verbs: status (local state, no network),
 * conflicts (dry-run diff report), install (apply).
 */

declare(strict_types=1);

namespace MdViewer\Admin;

use MdViewer\Core\AuditLog;
use MdViewer\Core\Config;
use MdViewer\Core\HttpClient;
use MdViewer\Core\Logger;
use MdViewer\Core\Paths;
use MdViewer\Core\Version;

final class UpdateService
{
    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger = new Logger(),
        private readonly ?AuditLog $audit = null,
        private readonly ?HttpClient $http = null,
    ) {
    }

    private function http(): HttpClient
    {
        return $this->http ?? new HttpClient();
    }

    private function stateFile(): string
    {
        return Paths::state() . '/update-check.json';
    }

    /** Local manifest shipped with this build (config/manifest.json). */
    public function manifest(): array
    {
        $file = Paths::config() . '/manifest.json';
        if (!is_file($file)) {
            return ['version' => Version::VERSION, 'files' => []];
        }
        $raw = (string) @file_get_contents($file);
        $data = json_validate($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : ['version' => Version::VERSION, 'files' => []];
    }

    /**
     * Check GitHub for the latest release. Cached for 15 minutes unless forced.
     *
     * @return array{ok: bool, current: string, latest?: string, newer?: bool, notes?: string, url?: string, checked_at?: string, error?: string}
     */
    public function check(bool $force = false): array
    {
        $current = Version::VERSION;
        $cached = $this->cachedCheck();
        if (!$force && $cached !== null) {
            return $cached;
        }

        $result = $this->http()->getJson(Version::API_BASE . '/releases/latest', [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ]);

        if ($result['error'] !== null || !is_array($result['data'])) {
            // Unauthenticated rate limit or offline — fall back to the raw README badge line.
            return [
                'ok' => false,
                'current' => $current,
                'error' => $result['error'] ?? 'GitHub API unavailable (HTTP ' . $result['status'] . ')',
            ];
        }

        $tag = ltrim((string) ($result['data']['tag_name'] ?? ''), 'vV');
        $notes = (string) ($result['data']['body'] ?? '');
        $url = (string) ($result['data']['html_url'] ?? Version::REPO . '/releases');
        $newer = $tag !== '' && Version::compare($tag, $current) > 0;

        $payload = [
            'ok' => true,
            'current' => $current,
            'latest' => $tag,
            'newer' => $newer,
            'notes' => mb_substr($notes, 0, 20000),
            'url' => $url,
            'checked_at' => date('c'),
        ];
        $this->cacheCheck($payload);

        return $payload;
    }

    private function cachedCheck(): ?array
    {
        $file = $this->stateFile();
        if (!is_file($file)) {
            return null;
        }
        $raw = (string) @file_get_contents($file);
        if (!json_validate($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['checked_at'])) {
            return null;
        }
        $age = time() - (int) strtotime((string) $data['checked_at']);

        return $age >= 0 && $age < 900 ? $data + ['from_cache' => true] : null;
    }

    private function cacheCheck(array $payload): void
    {
        @file_put_contents($this->stateFile(), (string) json_encode($payload), LOCK_EX);
    }

    /**
     * Local install state per tracked file (no network) — machine API "status".
     *
     * @return list<array{path: string, status: string, version: string}>
     */
    public function status(): array
    {
        $out = [];
        foreach ($this->manifest()['files'] ?? [] as $entry) {
            $path = (string) ($entry['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $full = Paths::root() . '/' . $path;
            $present = is_file($full);
            $matches = $present && hash_file('sha256', $full) === ($entry['sha256'] ?? '');
            $out[] = [
                'path' => $path,
                'status' => !$present ? 'missing' : ($matches ? 'present' : 'modified'),
                'version' => (string) ($entry['version'] ?? Version::VERSION),
            ];
        }

        return $out;
    }

    /**
     * Apply an update: download the main-branch tarball, verify against its
     * embedded manifest, back up, then replace changed files.
     *
     * @return array{ok: bool, error?: string, from?: string, to?: string, replaced?: int, unchanged?: int, backup?: string|null}
     */
    public function apply(string $actor = 'admin'): array
    {
        if (!$this->config->bool('ALLOW_UPDATE')) {
            return ['ok' => false, 'error' => 'ALLOW_UPDATE is false in .md.ini — updates are disabled.'];
        }
        if (!class_exists(\PharData::class)) {
            return ['ok' => false, 'error' => 'The PHP phar extension is required to apply updates.'];
        }

        $tmpDir = Paths::state() . '/update-' . bin2hex(random_bytes(4));
        if (!@mkdir($tmpDir, 0o755, true)) {
            return ['ok' => false, 'error' => 'Cannot create a temporary directory.'];
        }

        try {
            // 1. Download the release tarball.
            $tarPath = $tmpDir . '/release.tar.gz';
            $download = $this->http()->get(Version::REPO . '/archive/refs/heads/main.tar.gz');
            if ($download['error'] !== null || $download['status'] !== 200 || $download['body'] === '') {
                return ['ok' => false, 'error' => 'Download failed: ' . ($download['error'] ?? 'HTTP ' . $download['status'])];
            }
            if (strlen($download['body']) > 200 * 1024 * 1024) {
                return ['ok' => false, 'error' => 'Release archive is implausibly large; refused.'];
            }
            file_put_contents($tarPath, $download['body']);

            // 2. Extract.
            $phar = new \PharData($tarPath);
            $phar->decompress();
            $phar->extractTo($tmpDir, null, true);
            $inner = glob($tmpDir . '/MD.Viewer-*', GLOB_ONLYDIR) ?: [];
            $sourceRoot = $inner[0] ?? null;
            if ($sourceRoot === null || !is_file($sourceRoot . '/config/manifest.json')) {
                return ['ok' => false, 'error' => 'Archive does not look like an MD.Viewer release (manifest missing).'];
            }

            // 3. Verify + diff against the remote manifest.
            $remoteManifestRaw = (string) @file_get_contents($sourceRoot . '/config/manifest.json');
            if (!json_validate($remoteManifestRaw)) {
                return ['ok' => false, 'error' => 'Remote manifest is corrupt.'];
            }
            $remoteManifest = json_decode($remoteManifestRaw, true);
            $remoteVersion = (string) ($remoteManifest['version'] ?? 'unknown');

            $toReplace = [];
            $unchanged = 0;
            foreach ((array) ($remoteManifest['files'] ?? []) as $entry) {
                $rel = (string) ($entry['path'] ?? '');
                $sha = (string) ($entry['sha256'] ?? '');
                if ($rel === '' || $sha === '' || !preg_match('#^[A-Za-z0-9._/\-]+$#', $rel)) {
                    continue;
                }
                $candidate = $sourceRoot . '/' . $rel;
                if (!is_file($candidate) || hash_file('sha256', $candidate) !== $sha) {
                    // Archive does not match its own manifest — refuse entirely.
                    return ['ok' => false, 'error' => 'Integrity check failed for ' . $rel . '; update refused.'];
                }
                $local = Paths::root() . '/' . $rel;
                if (is_file($local) && hash_file('sha256', $local) === $sha) {
                    $unchanged++;
                    continue;
                }
                $toReplace[$rel] = $candidate;
            }

            if ($toReplace === []) {
                return ['ok' => true, 'from' => Version::VERSION, 'to' => $remoteVersion, 'replaced' => 0, 'unchanged' => $unchanged, 'backup' => null];
            }

            // 4. Back up the files we are about to replace.
            $backupDir = Paths::backups() . '/app-' . date('Ymd-His');
            @mkdir($backupDir, 0o755, true);
            foreach (array_keys($toReplace) as $rel) {
                $local = Paths::root() . '/' . $rel;
                if (is_file($local)) {
                    $dest = $backupDir . '/' . $rel;
                    @mkdir(dirname($dest), 0o755, true);
                    @copy($local, $dest);
                }
            }
            @file_put_contents($backupDir . '/backup.json', (string) json_encode([
                'id' => basename($backupDir),
                'kind' => 'app',
                'from_version' => Version::VERSION,
                'to_version' => $remoteVersion,
                'files' => array_keys($toReplace),
                'created' => date('c'),
            ], JSON_PRETTY_PRINT));

            // 5. Replace.
            $replaced = 0;
            foreach ($toReplace as $rel => $candidate) {
                $dest = Paths::root() . '/' . $rel;
                @mkdir(dirname($dest), 0o755, true);
                if (@copy($candidate, $dest)) {
                    @chmod($dest, 0o644);
                    $replaced++;
                }
            }

            $this->audit?->record('update.apply', $actor, [
                'from' => Version::VERSION, 'to' => $remoteVersion,
                'replaced' => $replaced, 'backup' => basename($backupDir),
            ]);
            $this->logger->info('update applied', ['to' => $remoteVersion, 'replaced' => $replaced]);

            return [
                'ok' => true,
                'from' => Version::VERSION,
                'to' => $remoteVersion,
                'replaced' => $replaced,
                'unchanged' => $unchanged,
                'backup' => basename($backupDir),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('update failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            $this->deleteTree($tmpDir);
        }
    }

    /** Restore application files from an app-* backup. */
    public function restoreApp(string $backupId, string $actor = 'admin'): array
    {
        if (!$this->config->bool('ALLOW_RESTORE')) {
            return ['ok' => false, 'error' => 'ALLOW_RESTORE is false in .md.ini.'];
        }
        if (preg_match('/^app-\d{8}-\d{6}$/', $backupId) !== 1) {
            return ['ok' => false, 'error' => 'Invalid backup id.'];
        }
        $dir = Paths::backups() . '/' . $backupId;
        if (!is_dir($dir)) {
            return ['ok' => false, 'error' => 'Backup not found.'];
        }
        $restored = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $rel = substr($item->getPathname(), strlen($dir) + 1);
            if ($rel === 'backup.json' || !str_starts_with($rel, 'app-') === false) {
                continue;
            }
            if ($item->isFile() && preg_match('#^[A-Za-z0-9._/\-]+$#', $rel) === 1) {
                $dest = Paths::root() . '/' . $rel;
                @mkdir(dirname($dest), 0o755, true);
                if (@copy($item->getPathname(), $dest)) {
                    $restored++;
                }
            }
        }
        $this->audit?->record('update.restore', $actor, ['backup' => $backupId, 'files' => $restored]);

        return ['ok' => true, 'files' => $restored];
    }

    /** @return list<array{id: string, created: string, files: int, from: string, to: string}> */
    public function appBackups(): array
    {
        $out = [];
        foreach ((array) glob(Paths::backups() . '/app-*', GLOB_ONLYDIR) as $dir) {
            if (!is_string($dir) || !is_file($dir . '/backup.json')) {
                continue;
            }
            $meta = json_decode((string) @file_get_contents($dir . '/backup.json'), true);
            if (is_array($meta)) {
                $out[] = [
                    'id' => basename($dir),
                    'created' => (string) ($meta['created'] ?? ''),
                    'files' => count((array) ($meta['files'] ?? [])),
                    'from' => (string) ($meta['from_version'] ?? ''),
                    'to' => (string) ($meta['to_version'] ?? ''),
                ];
            }
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['id'], $a['id']));

        return $out;
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
