<?php

/**
 * MD.Scribe — build the release artifact with one command.
 *
 *   php tools/build-release.php --version=<version> --out=/tmp/release
 *   php tools/build-release.php --version=<version> --out=/tmp/release --commit=<sha> --keep-clone
 *   php tools/build-release.php --version=<version> --out=/tmp/release --dry-run
 *   php tools/build-release.php --verify-only=/tmp/mdscribe-release-xxxx
 *
 * What it does, and why each step exists:
 *
 *   1. refuses to build from a dirty working copy unless --commit is given —
 *      an archive must correspond to a commit, not to whatever was on disk;
 *   2. clones that commit (--no-hardlinks), so `git gc` on either side later
 *      cannot disturb the other;
 *   3. installs runtime dependencies only: the archive ships vendor/, and dev
 *      tools are neither in the manifest nor in a release;
 *   4. regenerates config/manifest.json *inside the clone* — the manifest must
 *      describe the tree it travels with, not the author's checkout;
 *   5. checks that the manifest, the code and the requested version agree;
 *   6. re-verifies every manifest entry against its file, and refuses to build if
 *      any file is missing, differs, or is not excluded by design;
 *   7. writes a flat archive (no wrapper directory, forward slashes) and a
 *      .sha256 next to it, then prints the gh command.
 *
 * Exit 0 only when an archive was written (or --dry-run/--verify-only passed).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/ReleaseBuilder.php';

use MdViewer\Tools\ReleaseBuilder;

$arguments = array_slice($argv, 1);
$options = ['version' => '', 'out' => '', 'commit' => '', 'composer' => '', 'verify-only' => ''];
$flags = ['dry-run' => false, 'keep-clone' => false];

foreach ($arguments as $argument) {
    if ($argument === '--dry-run' || $argument === '--keep-clone') {
        $flags[substr($argument, 2)] = true;
        continue;
    }
    if (preg_match('/^--(version|out|commit|composer|verify-only)=(.*)$/', $argument, $m) === 1) {
        $options[$m[1]] = $m[2];
        continue;
    }
    if ($argument === '--help' || $argument === '-h') {
        echo "usage: php tools/build-release.php --version=X --out=DIR [--commit=SHA] [--dry-run] [--keep-clone] [--composer=PATH]\n";
        echo "       php tools/build-release.php --verify-only=DIR\n";
        exit(0);
    }
    fwrite(STDERR, "unknown argument: {$argument}\n");
    exit(2);
}

$root = dirname(__DIR__);
$builder = new ReleaseBuilder($root);

function say(string $line): void
{
    fwrite(STDOUT, $line . "\n");
}

function fail(string $message): never
{
    fwrite(STDERR, "\nREFUSED: " . $message . "\n");
    exit(1);
}

function removeTree(string $path): void
{
    if ($path === '' || !is_dir($path)) {
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

/** Verify a tree against its own manifest, printing what was examined. */
function verifyTree(ReleaseBuilder $builder, string $tree, string $label): array
{
    $manifest = ReleaseBuilder::readManifest($tree);
    if ($manifest === null) {
        fail($label . ': no readable config/manifest.json in ' . $tree);
    }
    $version = (string) ($manifest['version'] ?? '');
    $result = ReleaseBuilder::verifyAgainstManifest($tree, $manifest);
    say(sprintf('manifest:  %d entries, version %s', count((array) $manifest['files']), $version));
    say(sprintf('verified:  %d/%d', $result['checked'] - count($result['problems']), $result['checked']));
    if ($result['outside'] !== []) {
        $parts = [];
        foreach ($result['outside'] as $reason => $count) {
            $parts[] = $reason . ' ' . $count;
        }
        say('outside:   ' . implode(', ', $parts) . '  (excluded by design)');
    }
    if (!$result['ok']) {
        foreach (array_slice($result['problems'], 0, 10) as $problem) {
            say('  PROBLEM ' . $problem);
        }
        fail(count($result['problems']) . ' problem(s) in ' . $tree . ' — the archive would not match its own manifest');
    }

    return $result + ['version' => $version, 'manifest' => $manifest];
}

