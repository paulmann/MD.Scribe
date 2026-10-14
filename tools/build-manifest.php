<?php

/**
 * MD.Scribe 3.0 — release manifest builder.
 *
 * Generates config/manifest.json: every shipped file with its sha256, used by
 * the updater to verify releases and by updater.php?api_key=…&action=status.
 * Run from a clean checkout right before publishing:
 *
 *   php tools/build-manifest.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use MdViewer\Core\Version;

$root = dirname(__DIR__);

/** Directories excluded from the manifest (runtime data, dev tooling). */
$excludeDirs = [
    $root . '/storage',
    $root . '/content',
    $root . '/.git',
    $root . '/tools/frontend/node_modules',
    $root . '/tests',
];
$excludeFiles = [
    $root . '/.md.ini',
    $root . '/config/manifest.json',
];

/**
 * Separator-independent comparison form. The iterator yields native paths
 * ("\\" on Windows, "/" elsewhere) while the prefixes above are written with
 * "/", so comparing them directly made every exclusion fail on Windows and a
 * locally built manifest listed content/, storage/ and tests/ as shipped files.
 * Normalise both sides before comparing.
 */
$slashed = static fn (string $path): string => str_replace('\\', '/', $path);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

// Runtime vendor packages only (dev tools must never ship in a release).
$lock = json_decode((string) @file_get_contents($root . '/composer.lock'), true);
$runtimePackages = [];
foreach ((array) ($lock['packages'] ?? []) as $package) {
    if (isset($package['name'])) {
        $runtimePackages[$slashed($root) . '/vendor/' . $package['name']] = true;
    }
}

$isDevVendorPath = static function (string $path) use ($root, $runtimePackages, $slashed): bool {
    $path = $slashed($path);
    $vendorRoot = $slashed($root) . '/vendor/';
    if (!str_starts_with($path, $vendorRoot)) {
        return false;
    }
    if (str_starts_with($path, $vendorRoot . 'autoload.php')
        || str_starts_with($path, $vendorRoot . 'composer/')) {
        return false;
    }
    foreach (array_keys($runtimePackages) as $packageDir) {
        if (str_starts_with($path, $slashed($packageDir) . '/')) {
            return false;
        }
    }

    return true;
};

$files = [];
foreach ($iterator as $item) {
    /** @var SplFileInfo $item */
    if (!$item->isFile()) {
        continue;
    }
    $path = $item->getPathname();
    foreach ($excludeDirs as $dir) {
        if (str_starts_with($slashed($path), $slashed($dir) . '/')) {
            continue 2;
        }
    }
    if (in_array($slashed($path), array_map($slashed, $excludeFiles), true) || $isDevVendorPath($path)) {
        continue;
    }
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
    // Skip runtime/local dotfiles but keep shipped ones (.htaccess, .github).
    if (preg_match('#^(\.md\.ini|\.git/|\.phpunit\.cache|\.DS_Store)|/\.DS_Store$#', $relative) === 1) {
        continue;
    }
    $files[] = [
        'path' => $relative,
        'sha256' => hash_file('sha256', $path),
        'bytes' => (int) $item->getSize(),
        'version' => Version::VERSION,
    ];
}

usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

$manifest = [
    'app' => 'MD.Scribe',
    'version' => Version::VERSION,
    'generated' => date('c'),
    'php_min' => Version::PHP_MIN,
    'files' => $files,
];

$out = $root . '/config/manifest.json';
file_put_contents($out, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

printf(
    "manifest written: %d files, %s total → %s\n",
    count($files),
    round(array_sum(array_column($files, 'bytes')) / 1024 / 1024, 2) . ' MB',
    $out,
);
