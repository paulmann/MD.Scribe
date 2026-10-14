<?php

/**
 * MD.Viewer 3.0 — admin panel entry point.
 *
 * Serves the admin UI (session login) and its JSON action API. Kept as a
 * separate script so hosts can firewall /admin.php independently.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use MdViewer\Core\App;
use MdViewer\Http\AdminKernel;

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin.php'));
$assetBase = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

$app = App::boot('admin', $scriptName, $assetBase);

$kernel = new AdminKernel($app);

// Backup download has a body — handle before the generic dispatch.
if (($_GET['action'] ?? '') === 'backup.download' && isset($_GET['id'])) {
    $app->session->start();
    $response = $kernel->downloadBackup((string) $_GET['id']);
    $response->send();
}

$kernel->handle()->send();