// ── --verify-only ───────────────────────────────────────────────────────────
if ($options['verify-only'] !== '') {
    $tree = $options['verify-only'];
    if (!is_dir($tree)) {
        fail('no such directory: ' . $tree);
    }
    $result = verifyTree($builder, $tree, 'verify-only');
    say('RESULT: PASS (' . $result['version'] . ')');
    exit(0);
}

// ── Build ───────────────────────────────────────────────────────────────────
$version = $options['version'];
if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) !== 1) {
    fail('--version=X.Y.Z is required');
}
$out = $options['out'];
if ($out === '') {
    fail('--out=DIR is required (outside the working tree)');
}
$outReal = realpath($out);
$rootReal = realpath($root) ?: $root;
if ($outReal !== false && str_starts_with($outReal . DIRECTORY_SEPARATOR, $rootReal . DIRECTORY_SEPARATOR)) {
    fail('--out must be outside the repository: ' . $out);
}
if ($outReal !== false && str_starts_with($outReal . DIRECTORY_SEPARATOR, $rootReal . DIRECTORY_SEPARATOR . 'vendor')) {
    fail('--out must not point into vendor/: ' . $out);
}

$commit = $options['commit'];
if ($commit === '') {
    if (!$builder->isWorkingTreeClean()) {
        fail('the working tree has uncommitted changes — commit them, or pass --commit=<sha> to build a known commit');
    }
    $head = $builder->headCommit();
    if ($head === null) {
        fail('cannot read HEAD');
    }
    $commit = $head;
    say('commit:    ' . $commit . ' (HEAD, working tree clean)');
} else {
    say('commit:    ' . $commit . ' (given explicitly; uncommitted work is ignored)');
}

$clone = rtrim(sys_get_temp_dir(), '/\\') . '/mdscribe-release-' . $version . '-' . bin2hex(random_bytes(3));
say('clone:     ' . $clone);

