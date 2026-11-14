<?php

/**
 * MD.Scribe — release artifact verification.
 *
 * One command answers the only question that matters before a deploy: is this
 * archive the thing the manifest describes, and does the tree inside it actually
 * serve pages? It downloads the published asset (or takes a local file), checks
 * the digest the release publishes, verifies every one of the manifest entries
 * against the extracted files, boots the extracted tree on a free port and runs
 * the same six HTTP checks CI's smoke step runs, then hands the base URL to the
 * browser gate.
 *
 * Why it exists: the manual path that built 3.5.0 verified the archive with a
 * throwaway script, and the next deploy still shipped a tree with `vendor/`
 * missing — the archive was fine, the deployment step was not. This tool covers
 * the artifact; tools/deploy/mdscribe-deploy covers the placement and refuses to
 * declare success without calling this verification against the live tree.
 *
 * Usage:
 *   php tools/verify-release.php                      latest version in Version.php
 *   php tools/verify-release.php --version=3.5.0
 *   php tools/verify-release.php --source=MD.Scribe-3.5.0.zip --sha256=<hex>
 *   php tools/verify-release.php --skip-browser --keep
 *
 * Exit code 0 only when every check passed. Each check prints one line.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use MdViewer\Core\Version;

/** Paths the archive carries but the manifest never lists, by design. */
const ALLOWED_EXTRA_PREFIXES = ['content/', 'storage/', 'tests/'];
const ALLOWED_EXTRA_EXACT = ['config/manifest.json'];

final class ReleaseCheck
{
    /** @var list<array{state: string, name: string, detail: string}> */
    private array $results = [];

    public function pass(string $name, string $detail = ''): void
    {
        $this->record('PASS', $name, $detail);
    }

    public function fail(string $name, string $detail = ''): void
    {
        $this->record('FAIL', $name, $detail);
    }

    public function skip(string $name, string $detail = ''): void
    {
        $this->record('SKIP', $name, $detail);
    }

    private function record(string $state, string $name, string $detail): void
    {
        $this->results[] = ['state' => $state, 'name' => $name, 'detail' => $detail];
        $line = $state . '  ' . $name;
        if ($detail !== '') {
            $line .= ' — ' . $detail;
        }
        fwrite(STDOUT, $line . "\n");
    }

    public function failed(): int
    {
        return count(array_filter($this->results, static fn (array $r): bool => $r['state'] === 'FAIL'));
    }

    public function skipped(): int
    {
        return count(array_filter($this->results, static fn (array $r): bool => $r['state'] === 'SKIP'));
    }

    public function total(): int
    {
        return count($this->results);
    }
}

/**
 * @param list<string> $argv
 * @return array<string, string|bool>
 */
function parseOptions(array $argv): array
{
    $options = ['version' => '', 'source' => '', 'sha256' => '', 'tag' => '', 'skip-browser' => false, 'keep' => false];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--skip-browser' || $argument === '--keep') {
            $options[substr($argument, 2)] = true;
            continue;
        }
        if (preg_match('/^--(version|source|sha256|tag)=(.*)$/', $argument, $m) === 1) {
            $options[$m[1]] = $m[2];
            continue;
        }
        fwrite(STDERR, "unknown argument: {$argument}\n");
        exit(2);
    }

    return $options;
}

/** Remove a tree without following symlinks out of it. */
function removeTree(string $path): void
{
    if ($path === '' || !file_exists($path)) {
        return;
    }
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

/** @return list<string> every file under $root, relative and slash-separated */
function listFiles(string $root): array
{
    $files = [];
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        if (!$item->isFile()) {
            continue;
        }
        $files[] = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
    }
    sort($files);

    return $files;
}

/**
 * @return array{status: int, type: string, body: string, error: string}
 */
