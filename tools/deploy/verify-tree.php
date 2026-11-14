<?php

/**
 * MD.Scribe — verify a deployed tree against its own manifest.
 *
 *   php verify-tree.php /path/to/mdscribe [--quiet-summary]
 *
 * Reads config/manifest.json from that tree and compares every entry with the
 * file on disk. Files the manifest never lists are reported as unexpected unless
 * they belong to the runtime data the application writes itself (content/,
 * storage/, .md.ini), to the test suite the archive ships but the manifest
 * excludes, or to a VCS checkout.
 *
 * Exit 0 only when nothing is missing, modified or unexpected. This is the check
 * mdscribe-deploy runs after switching and before declaring success — a deploy
 * that cannot prove its own result is not finished.
 *
 * Kept dependency-free on purpose: it must run on a host with no Composer
 * packages installed, including inside a freshly extracted archive.
 */

declare(strict_types=1);

const ALLOWED_PREFIXES = ['content/', 'storage/', 'tests/', '.git/', 'node_modules/'];
const ALLOWED_EXACT = ['config/manifest.json', '.md.ini', '.DS_Store'];

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(2);
}

$root = rtrim($argv[1] ?? '', '/');
if ($root === '' || !is_dir($root)) {
    fail('usage: php verify-tree.php /path/to/mdscribe [--quiet-summary] [--fingerprint]');
}
$quiet = in_array('--quiet-summary', $argv, true);
$fingerprintOnly = in_array('--fingerprint', $argv, true);

$manifestFile = $root . '/config/manifest.json';
if (!is_file($manifestFile)) {
    fail('no manifest at ' . $manifestFile);
}
$manifest = json_decode((string) file_get_contents($manifestFile), true);
if (!is_array($manifest) || !is_array($manifest['files'] ?? null)) {
    fail('config/manifest.json is not a manifest');
}

$entries = $manifest['files'];
$known = [];
$missing = [];
$modified = [];
$unsafe = '';
foreach ($entries as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $path = (string) ($entry['path'] ?? '');
    $sha = (string) ($entry['sha256'] ?? '');
    if ($path === '' || preg_match('#^[A-Za-z0-9._/\-]+$#', $path) !== 1) {
        $unsafe = $path;
        break;
    }
    $known[$path] = true;
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        $missing[] = $path;
        continue;
    }
    if (!hash_equals($sha, (string) hash_file('sha256', $full))) {
        $modified[] = $path;
    }
}

$extra = [];
if ($unsafe === '') {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        if (!$item->isFile()) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if (isset($known[$rel]) || in_array($rel, ALLOWED_EXACT, true)) {
            continue;
        }
        foreach (ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                continue 2;
            }
        }
        $extra[] = $rel;
    }
    sort($extra);
}

$version = (string) ($manifest['version'] ?? '?');
$total = count($known);

// A single value that names this tree. It is computed from the manifest's own
// (path, sha256) pairs, so two installations of the same release print the same
// fingerprint and any difference in a shipped file changes it. Quote it next to a
// release tag; re-measure it later to prove the tree is still that release.
$digest = hash_init('sha256');
hash_update($digest, 'mdscribe-tree-v1\n');
foreach ($entries as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    hash_update($digest, (string) ($entry['path'] ?? '') . "\0" . (string) ($entry['sha256'] ?? '') . "\n");
}
$treeFingerprint = hash_final($digest);

if ($fingerprintOnly) {
    printf("%s %d %s\n", $treeFingerprint, count($entries), $version);
    exit(0);
}

if (!$quiet) {
    printf("tree:     %s\n", $root);
    printf("manifest: %d entries, version %s\n", count($entries), $version);
}
printf("fingerprint: %s\n", $treeFingerprint);
printf("verified: %d, missing: %d, modified: %d, unexpected: %d\n", $total - count($missing) - count($modified), count($missing), count($modified), count($extra));
if ($unsafe !== '') {
    printf("UNSAFE manifest entry: %s\n", $unsafe);
}
foreach (array_slice($missing, 0, 10) as $path) {
    printf("  MISSING %s\n", $path);
}
foreach (array_slice($modified, 0, 10) as $path) {
    printf("  MODIFIED %s\n", $path);
}
foreach (array_slice($extra, 0, 10) as $path) {
    printf("  EXTRA %s\n", $path);
}

$failed = $unsafe !== '' || $missing !== [] || $modified !== [] || $extra !== [];
if ($failed) {
    printf("RESULT: FAIL\n");
    exit(1);
}
printf("RESULT: PASS\n");
exit(0);