try {
    $builder->cloneAt($commit, $clone);
    say('clone:     done');

    $composer = ReleaseBuilder::resolveComposer($options['composer'] !== '' ? $options['composer'] : null);
    say('composer:  ' . implode(' ', $composer));
    $builder->installRuntimeDependencies($clone, $composer);
    say('deps:      runtime only, vendor/autoload.php ' . (is_file($clone . '/vendor/autoload.php') ? 'present' : 'MISSING'));
    if (!is_file($clone . '/vendor/autoload.php')) {
        fail('the clone has no vendor/autoload.php after composer install — the archive would be uninstallable');
    }

    // Reproducibility. Two things in a naive build depend on the wall clock: the
    // `generated` field of the manifest and every file's mtime (which the zip
    // records). Both are pinned to the commit time, so one commit yields one
    // archive digest — measured before this: same size, different sha256.
    $commitTime = $builder->commitTimestamp($commit);
    if ($commitTime === null) {
        fail('cannot read the commit timestamp of ' . $commit);
    }
    $builder->buildManifest($clone, $commitTime);
    $manifest = ReleaseBuilder::readManifest($clone);
    if ($manifest === null) {
        fail('tools/build-manifest.php did not produce a readable manifest');
    }
    $builder->stampTree($clone, $commitTime);
    say('stamp:     manifest and mtimes pinned to ' . gmdate('c', $commitTime));

    $sourceVersion = ReleaseBuilder::versionFromSource($clone);
    $codename = ReleaseBuilder::codenameFromSource($clone);
    $manifestVersion = (string) ($manifest['version'] ?? '');
    say('version:   code ' . ($sourceVersion ?? '?') . ' "' . ($codename ?? '?') . '", manifest ' . $manifestVersion . ', requested ' . $version);
    if ($sourceVersion !== $version) {
        fail('src/Core/Version.php says ' . ($sourceVersion ?? 'nothing') . ', but --version=' . $version);
    }
    if ($manifestVersion !== $version) {
        fail('the manifest says ' . $manifestVersion . ', but --version=' . $version);
    }

    verifyTree($builder, $clone, 'clone');

    $files = ReleaseBuilder::collectShippedFiles($clone);
    $known = [];
    foreach ((array) $manifest['files'] as $entry) {
        if (is_array($entry)) {
            $known[(string) ($entry['path'] ?? '')] = true;
        }
    }
    $outside = [];
    foreach ($files as $rel) {
        if (!isset($known[$rel])) {
            $outside[] = $rel;
        }
    }
    say(sprintf('entries:   %d files to ship (%d in the manifest, %d outside)', count($files), count($files) - count($outside), count($outside)));
    if ($flags['dry-run']) {
        say('');
        say('DRY RUN: nothing was written. The archive would be MD.Scribe-' . $version . '.zip in ' . $out);
        exit(0);
    }

    $target = rtrim($out, '/\\') . '/MD.Scribe-' . $version . '.zip';
    $archive = ReleaseBuilder::writeArchive($clone, $files, $target);

    // Read the archive back: the manifest inside it must be the one just verified,
    // and no entry may be unsafe. A zip that cannot be reopened is not an artifact.
    $zip = new ZipArchive();
    if ($zip->open($archive['path']) !== true) {
        fail('the archive was written but cannot be reopened: ' . $archive['path']);
    }
    $unsafe = [];
    $inArchive = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        $inArchive[] = $name;
        if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || preg_match('/[\x00-\x1F]/', $name) === 1) {
            $unsafe[] = $name;
        }
    }
    $zipManifestRaw = $zip->getFromName('config/manifest.json');
    $zip->close();
    if ($unsafe !== []) {
        @unlink($archive['path']);
        fail('the archive contains an unsafe path: ' . $unsafe[0]);
    }
    if ($zipManifestRaw === false) {
        @unlink($archive['path']);
        fail('the archive has no config/manifest.json');
    }
    if (!hash_equals((string) hash('sha256', $zipManifestRaw), (string) hash_file('sha256', $clone . '/config/manifest.json'))) {
        @unlink($archive['path']);
        fail('the manifest inside the archive differs from the verified one');
    }
    if (count($inArchive) !== count($files)) {
        @unlink($archive['path']);
        fail('the archive holds ' . count($inArchive) . ' entries, expected ' . count($files));
    }

    $checksumFile = rtrim($out, '/\\') . '/MD.Scribe-' . $version . '.sha256';
    file_put_contents($checksumFile, $archive['sha256'] . '  MD.Scribe-' . $version . ".zip\n");

    say(sprintf('archive:   %s (%d bytes)', basename($archive['path']), $archive['bytes']));
    say('sha256:    ' . $archive['sha256']);
    say('checksum:  ' . $checksumFile);
    say('entries:   ' . count($inArchive) . ' (flat, forward slashes, no wrapper directory)');
    say('');
    say('next:');
    say('  php tools/verify-release.php --version=' . $version . ' --source=' . $archive['path'] . ' --sha256=' . $archive['sha256']);
    say('  git tag -a v' . $version . ' -m "MD.Scribe ' . $version . ' — ' . ($codename ?? '') . '" && git push origin v' . $version);
    say('  gh release create v' . $version . ' ' . $archive['path']
        . ' --title "MD.Scribe ' . $version . ' — ' . ($codename ?? '') . '" --notes-file <notes.md>');
} catch (RuntimeException $exception) {
    fail($exception->getMessage());
} finally {
    if ($flags['keep-clone']) {
        say('clone:     kept at ' . $clone);
    } else {
        removeTree($clone);
    }
}

exit(0);
