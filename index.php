<?php

/**
 * MD.Scribe 3.0 — project-root entry (for docroot-at-project-root installs).
 *
 * Booting this file is equivalent to public/index.php: it renders the home
 * document or the file browser. Asset URLs are computed for the /public
 * prefix automatically. On a docroot-at-public deployment this file is never
 * reached.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use MdViewer\Core\App;
use MdViewer\Http\FrontController;

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$assetBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/public';

$app = App::boot('viewer', $scriptName, $assetBase);

(new FrontController($app))->handle()->send();