function httpGet(string $url, int $timeout = 20, bool $follow = false): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            // Release assets redirect to objects.githubusercontent.com; the 302 is
            // not an error, it is the first hop of the download.
            'follow_location' => $follow ? 1 : 0,
            'max_redirects' => $follow ? 5 : 1,
            'header' => "User-Agent: MD.Scribe-release-check\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $error = error_get_last();

        return ['status' => 0, 'type' => '', 'body' => '', 'error' => $error['message'] ?? 'request failed'];
    }
    $headers = $http_response_header ?? [];
    $status = 0;
    $type = '';
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
            $status = (int) $m[1];
        }
        if (stripos($header, 'content-type:') === 0) {
            $type = trim(substr($header, strlen('content-type:')));
        }
    }

    return ['status' => $status, 'type' => $type, 'body' => $body, 'error' => ''];
}

/** Strip parameters from a media type: "application/json; charset=UTF-8" → "application/json". */
function mediaType(string $header): string
{
    $position = strpos($header, ';');
    $type = $position === false ? $header : substr($header, 0, $position);

    return strtolower(trim($type));
}

/**
 * Ask GitHub for the asset of a release.
 *
 * @return array{ok: bool, url?: string, name?: string, size?: int, digest?: string, error?: string}
 */
function releaseAsset(string $tag): array
{
    $endpoint = Version::API_BASE . '/releases/tags/' . rawurlencode($tag);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => "Accept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\nUser-Agent: MD.Scribe-release-check\r\n",
        ],
    ]);
    $raw = @file_get_contents($endpoint, false, $context);
    if ($raw === false) {
        $error = error_get_last();

        return ['ok' => false, 'error' => 'cannot reach ' . $endpoint . ': ' . ($error['message'] ?? 'unknown')];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'release metadata is not JSON (tag ' . $tag . ' published?)'];
    }
    foreach ((array) ($data['assets'] ?? []) as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $name = (string) ($asset['name'] ?? '');
        $url = (string) ($asset['browser_download_url'] ?? '');
        if ($url === '' || preg_match('/\.zip$/i', $name) !== 1) {
            continue;
        }
        if (!str_starts_with($name, 'MD.Scribe-')) {
            continue;
        }

        return [
            'ok' => true,
            'url' => $url,
            'name' => $name,
            'size' => (int) ($asset['size'] ?? 0),
            'digest' => (string) ($asset['digest'] ?? ''),
        ];
    }

    return ['ok' => false, 'error' => 'release ' . $tag . ' has no MD.Scribe-*.zip asset'];
}

function findFreePort(): int
{
    $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($socket === false) {
        fwrite(STDERR, "cannot bind a probe socket: {$errstr} ({$errno})\n");
        exit(2);
    }
    $name = (string) stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr($name, (int) strrpos($name, ':') + 1);
    if ($port <= 0) {
        fwrite(STDERR, "cannot determine a free port from {$name}\n");
        exit(2);
    }

    return $port;
}

$options = parseOptions($argv);
$wantedVersion = is_string($options['version']) && $options['version'] !== '' ? $options['version'] : Version::VERSION;
$tag = is_string($options['tag']) && $options['tag'] !== '' ? $options['tag'] : 'v' . $wantedVersion;
$localSource = is_string($options['source']) ? $options['source'] : '';
$expectedSha = strtolower(is_string($options['sha256']) ? $options['sha256'] : '');
$skipBrowser = $options['skip-browser'] === true;
$keep = $options['keep'] === true;

$check = new ReleaseCheck();
$tempRoot = rtrim(sys_get_temp_dir(), '/\\') . '/mds-verify-' . bin2hex(random_bytes(5));
$archivePath = '';
$server = null;
$port = 0;

fwrite(STDOUT, 'repository: ' . dirname(__DIR__) . "\n");
fwrite(STDOUT, 'version:    ' . $wantedVersion . '  (tag ' . $tag . ', local Version::VERSION ' . Version::VERSION . ")\n");

