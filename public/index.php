<?php

/**
 * MD.Scribe 3.0 — public front controller.
 *
 * Deployment options (see README):
 *  A) docroot = public/            → https://site/            (recommended)
 *  B) docroot = project root       → https://site/public/     (works out of the box;
 *                                     root index.php / md.php shims forward here)
 *  C) php -S localhost:8080 -t public
 *
 * This file computes its own web prefix, so asset URLs and links stay correct
 * in sub-directory installs without any configuration.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use MdViewer\Core\App;
use MdViewer\Http\FrontController;

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
if ($scriptName === '' || !str_ends_with($scriptName, '.php')) {
    $scriptName = '/index.php';
}
$assetBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

$app = App::boot('viewer', $scriptName, $assetBase);

(new FrontController($app))->handle()->send();
