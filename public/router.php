<?php

/**
 * MD.Viewer 3.0 — router for the PHP built-in dev server:
 *
 *   php -S localhost:8080 -t public public/router.php
 *
 * Serves real files statically, executes the two front controllers
 * (index.php / admin.php) for their own URLs, and routes everything else
 * through index.php (also makes optional pretty URLs work in development).
 */

declare(strict_types=1);

$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$path = rawurldecode($path);
$publicDir = (string) realpath(__DIR__);
$file = __DIR__ . $path;

// The two front controllers, addressed by realpath. Comparing a realpath
// result against a string joined with "/" only works where the separator is
// "/"; on Windows realpath() returns "\", so ".../public/admin.php" never
// matched and /admin.php silently fell through to index.php.
$controllers = array_values(array_filter([
    realpath($publicDir . DIRECTORY_SEPARATOR . 'index.php'),
    realpath($publicDir . DIRECTORY_SEPARATOR . 'admin.php'),
], static fn ($candidate): bool => $candidate !== false));

// Never escape the public dir, never serve dotfiles.
$real = realpath($file);
if ($path !== '/' && $real !== false && str_starts_with($real, $publicDir . DIRECTORY_SEPARATOR) && !str_contains($path, '/.')) {
    if (in_array($real, $controllers, true)) {
        require $real;

        return;
    }
    if (is_file($real) && !str_ends_with($real, '.php')) {
        return false; // let the built-in server stream the static file
    }
}

require $publicDir . '/index.php';
