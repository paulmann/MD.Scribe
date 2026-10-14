<?php

/**
 * MD.Scribe 3.0 — updater compatibility shim (v2 → v3).
 *
 * Keeps every v2 entry point working against the new engine:
 *
 *   updater.php                                        landing page (status)
 *   updater.php?update=true[&force=true]               apply update (ALLOW_UPDATE)
 *   updater.php?restore=latest|app-YYYYmmdd-HHMMSS     restore app backup (ALLOW_RESTORE)
 *   updater.php?api_key=<key>&action=status&format=json
 *   updater.php?api_key=<key>&action=conflicts&format=json
 *   updater.php?api_key=<key>&action=install&format=json   (POST)
 *
 * The JSON envelope matches v2: {ok, action, version, steps, files,
 * conflicts, errors}. The recommended UI for v3 is the admin panel
 * (public/admin.php → Updates); this shim exists for bookmarks, scripts
 * and control panels built against v2.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use MdViewer\Admin\UpdateService;
use MdViewer\Core\AuditLog;
use MdViewer\Core\Config;
use MdViewer\Core\Logger;
use MdViewer\Core\Version;

$config = Config::instance();
$logger = new Logger($config);
$audit = new AuditLog();
$service = new UpdateService($config, $logger, $audit);

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_GET['action'] ?? '');
$format = (string) ($_GET['format'] ?? '');
$apiKey = (string) ($_GET['api_key'] ?? '');

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// ── Machine API (JSON) ─────────────────────────────────────────────────────
if ($format === 'json' && $action !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    $expectedKey = $config->str('API_KEY');
    if ($expectedKey === '' || !hash_equals($expectedKey, $apiKey)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'action' => $action, 'error' => 'unauthorized']);
        exit;
    }

    $envelope = static function (bool $ok, string $action, array $extra = []): void {
        echo (string) json_encode(array_merge([
            'ok' => $ok,
            'action' => $action,
            'version' => Version::VERSION,
        ], $extra), JSON_UNESCAPED_SLASHES);
    };

    if ($action === 'status') {
        $files = $service->status();
        $envelope(true, 'status', ['files' => $files, 'steps' => [], 'conflicts' => [], 'errors' => []]);
        exit;
    }

    if ($action === 'conflicts') {
        // v3 semantics: a conflict is any tracked file that is missing or was
        // modified locally (its sha256 no longer matches the shipped manifest).
        $files = $service->status();
        $conflicts = [];
        foreach ($files as $file) {
            if ($file['status'] !== 'present') {
                $conflicts[] = [
                    'path' => $file['path'],
                    'status' => $file['status'] === 'missing' ? 'missing' : 'exists',
                    'size_bytes' => is_file(__DIR__ . '/' . $file['path']) ? (int) @filesize(__DIR__ . '/' . $file['path']) : 0,
                ];
            }
        }
        $envelope(true, 'conflicts', ['files' => $files, 'conflicts' => $conflicts, 'steps' => [], 'errors' => []]);
        exit;
    }

    if ($action === 'install') {
        if ($method !== 'POST') {
            http_response_code(405);
            $envelope(false, 'install', ['errors' => ['POST required']]);
            exit;
        }
        $dryRun = (string) ($_GET['dry_run'] ?? '') === '1';
        if ($dryRun) {
            $files = $service->status();
            $would = array_values(array_filter($files, static fn (array $f): bool => $f['status'] !== 'present'));
            $envelope(true, 'install', [
                'files' => $files,
                'steps' => array_map(static fn (array $f): array => [
                    'step' => 'install:' . $f['path'],
                    'status' => 'dry-run',
                    'detail' => $f['status'],
                ], $would),
                'conflicts' => [],
                'errors' => [],
            ]);
            exit;
        }
        $result = $service->apply('machine-api');
        $envelope((bool) ($result['ok'] ?? false), 'install', [
            'steps' => [[
                'step' => 'install:release',
                'status' => ($result['ok'] ?? false) ? 'ok' : 'error',
                'detail' => ($result['ok'] ?? false)
                    ? ($result['from'] ?? '?') . ' → ' . ($result['to'] ?? '?') . ', ' . ($result['replaced'] ?? 0) . ' files'
                    : (string) ($result['error'] ?? 'failed'),
            ]],
            'files' => $service->status(),
            'conflicts' => [],
            'errors' => ($result['ok'] ?? false) ? [] : [(string) ($result['error'] ?? 'failed')],
        ]);
        exit;
    }

    http_response_code(400);
    $envelope(false, $action, ['errors' => ['unknown action']]);
    exit;
}

// ── HTML modes ─────────────────────────────────────────────────────────────

$layout = static function (string $title, string $body) use ($e): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . "<title>{$e($title)} · MD.Scribe updater</title>"
        . '<style>'
        . ':root{color-scheme:light dark;--bg:#fafafa;--fg:#18181b;--mut:#71717a;--bd:#e4e4e7;--acc:#2563eb}'
        . '@media(prefers-color-scheme:dark){:root{--bg:#18181b;--fg:#fafafa;--mut:#a1a1aa;--bd:#3f3f46;--acc:#60a5fa}}'
        . 'body{font:14px/1.6 system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--fg);margin:0;padding:2rem 1rem}'
        . '.wrap{max-width:46rem;margin:0 auto}h1{font-size:1.3rem;margin:0 0 .3rem}.sub{color:var(--mut);margin:0 0 1.5rem}'
        . 'table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:.45rem .6rem;border-bottom:1px solid var(--bd)}'
        . 'th{color:var(--mut);font-size:11px;text-transform:uppercase;letter-spacing:.05em}'
        . '.badge{display:inline-block;padding:.1rem .5rem;border-radius:999px;font-size:11px;font-weight:600;border:1px solid var(--bd)}'
        . '.ok{color:#16a34a}.warn{color:#d97706}.err{color:#dc2626}'
        . '.btn{display:inline-block;background:var(--acc);color:#fff;border:0;border-radius:8px;padding:.5rem 1rem;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer}'
        . '.btn.ghost{background:transparent;color:var(--fg);border:1px solid var(--bd)}'
        . '.note{background:color-mix(in srgb,var(--bd) 35%,transparent);border:1px solid var(--bd);border-radius:8px;padding:.7rem .9rem;font-size:13px;color:var(--mut);margin:1rem 0}'
        . 'code{font-family:ui-monospace,monospace;font-size:12px}'
        . '</style></head><body><div class="wrap">' . $body . '</div></body></html>';
};

// ?update=true — direct apply (ALLOW_UPDATE required)
if (($_GET['update'] ?? '') === 'true' || ($_GET['update'] ?? '') === '1') {
    if (!$config->bool('ALLOW_UPDATE')) {
        http_response_code(403);
        $layout('Update disabled', '<h1>Update disabled</h1><p class="sub">Set <code>ALLOW_UPDATE = true</code> in <code>.md.ini</code> to enable updates.</p>');
        exit;
    }
    set_time_limit(300);
    $result = $service->apply('updater.php');
    $rows = ($result['ok'] ?? false)
        ? '<p><span class="badge ok">updated</span> ' . $e($result['from'] ?? '?') . ' → ' . $e($result['to'] ?? '?')
          . ' — ' . (int) ($result['replaced'] ?? 0) . ' file(s) replaced, ' . (int) ($result['unchanged'] ?? 0) . ' unchanged.'
          . '<br>Backup: <code>' . $e($result['backup'] ?? '—') . '</code></p>'
        : '<p><span class="badge err">error</span> ' . $e($result['error'] ?? 'unknown error') . '</p>';
    $layout('Update result', '<h1>Update result</h1>' . $rows . '<p><a class="btn ghost" href="updater.php">← Back</a></p>');
    exit;
}

// ?restore=latest|app-… — rollback (ALLOW_RESTORE required)
if (isset($_GET['restore']) && (string) $_GET['restore'] !== '') {
    if (!$config->bool('ALLOW_RESTORE')) {
        http_response_code(403);
        $layout('Restore disabled', '<h1>Restore disabled</h1><p class="sub">Set <code>ALLOW_RESTORE = true</code> in <code>.md.ini</code>.</p>');
        exit;
    }
    $requested = (string) $_GET['restore'];
    $backups = $service->appBackups();
    $id = $requested === 'latest' ? (string) ($backups[0]['id'] ?? '') : $requested;
    if ($id === '') {
        $layout('Restore', '<h1>Nothing to restore</h1><p class="sub">No application backups exist yet — they are created before every update.</p>');
        exit;
    }
    $result = $service->restoreApp($id, 'updater.php');
    $body = ($result['ok'] ?? false)
        ? '<p><span class="badge ok">restored</span> ' . (int) ($result['files'] ?? 0) . ' file(s) restored from <code>' . $e($id) . '</code>.</p>'
        : '<p><span class="badge err">error</span> ' . $e($result['error'] ?? 'restore failed') . '</p>';
    $layout('Restore result', '<h1>Restore result</h1>' . $body . '<p><a class="btn ghost" href="updater.php">← Back</a></p>');
    exit;
}

// Landing page — status table
$files = $service->status();
$counts = ['present' => 0, 'modified' => 0, 'missing' => 0];
foreach ($files as $file) {
    $counts[$file['status']] = ($counts[$file['status']] ?? 0) + 1;
}
$rows = '';
foreach ($files as $file) {
    $badge = match ($file['status']) {
        'present' => '<span class="badge ok">present</span>',
        'modified' => '<span class="badge warn">modified</span>',
        default => '<span class="badge err">missing</span>',
    };
    $rows .= '<tr><td><code>' . $e($file['path']) . '</code></td><td>' . $badge . '</td><td>' . $e($file['version']) . '</td></tr>';
}
$check = $service->check();
$checkLine = ($check['ok'] ?? false)
    ? (($check['newer'] ?? false)
        ? '<p class="sub"><span class="badge warn">update available</span> v' . $e($check['latest']) . ' (you run v' . $e($check['current']) . ') — <a href="?update=true" class="btn" style="padding:.25rem .6rem">Check &amp; apply</a></p>'
        : '<p class="sub"><span class="badge ok">up to date</span> v' . $e($check['current']) . '</p>')
    : '<p class="sub"><span class="badge warn">check failed</span> ' . $e($check['error'] ?? '') . '</p>';

$allowUpdateNote = $config->bool('ALLOW_UPDATE')
    ? ''
    : '<div class="note">Updates are disabled: set <code>ALLOW_UPDATE = true</code> in <code>.md.ini</code>. The machine API below works with <code>API_KEY</code> regardless.</div>';

$layout('MD.Scribe updater', '<h1>MD.Scribe updater</h1>' . $checkLine . $allowUpdateNote
    . '<table><thead><tr><th>Tracked file</th><th>Status</th><th>Version</th></tr></thead><tbody>' . ($rows !== '' ? $rows : '<tr><td colspan="3">No manifest — run <code>php tools/build-manifest.php</code>.</td></tr>') . '</tbody></table>'
    . '<div class="note">Recommended: manage updates from the <a href="public/admin.php?page=updates">admin panel</a>. This page keeps the v2 URL contract (<code>?update=true</code>, <code>?restore=latest</code>, machine API with <code>api_key</code>).</div>');
