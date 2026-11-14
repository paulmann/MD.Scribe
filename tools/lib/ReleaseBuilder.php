<?php

/**
 * MD.Scribe — release artifact builder.
 *
 * The release checklist used to be nine manual steps, and both ways of getting it
 * wrong happened for real: an archive assembled from a git snapshot without
 * `vendor/` (every page served a fatal once deployed), and an archive wrapped in
 * `MD.Scribe-<version>/` while the previous release was flat (the deploy refused it,
 * correctly, but only after the tag was published).
 *
 * This class does the whole sequence and verifies its own result before writing an
 * archive: clean clone at the release commit, runtime-only Composer install, the
 * manifest regenerated inside that clone, and every manifest entry re-checked
 * against the file it describes. The archive is then built from exactly the files
 * that check passed, flat, with forward slashes.
 *
 * The verification half is static and side-effect free so it can be tested without
 * git or Composer: see tests/Unit/ReleaseBuilderTest.php.
 */

declare(strict_types=1);

namespace MdViewer\Tools;

final class ReleaseBuilder
{
    /** Directories that never travel in a release archive. */
    public const array NEVER_SHIPPED = [
        '.git',      // history
        '.work',     // scratch
        '.wiki',     // wiki clone
        '.zwork',    // agent artefacts
        '.ragraf',   // code index — 1 GB on a working machine, gitignored
        'node_modules',
        '.phpunit.cache',
        '.idea',
        '.vscode',
    ];

    /** Files the manifest excludes by design, so they are expected next to it. */
    public const array OUTSIDE_MANIFEST = ['config/manifest.json', '.md.ini', '.DS_Store'];

    /** Prefixes the manifest excludes by design. */
    public const array OUTSIDE_MANIFEST_PREFIXES = ['content/', 'storage/', 'tests/', '.git/', 'node_modules/'];

    public function __construct(private readonly string $repository)
    {
    }