try {
    // ── 1. The archive itself ───────────────────────────────────────────────
    if ($localSource !== '') {
        $archivePath = $localSource;
        if (!is_file($archivePath)) {
            $check->fail('archive', 'no such file: ' . $archivePath);
            throw new RuntimeException('missing archive');
        }
        $check->pass('archive', $localSource . ' (' . number_format((float) filesize($archivePath), 0, '.', ' ') . ' bytes, local)');
    } else {
        $asset = releaseAsset($tag);
        if ($asset['ok'] !== true) {
            $check->fail('archive', (string) ($asset['error'] ?? 'unknown error'));
            throw new RuntimeException('release lookup failed');
        }
        $archivePath = $tempRoot . '/' . $asset['name'];
        if (!mkdir($tempRoot, 0o777, true) && !is_dir($tempRoot)) {
            $check->fail('archive', 'cannot create ' . $tempRoot);
            throw new RuntimeException('temp dir');
        }
        $download = httpGet((string) $asset['url'], 300, true);
        if ($download['status'] !== 200 || $download['body'] === '') {
            $check->fail('archive', 'download failed: HTTP ' . $download['status'] . ' ' . $download['error']);
            throw new RuntimeException('download failed');
        }
        file_put_contents($archivePath, $download['body']);
        $bytes = (int) filesize($archivePath);
        if ($asset['size'] > 0 && $bytes !== $asset['size']) {
            $check->fail('archive', $asset['name'] . ' is ' . $bytes . ' bytes, the release says ' . $asset['size']);
            throw new RuntimeException('size mismatch');
        }
        $check->pass('archive', $asset['name'] . ' (' . number_format((float) $bytes, 0, '.', ' ') . ' bytes from ' . $tag . ')');
        if ($expectedSha === '' && ($asset['digest'] ?? '') !== '') {
            // GitHub publishes sha256:<hex> per asset since 2025; trust it only
            // as the expected value — the comparison below still happens locally.
            $expectedSha = strtolower(str_replace('sha256:', '', (string) $asset['digest']));
        }
        if ($expectedSha === '' && ($asset['digest'] ?? '') === '') {
            $check->fail('digest', 'the release publishes no asset digest and none was passed with --sha256');
        }
    }

    // ── 2. Digest ───────────────────────────────────────────────────────────
    $actualSha = hash_file('sha256', $archivePath);
    if ($expectedSha !== '') {
        if (preg_match('/^[0-9a-f]{64}$/', $expectedSha) !== 1) {
            $check->fail('digest', 'expected sha256 is not 64 hex characters: ' . $expectedSha);
        } elseif (!hash_equals($expectedSha, (string) $actualSha)) {
            $check->fail('digest', 'sha256 ' . substr((string) $actualSha, 0, 16) . '… but expected ' . substr($expectedSha, 0, 16) . '…');
        } else {
            $check->pass('digest', 'sha256 ' . $actualSha);
        }
    } else {
        $check->skip('digest', 'nothing to compare against (local source without --sha256)');
    }

    // ── 3. Extract, refusing anything that could escape the target ───────────
    $tree = $tempRoot . '/tree';
    if (!is_dir($tree) && !mkdir($tree, 0o777, true) && !is_dir($tree)) {
        $check->fail('extract', 'cannot create ' . $tree);
        throw new RuntimeException('tree dir');
    }
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        $check->fail('extract', 'cannot open the archive as ZIP');
        throw new RuntimeException('zip open');
    }
    $unsafe = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || preg_match('/[\x00-\x1F]/', $name) === 1) {
            $unsafe = $name;
            break;
        }
    }
    if ($unsafe !== '') {
        $zip->close();
        $check->fail('extract', 'unsafe path inside the archive: ' . $unsafe);
        throw new RuntimeException('unsafe path');
    }
    if (!$zip->extractTo($tree)) {
        $zip->close();
        $check->fail('extract', 'extraction failed');
        throw new RuntimeException('extract failed');
    }
    $zip->close();
    $check->pass('extract', 'into ' . $tree);

    // ── 4. The manifest inside the archive ──────────────────────────────────
    $manifestFile = $tree . '/config/manifest.json';
    if (!is_file($manifestFile)) {
        $check->fail('manifest', 'config/manifest.json is missing from the archive');
        throw new RuntimeException('no manifest');
    }
    $manifest = json_decode((string) file_get_contents($manifestFile), true);
    if (!is_array($manifest) || !is_array($manifest['files'] ?? null)) {
        $check->fail('manifest', 'config/manifest.json is not a manifest');
        throw new RuntimeException('bad manifest');
    }
    $manifestVersion = (string) ($manifest['version'] ?? '');
    $entries = $manifest['files'];
    if ($manifestVersion !== $wantedVersion) {
        $check->fail('manifest', 'archive says version ' . $manifestVersion . ', expected ' . $wantedVersion);
    } else {
        $check->pass('manifest', count($entries) . ' entries, version ' . $manifestVersion . ', php_min ' . (string) ($manifest['php_min'] ?? '?'));
    }
    $phpMin = (string) ($manifest['php_min'] ?? '');
    if ($phpMin !== '' && version_compare(PHP_VERSION, $phpMin, '<')) {
        $check->fail('php floor', 'this PHP is ' . PHP_VERSION . ', the release needs ' . $phpMin);
    }

    // ── 5. Every entry against its file ─────────────────────────────────────
    $known = [];
    $missing = [];
    $modified = [];
    $unsafeEntry = '';
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $path = (string) ($entry['path'] ?? '');
        $sha = (string) ($entry['sha256'] ?? '');
        if ($path === '' || preg_match('#^[A-Za-z0-9._/\-]+$#', $path) !== 1) {
            $unsafeEntry = $path;
            break;
        }
        $known[$path] = true;
        $full = $tree . '/' . $path;
        if (!is_file($full)) {
            $missing[] = $path;
            continue;
        }
        if (!hash_equals($sha, (string) hash_file('sha256', $full))) {
            $modified[] = $path;
        }
    }
    if ($unsafeEntry !== '') {
        $check->fail('manifest paths', 'unsafe entry in the manifest: ' . $unsafeEntry);
    } elseif ($missing === [] && $modified === []) {
        $check->pass('integrity', count($known) . ' entries match their sha256');
    } else {
        $check->fail(
            'integrity',
            count($missing) . ' missing, ' . count($modified) . ' modified'
            . ($missing !== [] ? ' — first missing ' . $missing[0] : '')
            . ($modified !== [] ? ' — first modified ' . $modified[0] : ''),
        );
    }

    // ── 6. Nothing unexpected ships ─────────────────────────────────────────
    $extra = [];
    foreach (listFiles($tree) as $rel) {
        if (isset($known[$rel])) {
            continue;
        }
        if (in_array($rel, ALLOWED_EXTRA_EXACT, true)) {
            continue;
        }
        foreach (ALLOWED_EXTRA_PREFIXES as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                continue 2;
            }
        }
        if (str_ends_with($rel, '/.DS_Store') || $rel === '.DS_Store' || $rel === '.md.ini') {
            continue;
        }
        $extra[] = $rel;
    }
    if ($extra === []) {
        $check->pass('no unexpected files', 'archive holds exactly the manifest plus content/, storage/, tests/');
    } else {
        $check->fail('no unexpected files', count($extra) . ' not in the manifest — first: ' . $extra[0]);
    }

    // vendor/ is the difference between an install and a broken deploy: the
    // archive must carry it, because nothing else provides autoloading.
    $vendorFiles = count(array_filter(array_keys($known), static fn (string $p): bool => str_starts_with($p, 'vendor/')));
    if (is_file($tree . '/vendor/autoload.php') && $vendorFiles > 100) {
        $check->pass('vendor', $vendorFiles . ' files, autoload.php present');
    } else {
        $check->fail('vendor', $vendorFiles . ' manifest entries; autoload.php ' . (is_file($tree . '/vendor/autoload.php') ? 'present' : 'MISSING'));
    }

    // ── 7. Serve the extracted tree and check the six routes ────────────────
    $publicDir = $tree . '/public';
    $router = $publicDir . '/router.php';
    if (!is_file($router) || !is_file($tree . '/index.php')) {
        $check->fail('serve', 'the archive has no public/router.php + index.php');
        throw new RuntimeException('not runnable');
    }
    $port = findFreePort();
    $logFile = $tempRoot . '/server.log';
    $descriptors = [1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']];
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $publicDir, $router], $descriptors, $pipes, $tree);
    if (!is_resource($process)) {
        $check->fail('serve', 'cannot start php -S');
        throw new RuntimeException('no server');
    }
    $server = $process;
    $base = 'http://127.0.0.1:' . $port;
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        usleep(150_000);
        $probe = httpGet($base . '/', 5);
        if ($probe['status'] === 200) {
            $ready = true;
            break;
        }
        $status = proc_get_status($process);
        if ($status['running'] === false) {
            break;
        }
    }
    if (!$ready) {
        $log = is_file($logFile) ? trim((string) file_get_contents($logFile)) : '';
        $check->fail('serve', 'php -S did not answer on ' . $base . ($log !== '' ? ' — ' . substr($log, 0, 200) : ''));
        throw new RuntimeException('server did not start');
    }
    $check->pass('serve', 'php -S on ' . $base . ' from the extracted tree');

    // The six routes CI's smoke step exercises, plus the manifest media type.
    $home = httpGet($base . '/');
    $check->pass('GET /', 'HTTP ' . $home['status'] . ', ' . strlen($home['body']) . ' bytes');
    // The version reaches the page twice and both are checked: the footer is
    // what a reader sees, window.MDV_CONFIG is what the scripts read.
    $footerOk = preg_match('/MD\.Scribe<\/a>\s*' . preg_quote($manifestVersion, '/') . '\b/u', $home['body']) === 1;
    $configOk = str_contains($home['body'], '"version":"' . $manifestVersion . '"');
    if ($footerOk && $configOk) {
        $check->pass('page names version', $manifestVersion . ' in the footer and in window.MDV_CONFIG');
    } else {
        $check->fail('page names version', 'footer ' . ($footerOk ? 'ok' : 'silent') . ', MDV_CONFIG ' . ($configOk ? 'ok' : 'silent') . ' (expected ' . $manifestVersion . ')');
    }

    foreach (['/?page=browser', '/?page=themes'] as $path) {
        $response = httpGet($base . $path);
        if ($response['status'] === 200 && str_contains($response['body'], '<html')) {
            $check->pass('GET ' . $path, 'HTTP 200, ' . strlen($response['body']) . ' bytes');
        } else {
            $check->fail('GET ' . $path, 'HTTP ' . $response['status'] . ', no HTML');
        }
    }

    $search = httpGet($base . '/?api=search&q=markdown');
    $payload = json_decode($search['body'], true);
    $found = is_array($payload) ? (int) ($payload['total'] ?? 0) : 0;
    if ($search['status'] === 200 && is_array($payload) && ($payload['ok'] ?? false) === true && $found > 0) {
        $check->pass('GET ?api=search', 'ok:true, ' . $found . ' results for "markdown"');
    } else {
        $check->fail('GET ?api=search', 'HTTP ' . $search['status'] . ', total ' . $found . ', body ' . substr($search['body'], 0, 120));
    }

    $missingFile = httpGet($base . '/?file=nope.md');
    if ($missingFile['status'] === 404) {
        $check->pass('GET ?file=nope.md', 'HTTP 404');
    } else {
        $check->fail('GET ?file=nope.md', 'HTTP ' . $missingFile['status'] . ', expected 404');
    }

    // The PWA manifest carries no version — it is checked for the properties an
    // install depends on: the media type, a scope/start_url inside the
    // installation, and a local icon (a CDN URL here would break offline).
    $pwa = httpGet($base . '/?api=manifest');
    $pwaPayload = json_decode($pwa['body'], true);
    $pwaType = mediaType($pwa['type']);
    if ($pwaType === 'application/manifest+json') {
        $check->pass('?api=manifest media type', $pwaType);
    } else {
        $check->fail('?api=manifest media type', 'got "' . $pwa['type'] . '"');
    }
    $scope = is_array($pwaPayload) ? (string) ($pwaPayload['scope'] ?? '') : '';
    $startUrl = is_array($pwaPayload) ? (string) ($pwaPayload['start_url'] ?? '') : '';
    $iconSrc = is_array($pwaPayload) ? (string) ($pwaPayload['icons'][0]['src'] ?? '') : '';
    $pwaProblems = [];
    if ($scope !== './' && $scope !== '') {
        $pwaProblems[] = 'scope "' . $scope . '"';
    }
    if ($startUrl === '') {
        $pwaProblems[] = 'no start_url';
    }
    if ($iconSrc === '' || preg_match('#^https?://#i', $iconSrc) === 1) {
        $pwaProblems[] = 'icon "' . $iconSrc . '" is not local';
    }
    if ($pwaProblems === []) {
        $check->pass('?api=manifest shape', 'scope ' . $scope . ', start_url ' . $startUrl . ', icon ' . $iconSrc);
    } else {
        $check->fail('?api=manifest shape', implode('; ', $pwaProblems));
    }

    // ── 8. The browser half of the pipeline ─────────────────────────────────
    if ($skipBrowser) {
        $check->skip('browser gate', '--skip-browser');
    } else {
        $gate = dirname(__DIR__) . '/tools/frontend/check-render.mjs';
        if (!is_file($gate)) {
            $check->fail('browser gate', 'tools/frontend/check-render.mjs is missing');
        } else {
            // --root points the gate at the extracted tree, so its fixture is
            // written there and never into the working copy.
            $command = ['node', $gate, '--base=' . $base, '--root=' . $tree];
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $gateProcess = proc_open($command, $descriptors, $gatePipes, dirname(__DIR__) . '/tools/frontend');
            if (!is_resource($gateProcess)) {
                $check->fail('browser gate', 'cannot run node');
            } else {
                $stdout = (string) stream_get_contents($gatePipes[1]);
                $stderr = (string) stream_get_contents($gatePipes[2]);
                fclose($gatePipes[1]);
                fclose($gatePipes[2]);
                $code = proc_close($gateProcess);
                preg_match_all('/^PASS/m', $stdout, $passMatches);
                preg_match_all('/^FAIL.*$/m', $stdout, $failMatches);
                if ($code === 0) {
                    $check->pass('browser gate', count($passMatches[0]) . ' checks passed');
                } else {
                    $detail = $failMatches[0] !== []
                        ? implode(' | ', array_slice($failMatches[0], 0, 3))
                        : (trim($stderr) !== '' ? substr(trim($stderr), 0, 240) : 'exit ' . $code);
                    $check->fail('browser gate', $detail);
                }
                if (trim($stderr) !== '' && $code === 0) {
                    $check->skip('browser gate output', substr(trim($stderr), 0, 160));
                }
            }
        }
    }
} catch (Throwable $exception) {
    if ($check->failed() === 0) {
        $check->fail('aborted', $exception->getMessage());
    }
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    if ($keep) {
        fwrite(STDOUT, 'kept:       ' . $tempRoot . "\n");
    } else {
        removeTree($tempRoot);
    }
}

fwrite(STDOUT, sprintf(
    "\nchecks: %d, failed: %d, skipped: %d\n",
    $check->total(),
    $check->failed(),
    $check->skipped(),
));

exit($check->failed() === 0 ? 0 : 1);
