<?php

/**
 * MD.Viewer 3.0 — compatibility shim (v2 → v3).
 *
 * MD.Viewer v2 was a single md.php that rendered md.md next to it. Dropping
 * this file into the project root of a v3 install keeps every old bookmark,
 * every `?file=` link and the `&download=1` contract working:
 *
 *   md.php                       → home document (md.md if present, else HOME_DOC)
 *   md.php?file=docs/api.md      → viewer
 *   md.php?file=…&download=1     → source download
 *
 * It boots the exact same app as public/index.php — one code path, one
 * validator, one renderer.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use MdViewer\Core\App;
use MdViewer\Http\FrontController;

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/md.php'));
$assetBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/public';

// v2 rule: md.php renders md.md. Honour it when the file exists and the
// request did not ask for a specific document.
if (!isset($_GET['file']) && !isset($_GET['page']) && !isset($_GET['api'])) {
    $candidate = rtrim(MdViewer\Core\Config::instance()->browseDir(), '/\\') . '/md.md';
    if (is_file($candidate)) {
        $_GET['file'] = 'md.md';
    }
}

$app = App::boot('viewer', $scriptName, $assetBase);

(new FrontController($app))->handle()->send();