    /** @return list<string> relative, slash-separated, sorted */
    public static function collectShippedFiles(string $tree): array
    {
        $tree = rtrim(str_replace('\\', '/', $tree), '/');
        $files = [];
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tree, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($tree) + 1));
            if (self::ships($rel)) {
                $files[] = $rel;
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    /** Whether a relative path belongs in the archive. */
    public static function ships(string $rel): bool
    {
        $parts = explode('/', $rel);
        $top = $parts[0] ?? '';
        if (in_array($top, self::NEVER_SHIPPED, true)) {
            return false;
        }
        // storage/ carries runtime data; only its access rule is part of a release.
        if ($top === 'storage' && $rel !== 'storage/.htaccess') {
            return false;
        }
        if (in_array($rel, ['.md.ini', '.DS_Store'], true) || str_ends_with($rel, '/.DS_Store')) {
            return false;
        }
        if (count($parts) > 2 && $parts[0] === 'tools' && $parts[1] === 'frontend' && $parts[2] === 'node_modules') {
            return false;
        }

        return true;
    }

    /** The version the code declares, read from src/Core/Version.php. */
    public static function versionFromSource(string $tree): ?string
    {
        $file = rtrim($tree, '/\\') . '/src/Core/Version.php';
        if (!is_file($file)) {
            return null;
        }
        $source = (string) file_get_contents($file);
        if (preg_match("/public const string VERSION = '([0-9]+\\.[0-9]+\\.[0-9]+)';/", $source, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    public static function codenameFromSource(string $tree): ?string
    {
        $file = rtrim($tree, '/\\') . '/src/Core/Version.php';
        if (!is_file($file)) {
            return null;
        }
        $source = (string) file_get_contents($file);
        if (preg_match("/public const string CODENAME = '([^']+)';/", $source, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /** @return array<string, mixed>|null */
    public static function readManifest(string $tree): ?array
    {
        $file = rtrim($tree, '/\\') . '/config/manifest.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = (string) file_get_contents($file);
        if (!json_validate($raw)) {
            return null;
        }
        $manifest = json_decode($raw, true);

        return is_array($manifest) && is_array($manifest['files'] ?? null) ? $manifest : null;
    }

    /**
     * Every manifest entry against the file it describes, plus a report of files
     * that the manifest excludes by design and files it should have listed.
     *
     * @param array<string, mixed> $manifest
     * @return array{ok: bool, checked: int, problems: list<string>, outside: array<string, int>, unexplained: list<string>}
     */
    public static function verifyAgainstManifest(string $tree, array $manifest): array
    {
        $tree = rtrim(str_replace('\\', '/', $tree), '/');
        $problems = [];
        $checked = 0;
        $known = [];
        foreach ((array) ($manifest['files'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $rel = (string) ($entry['path'] ?? '');
            $sha = (string) ($entry['sha256'] ?? '');
            if ($rel === '' || $sha === '') {
                continue;
            }
            $known[$rel] = true;
            $checked++;
            $full = $tree . '/' . $rel;
            if (!is_file($full)) {
                $problems[] = 'missing: ' . $rel;
                continue;
            }
            if (!hash_equals($sha, (string) hash_file('sha256', $full))) {
                $problems[] = 'differs from the manifest: ' . $rel;
            }
        }

        $outside = [];
        $unexplained = [];
        foreach (self::collectShippedFiles($tree) as $rel) {
            if (isset($known[$rel])) {
                continue;
            }
            $reason = self::exclusionReason($rel);
            if ($reason === null) {
                $unexplained[] = $rel;
                continue;
            }
            $outside[$reason] = ($outside[$reason] ?? 0) + 1;
        }
        foreach ($unexplained as $rel) {
            $problems[] = 'not in the manifest and not excluded by design: ' . $rel;
        }

        return [
            'ok' => $problems === [],
            'checked' => $checked,
            'problems' => $problems,
            'outside' => $outside,
            'unexplained' => $unexplained,
        ];
    }

    /**
     * Whether the manifest describes this file.
     *
     * Not the same question as `ships()`: `content/` seeds and `tests/` travel in the
     * archive (a fresh install gets a few pages to look at), but the manifest must not
     * list them or an update would try to replace the owner's documents. Conflating the
     * two questions is what put 1 GB of `.ragraf/` into a working-tree manifest.
     */
    public static function inManifest(string $rel): bool
    {
        return self::ships($rel) && self::exclusionReason($rel) === null;
    }

    /** Why a shipped file is absent from the manifest, or null when it is not excluded. */
    public static function exclusionReason(string $rel): ?string
    {
        if (in_array($rel, self::OUTSIDE_MANIFEST, true)) {
            return $rel;
        }
        foreach (self::OUTSIDE_MANIFEST_PREFIXES as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                return rtrim($prefix, '/') . '/';
            }
        }

        return null;
    }

    /**
     * Build the archive. Files are added in sorted order with their own mtime, so
     * two runs from the same commit produce the same sha256.
     *
     * @param list<string> $files relative paths, already verified
     * @return array{path: string, bytes: int, sha256: string, entries: int}
     */
    public static function writeArchive(string $tree, array $files, string $target): array
    {
        $tree = rtrim($tree, '/\\');
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new \RuntimeException('cannot create the output directory: ' . $directory);
        }
        $zip = new \ZipArchive();
        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('cannot create ' . $target);
        }
        foreach ($files as $rel) {
            if (!$zip->addFile($tree . '/' . $rel, $rel)) {
                $zip->close();
                throw new \RuntimeException('cannot add ' . $rel . ' to the archive');
            }
        }
        if (!$zip->close()) {
            throw new \RuntimeException('cannot finalise ' . $target);
        }

        return [
            'path' => $target,
            'bytes' => (int) filesize($target),
            'sha256' => (string) hash_file('sha256', $target),
            'entries' => count($files),
        ];
    }

    public function isWorkingTreeClean(): bool
    {
        $output = [];
        $code = 0;
        exec('git -C ' . escapeshellarg($this->repository) . ' status --porcelain 2>&1', $output, $code);

        return $code === 0 && $output === [];
    }

    public function headCommit(): ?string
    {
        $output = [];
        $code = 0;
        exec('git -C ' . escapeshellarg($this->repository) . ' rev-parse HEAD 2>&1', $output, $code);
        $sha = trim($output[0] ?? '');

        return $code === 0 && preg_match('/^[0-9a-f]{40}$/', $sha) === 1 ? $sha : null;
    }

    /**
     * A clean clone of this repository at the given commit.
     *
     * `--no-hardlinks` matters: a hardlinked clone shares object files with the
     * working copy, so a later `git gc` in either tree touches the other one.
     */
    public function cloneAt(string $commit, string $target): void
    {
        self::run(['git', 'clone', '--quiet', '--no-hardlinks', $this->repository, $target]);
        self::run(['git', '-C', $target, 'checkout', '--quiet', $commit]);
    }

    /**
     * Runtime dependencies only — the archive ships vendor/, so dev tools must not
     * travel: they add weight and they are not in the manifest.
     *
     * @param list<string> $composerCommand e.g. ['php', 'C:/tools/composer/composer.phar']
     */
    public function installRuntimeDependencies(string $tree, array $composerCommand): void
    {
        self::run(array_merge($composerCommand, [
            'install',
            '--no-dev',
            '--optimize-autoloader',
            '--no-interaction',
            '--no-progress',
        ]), $tree, $this->composerEnvironment());
    }

    public function buildManifest(string $tree, ?int $epoch = null): void
    {
        $environment = $epoch === null ? [] : ['SOURCE_DATE_EPOCH' => (string) $epoch];
        self::run([PHP_BINARY, 'tools/build-manifest.php'], $tree, $environment);
    }

    /**
     * Set every mtime in the tree to one timestamp.
     *
     * A zip records each file's mtime, and a fresh clone stamps files with the
     * moment of the clone — so two builds of the same commit produced different
     * bytes and a different sha256 (measured: same size, different hash). Stamping
     * the tree with the commit time makes the archive a function of the commit: the
     * same input produces the same sha256, which is what makes a published digest
     * meaningful.
     */
    public function stampTree(string $tree, int $timestamp): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tree, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            @touch($item->getPathname(), $timestamp);
        }
        @touch($tree, $timestamp);
    }

    /** Commit time in seconds, or null when the commit is unknown. */
    public function commitTimestamp(string $commit): ?int
    {
        $output = [];
        $code = 0;
        exec('git -C ' . escapeshellarg($this->repository) . ' log -1 --format=%ct ' . escapeshellarg($commit) . ' 2>&1', $output, $code);
        $value = trim($output[0] ?? '');
        if ($code !== 0 || preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Composer needs a writable home. On a host where APPDATA is unset (every
     * agent session) it aborts unless COMPOSER_HOME points somewhere real.
     *
     * @return array<string, string>
     */
    private function composerEnvironment(): array
    {
        $environment = [];
        $home = getenv('COMPOSER_HOME');
        if ($home === false || $home === '') {
            $appData = getenv('APPDATA');
            if ($appData === false || $appData === '') {
                $environment['COMPOSER_HOME'] = rtrim(sys_get_temp_dir(), '/\\') . '/composer-home';
            }
        }
        if (($environment['COMPOSER_HOME'] ?? '') !== '' && !is_dir($environment['COMPOSER_HOME'])) {
            @mkdir($environment['COMPOSER_HOME'], 0o777, true);
        }

        return $environment;
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    public static function run(array $command, ?string $cwd = null, array $environment = []): string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $cwd, $environment === [] ? null : array_merge(self::currentEnvironment(), $environment));
        if (!is_resource($process)) {
            throw new \RuntimeException('cannot run: ' . implode(' ', $command));
        }
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            throw new \RuntimeException(
                'command failed (' . $code . '): ' . implode(' ', $command)
                . ($stderr !== '' ? "\n" . trim($stderr) : '')
                . ($stdout !== '' ? "\n" . trim($stdout) : ''),
            );
        }

        return $stdout;
    }

    /** @return array<string, string> */
    private static function currentEnvironment(): array
    {
        $environment = [];
        foreach (getenv() as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }

    /** Find a runnable Composer: an explicit path, then PATH, then a local phar. */
    public static function resolveComposer(?string $explicit): array
    {
        if ($explicit !== null && $explicit !== '') {
            if (!is_file($explicit)) {
                throw new \RuntimeException('no such Composer: ' . $explicit);
            }

            return str_ends_with(strtolower($explicit), '.phar') ? [PHP_BINARY, $explicit] : [$explicit];
        }

        $probe = DIRECTORY_SEPARATOR === '\\'
            ? ['where', 'composer']
            : ['sh', '-c', 'command -v composer'];
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($probe, $descriptors, $pipes);
        if (is_resource($process)) {
            $found = trim((string) stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            if ($found !== '') {
                return [strtok($found, "\r\n") ?: 'composer'];
            }
        }

        foreach (['C:/tools/composer/composer.phar', '/usr/local/bin/composer', 'composer.phar'] as $candidate) {
            if (is_file($candidate)) {
                return [PHP_BINARY, $candidate];
            }
        }

        throw new \RuntimeException(
            'Composer not found: pass --composer=/path/to/composer(.phar), or put it on PATH',
        );
    }
}
