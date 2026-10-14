<?php

/**
 * MD.Viewer 3.0 — bootstrap: autoloading + global helpers.
 *
 * Included by every entry point (public/index.php, public/admin.php, the
 * compatibility shims, CLI tools and the test suite).
 */

declare(strict_types=1);

namespace {
    // ── PHP version gate ────────────────────────────────────────────────────
    if (PHP_VERSION_ID < 80300) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit('MD.Viewer 3.0 requires PHP >= 8.3.0; running ' . PHP_VERSION . ".\n");
    }

    // ── Required extensions ─────────────────────────────────────────────────
    foreach (['mbstring', 'json', 'dom', 'libxml'] as $mdvExt) {
        if (!extension_loaded($mdvExt)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit("MD.Viewer requires the PHP extension '{$mdvExt}', which is not loaded.\n");
        }
    }
    unset($mdvExt);

    // ── Autoloading ─────────────────────────────────────────────────────────
    $mdvAutoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($mdvAutoload)) {
        require $mdvAutoload;
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit(
            "MD.Viewer: vendor/autoload.php is missing.\n"
            . "Run `composer install` in the project root (or deploy the release archive, which bundles vendor/).\n"
        );
    }
    unset($mdvAutoload);

    // ── Global helpers (templates are plain PHP in the global namespace) ────
    if (!function_exists('e')) {
        /** Escape for HTML output. */
        function e(mixed $value): string
        {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    if (!function_exists('icon')) {
        /** Render an inline lucide icon (24×24 stroke SVG). */
        function icon(string $name, string $class = 'h-4 w-4', string $title = ''): string
        {
            return \MdViewer\View\Icons::svg($name, $class, $title);
        }
    }
}

namespace MdViewer {
    // ── Timezone & storage sanity ───────────────────────────────────────────
    date_default_timezone_set('UTC');

    // Non-fatal: the app degrades to uncached mode instead of dying.
    Core\Paths::ensureStorage();
}
