<?php

/**
 * MD.Scribe 3.0 — self-update service.
 *
 * Modernised v2 updater: instead of crawling dozens of raw files, it asks the
 * GitHub API for the latest release, compares versions, and on apply downloads
 * the **release archive** once (the asset that ships vendor/), refuses unsafe
 * paths, verifies every file against the archive's own manifest (sha256), backs
 * the current installation up to storage/backups/app-<timestamp>/ and swaps only
 * the files that changed. plan() is the same path without the writes.
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
     * @return array{ok: bool, current: string, latest?: string, newer?: bool, notes?: string, url?: string, checked_at?: string, archive?: string, installable?: bool, error?: string}
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

        // The updater installs the release **archive**, so report whether one is
        // attached: a release without it cannot be applied, and the panel says so
        // instead of offering a button that fails at the last step.
        $archive = '';
        foreach ((array) ($result['data']['assets'] ?? []) as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $name = (string) ($asset['name'] ?? '');
            if (preg_match('/\.zip$/i', $name) !== 1) {
                continue;
            }
            $archive = $name;
            if (str_starts_with($name, 'MD.Scribe-')) {
                break;
            }
        }

        $payload = [
            'ok' => true,
            'current' => $current,
            'latest' => $tag,
            'newer' => $newer,
            'notes' => mb_substr($notes, 0, 20000),
            'url' => $url,
            'checked_at' => date('c'),
            'archive' => $archive,
            'installable' => $archive !== '',
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
     * Apply an update: download the **release archive**, verify every file
     * against the manifest embedded in it, back up, then replace what changed.
     *
     * The archive must be the asset attached to the GitHub release
     * (`MD.Scribe-<version>.zip`), not a branch tarball: the release ships
     * `vendor/`, a branch tarball never does, and the manifest lists vendor —
     * so a branch download fails its own integrity check by construction.
     *
     * @return array{ok: bool, error?: string, from?: string, to?: string, replaced?: int, unchanged?: int, backup?: string|null}
     */
    public function apply(string $actor = 'admin'): array
    {
        if (!$this->config->bool('ALLOW_UPDATE')) {
            return ['ok' => false, 'error' => 'ALLOW_UPDATE is false in .md.ini — updates are disabled.'];
        }
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'error' => 'The PHP zip extension is required to apply updates.'];
        }

        $tmpDir = Paths::state() . '/update-' . bin2hex(random_bytes(4));
        if (!@mkdir($tmpDir, 0o755, true)) {
            return ['ok' => false, 'error' => 'Cannot create a temporary directory.'];
        }

        try {
            // 1-3. Download the release archive, refuse unsafe paths, and verify
            // every file in it against the manifest embedded in it.
            $release = $this->openRelease($tmpDir);
            if (($release['ok'] ?? false) !== true) {
                return ['ok' => false, 'error' => (string) ($release['error'] ?? 'release unavailable')];
            }
            $sourceRoot = (string) $release['sourceRoot'];
            $remoteManifest = $release['manifest'];
            $remoteVersion = (string) ($remoteManifest['version'] ?? 'unknown');

            $toReplace = [];
            $unchanged = 0;
            foreach ((array) ($remoteManifest['files'] ?? []) as $entry) {
                $rel = (string) ($entry['path'] ?? '');
                $sha = (string) ($entry['sha256'] ?? '');
                if ($rel === '' || $sha === '') {
                    continue;
                }
                $local = Paths::root() . '/' . $rel;
                if (is_file($local) && hash_file('sha256', $local) === $sha) {
                    $unchanged++;
                    continue;
                }
                $toReplace[$rel] = $sourceRoot . '/' . $rel;
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

    /**
     * Dry run: what would an update replace, judged against the release archive
     * itself rather than against the local manifest.
     *
     * Downloads the asset, verifies every entry inside it against the manifest
     * embedded in it, and compares hashes with the installed tree. **Writes
     * nothing** to the project: the archive lives in a temporary directory under
     * storage/state and is removed before returning.
     *
     * @return array{ok: bool, error?: string, from?: string, to?: string, asset?: string, verified?: int, replace?: list<string>, unchanged?: int, conflicts?: int}
     */
    public function plan(): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'error' => 'The PHP zip extension is required to inspect a release.'];
        }

        $tmpDir = Paths::state() . '/plan-' . bin2hex(random_bytes(4));
        if (!@mkdir($tmpDir, 0o755, true)) {
            return ['ok' => false, 'error' => 'Cannot create a temporary directory.'];
        }

        try {
            $release = $this->openRelease($tmpDir);
            if (($release['ok'] ?? false) !== true) {
                return ['ok' => false, 'error' => (string) ($release['error'] ?? 'release unavailable')];
            }

            $sourceRoot = (string) $release['sourceRoot'];
            $manifest = $release['manifest'];
            $replace = [];
            $unchanged = 0;
            $conflicts = 0;
            $verified = 0;

            foreach ((array) ($manifest['files'] ?? []) as $entry) {
                $rel = (string) ($entry['path'] ?? '');
                $sha = (string) ($entry['sha256'] ?? '');
                if ($rel === '' || $sha === '' || preg_match('#^[A-Za-z0-9._/\-]+$#', $rel) !== 1) {
                    continue;
                }
                $verified++;
                $local = Paths::root() . '/' . $rel;
                if (is_file($local) && hash_file('sha256', $local) === $sha) {
                    $unchanged++;
                    continue;
                }
                if (is_file($local)) {
                    $conflicts++; // present but different — would be overwritten
                }
                $replace[] = $rel;
            }

            return [
                'ok' => true,
                'from' => Version::VERSION,
                'to' => (string) ($manifest['version'] ?? 'unknown'),
                'asset' => (string) $release['asset'],
                'verified' => $verified,
                'replace' => $replace,
                'unchanged' => $unchanged,
                'conflicts' => $conflicts,
            ];
        } finally {
            $this->deleteTree($tmpDir);
        }
    }

    /**
     * Download the latest release archive and verify it against its own manifest.
     *
     * @return array{ok: bool, error?: string, asset?: string, sourceRoot?: string, manifest?: array<string, mixed>}
     */
    private function openRelease(string $tmpDir): array
    {
        // 1. Resolve the release asset (the archive that carries vendor/).
        $release = $this->http()->getJson(Version::API_BASE . '/releases/latest', [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ]);
        if ($release['error'] !== null || !is_array($release['data'])) {
            return ['ok' => false, 'error' => 'Cannot read the latest release: ' . ($release['error'] ?? 'HTTP ' . $release['status'])];
        }

        $assetUrl = '';
        $assetName = '';
        foreach ((array) ($release['data']['assets'] ?? []) as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $name = (string) ($asset['name'] ?? '');
            $url = (string) ($asset['browser_download_url'] ?? '');
            if ($url === '' || preg_match('/\.zip$/i', $name) !== 1) {
                continue;
            }
            $assetUrl = $url;
            $assetName = $name;
            if (str_starts_with($name, 'MD.Scribe-')) {
                break; // prefer the product archive over any extra zip
            }
        }
        if ($assetUrl === '') {
            return [
                'ok' => false,
                'error' => 'The latest release has no .zip archive attached. The updater needs MD.Scribe-<version>.zip '
                    . '(see docs/development.md, release checklist).',
            ];
        }

        // 2. Download it.
        $zipPath = $tmpDir . '/release.zip';
        $download = $this->http()->get($assetUrl, null, ['Accept: application/octet-stream']);
        if ($download['error'] !== null || $download['status'] !== 200 || $download['body'] === '') {
            return ['ok' => false, 'error' => 'Download failed: ' . ($download['error'] ?? 'HTTP ' . $download['status'])];
        }
        if (strlen($download['body']) > 200 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Release archive is implausibly large; refused.'];
        }
        file_put_contents($zipPath, $download['body']);

        // 3. Extract, refusing any entry that could escape the target.
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'Cannot open ' . $assetName . ' as a ZIP archive.'];
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || preg_match('/[\x00-\x1F]/', $name) === 1) {
                $zip->close();

                return ['ok' => false, 'error' => 'Archive contains an unsafe path: ' . $name];
            }
        }
        if (!$zip->extractTo($tmpDir)) {
            $zip->close();

            return ['ok' => false, 'error' => 'Cannot extract ' . $assetName . '.'];
        }
        $zip->close();

        // A flat archive is the release convention; a single wrapper directory
        // is tolerated for archives built by hand.
        $sourceRoot = $tmpDir;
        if (!is_file($sourceRoot . '/config/manifest.json')) {
            $inner = array_values(array_filter(
                glob($tmpDir . '/*', GLOB_ONLYDIR) ?: [],
                static fn (string $dir): bool => is_file($dir . '/config/manifest.json'),
            ));
            $sourceRoot = $inner[0] ?? '';
        }
        if ($sourceRoot === '' || !is_file($sourceRoot . '/config/manifest.json')) {
            return ['ok' => false, 'error' => 'Archive does not look like a valid release (config/manifest.json missing).'];
        }

        // 4. The archive's own manifest, and every entry verified against it.
        $remoteManifestRaw = (string) @file_get_contents($sourceRoot . '/config/manifest.json');
        if (!json_validate($remoteManifestRaw)) {
            return ['ok' => false, 'error' => 'Remote manifest is corrupt.'];
        }
        $manifest = json_decode($remoteManifestRaw, true);
        if (!is_array($manifest)) {
            return ['ok' => false, 'error' => 'Remote manifest is corrupt.'];
        }
        foreach ((array) ($manifest['files'] ?? []) as $entry) {
            $rel = (string) ($entry['path'] ?? '');
            $sha = (string) ($entry['sha256'] ?? '');
            if ($rel === '' || $sha === '' || preg_match('#^[A-Za-z0-9._/\-]+$#', $rel) !== 1) {
                continue;
            }
            $candidate = $sourceRoot . '/' . $rel;
            if (!is_file($candidate) || hash_file('sha256', $candidate) !== $sha) {
                return ['ok' => false, 'error' => 'Integrity check failed for ' . $rel . '; update refused.'];
            }
        }

        return ['ok' => true, 'asset' => $assetName, 'sourceRoot' => $sourceRoot, 'manifest' => $manifest];
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
