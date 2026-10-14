<?php

/**
 * MD.Viewer 3.0 — admin kernel (public/admin.php entry).
 *
 * Pages:  login · setup · dashboard · files · editor · index · backups ·
 *         updates · settings · themes · logs · security · tools
 * JSON:   ?action=… (POST unless noted) — every mutating call is
 *         authenticated + CSRF-protected + audited.
 */

declare(strict_types=1);

namespace MdViewer\Http;

use MdViewer\Admin\AdminAuth;
use MdViewer\Admin\BackupService;
use MdViewer\Admin\HealthCheck;
use MdViewer\Admin\LinkChecker;
use MdViewer\Admin\UpdateService;
use MdViewer\Core\App;
use MdViewer\Core\AuditLog;
use MdViewer\Core\Paths;
use MdViewer\Core\Response;
use MdViewer\Core\Version;
use MdViewer\Files\FileManager;
use MdViewer\Files\MarkdownRepository;

final class AdminKernel
{
    private AdminAuth $auth;

    public function __construct(private readonly App $app)
    {
        $this->auth = new AdminAuth(
            $app->config,
            $app->session,
            $app->request,
            $app->logger,
            $app->audit,
        );
    }

    public function handle(): Response
    {
        if (!$this->auth->isPanelEnabled()) {
            // Existence is not disclosed when the panel is disabled.
            return Response::html('<h1>404 Not Found</h1>', 404)->noStore();
        }

        $this->app->session->start();
        $req = $this->app->request;

        // ── JSON actions ───────────────────────────────────────────────────
        $action = (string) ($req->query('action') ?? $req->post('action') ?? '');
        if ($action !== '') {
            if ($req->isPost() && !$this->app->csrf->validate($req)) {
                return Response::json(['ok' => false, 'error' => 'CSRF token missing or invalid.'], 403);
            }
            if (in_array($action, ['login', 'setup'], true)) {
                return $this->authAction($action);
            }
            if (!$this->auth->isAuthenticated()) {
                return Response::json(['ok' => false, 'error' => 'unauthorized'], 401);
            }
            if ($action === 'logout') {
                $this->auth->logout();

                return Response::json(['ok' => true]);
            }

            return $this->jsonAction($action);
        }

        // ── Pages ──────────────────────────────────────────────────────────
        $page = (string) ($req->query('page') ?? 'dashboard');

        if ($page === 'login') {
            if ($this->auth->isAuthenticated()) {
                return Response::redirect($req->scriptUrl());
            }
            if (!$this->auth->isPasswordSet()) {
                return Response::redirect($req->url(['page' => 'setup']));
            }

            return $this->page('admin/login', ['setupNeeded' => false]);
        }

        if (!$this->auth->isPasswordSet()) {
            if ($page !== 'setup') {
                return Response::redirect($req->url(['page' => 'setup']));
            }

            return $this->page('admin/setup', [
                'localhostAllowed' => $this->auth->setupAllowed(),
            ]);
        }

        if (!$this->auth->isAuthenticated()) {
            return Response::redirect($req->url(['page' => 'login']));
        }

        $pages = [
            'dashboard', 'files', 'editor', 'index', 'backups',
            'updates', 'settings', 'themes', 'logs', 'security', 'tools',
        ];
        if (!in_array($page, $pages, true)) {
            $page = 'dashboard';
        }

        return $this->renderAdminPage($page);
    }

    // ── Auth actions ───────────────────────────────────────────────────────

    private function authAction(string $action): Response
    {
        $req = $this->app->request;
        if (!$req->isPost()) {
            return Response::json(['ok' => false, 'error' => 'POST required'], 405);
        }
        if (!$this->app->csrf->validate($req)) {
            return Response::json(['ok' => false, 'error' => 'CSRF token missing or invalid.'], 403);
        }

        if ($action === 'login') {
            if (($locked = $this->auth->lockSeconds()) > 0) {
                return Response::json(['ok' => false, 'error' => "Too many attempts — locked for {$locked}s.", 'locked' => $locked], 429);
            }
            $result = $this->auth->attempt(
                (string) ($req->post('username') ?? ''),
                (string) ($req->post('password') ?? ''),
            );
            if (!$result['ok']) {
                $status = ($result['locked'] ?? 0) > 0 ? 429 : 401;

                return Response::json(['ok' => false, 'error' => $result['error'] ?? 'Login failed'], $status);
            }
            $this->app->csrf->rotate();

            return Response::json(['ok' => true, 'redirect' => $this->app->request->scriptUrl()]);
        }

        // setup
        if ($this->auth->isPasswordSet()) {
            return Response::json(['ok' => false, 'error' => 'Already configured.'], 409);
        }
        $ok = $this->auth->setup(
            (string) ($req->post('code') ?? ''),
            (string) ($req->post('password') ?? ''),
        );
        if (!$ok) {
            return Response::json(['ok' => false, 'error' => 'Setup refused: wrong code, or password shorter than 10 characters.'], 400);
        }
        $this->app->csrf->rotate();

        return Response::json(['ok' => true, 'redirect' => $this->app->request->url(['page' => 'login'])]);
    }

    // ── Authenticated JSON actions ─────────────────────────────────────────

    private function jsonAction(string $action): Response
    {
        $req = $this->app->request;
        $actor = $this->auth->user();
        $fm = $this->app->fileManager();

        try {
            return match ($action) {
                // Content
                'file.read' => $this->json($fm->read((string) ($req->query('path') ?? $req->post('path') ?? ''))),
                'file.write' => $this->json($fm->write(
                    (string) ($req->post('path') ?? ''),
                    (string) ($req->post('content') ?? ''),
                    $actor,
                    $req->post('hash'),
                )),
                'file.create' => $this->json($fm->write(
                    (string) ($req->post('path') ?? ''),
                    (string) ($req->post('content') ?? "# New document\n"),
                    $actor,
                )),
                'file.rename' => $this->json($fm->rename((string) ($req->post('from') ?? ''), (string) ($req->post('to') ?? ''), $actor)),
                'file.move' => $this->json($fm->move((string) ($req->post('from') ?? ''), (string) ($req->post('to_dir') ?? ''), $actor)),
                'file.delete' => $this->json($fm->delete((string) ($req->post('path') ?? ''), $actor)),
                'folder.create' => $this->json($fm->createFolder((string) ($req->post('path') ?? ''), $actor)),
                'folder.delete' => $this->json($fm->deleteFolder((string) ($req->post('path') ?? ''), $actor)),
                'upload.md' => $this->adminUploadMd($fm, $actor),
                'upload.image' => $this->adminUploadImage($fm, $actor),
                'preview' => $this->preview((string) ($req->post('content') ?? '')),
                'trash.list' => $this->json(['ok' => true, 'items' => $fm->trashList()]),
                'trash.restore' => $this->json($fm->restoreFromTrash((string) ($req->post('name') ?? ''), $actor)),

                // Search index
                'index.rebuild' => $this->json(['ok' => true] + $this->app->search()->rebuild()),
                'index.stats' => $this->json(['ok' => true] + $this->app->search()->stats()),

                // Backups
                'backup.create' => $this->json((new BackupService($this->app->repo(), $this->app->logger, $this->app->audit))->create($actor, (string) ($req->post('note') ?? ''))),
                'backup.list' => $this->json(['ok' => true, 'items' => (new BackupService($this->app->repo(), $this->app->logger, $this->app->audit))->list()]),
                'backup.restore' => $this->restoreGuard((new BackupService($this->app->repo(), $this->app->logger, $this->app->audit))->restore((string) ($req->post('id') ?? ''), $actor)),
                'backup.delete' => $this->json((new BackupService($this->app->repo(), $this->app->logger, $this->app->audit))->delete((string) ($req->post('id') ?? ''), $actor)),

                // Updater
                'update.check' => $this->json((new UpdateService($this->app->config, $this->app->logger, $this->app->audit))->check($req->query('force') === '1' || $req->post('force') === '1')),
                'update.status' => $this->json(['ok' => true, 'files' => (new UpdateService($this->app->config, $this->app->logger, $this->app->audit))->status()]),
                'update.apply' => $this->updateGuard((new UpdateService($this->app->config, $this->app->logger, $this->app->audit))->apply($actor)),

                // Settings
                'settings.set' => $this->settingsSet($actor),
                'settings.regenerate-key' => $this->regenerateApiKey($actor),

                // Diagnostics
                'logs.tail' => $this->json(['ok' => true, 'logs' => $this->app->logger->tail(3, 200), 'audit' => AuditLog::read(100)]),
                'health.run' => $this->json((new HealthCheck($this->app->config))->run()),
                'links.check' => $this->json(['ok' => true] + (new LinkChecker($this->app->repo()))->run()),
                'cache.clear' => $this->cacheClear($actor),

                // Custom themes
                'theme.save' => $this->themeSave($actor),
                'theme.delete' => $this->themeDelete($actor),

                default => Response::json(['ok' => false, 'error' => 'unknown action: ' . $action], 400),
            };
        } catch (\Throwable $e) {
            $this->app->logger->error('admin action failed', ['action' => $action, 'error' => $e->getMessage()]);

            return Response::json(['ok' => false, 'error' => $this->app->isDevelopment() ? $e->getMessage() : 'action failed'], 500);
        }
    }

    private function json(array $result): Response
    {
        $status = (int) ($result['status'] ?? ($result['ok'] ? 200 : 400));

        return Response::json($result, $status === 0 ? 400 : $status);
    }

    private function restoreGuard(array $result): Response
    {
        if (($result['ok'] ?? false) === false) {
            return $this->json($result);
        }
        if (!$this->app->config->bool('ALLOW_RESTORE')) {
            return Response::json(['ok' => false, 'error' => 'ALLOW_RESTORE is false in .md.ini.'], 403);
        }

        return $this->json($result);
    }

    private function updateGuard(array $result): Response
    {
        if (!$this->app->config->bool('ALLOW_UPDATE')) {
            return Response::json(['ok' => false, 'error' => 'ALLOW_UPDATE is false in .md.ini.'], 403);
        }

        return $this->json($result);
    }

    private function adminUploadMd(FileManager $fm, string $actor): Response
    {
        unset($fm); // uploads go through the Uploader (reader path), audited as admin
        $file = $this->app->request->files()['file'] ?? null;
        if (!is_array($file)) {
            return Response::json(['ok' => false, 'error' => 'missing file'], 400);
        }
        // Admin uploads bypass the reader DISABLE_UPLOAD gate but are audited.
        $result = $this->app->uploader()->saveMarkdown(
            (string) @file_get_contents((string) ($file['tmp_name'] ?? '')),
            (string) ($file['name'] ?? ''),
            (string) ($this->app->request->post('dir') ?? ''),
            $actor,
        );

        return $this->json($result);
    }

    private function adminUploadImage(FileManager $fm, string $actor): Response
    {
        $file = $this->app->request->files()['file'] ?? null;
        if (!is_array($file)) {
            return Response::json(['ok' => false, 'error' => 'missing file'], 400);
        }

        return $this->json($fm->uploadImage($file, (string) ($this->app->request->post('dir') ?? ''), $actor));
    }

    private function preview(string $markdown): Response
    {
        if ($markdown === '' || strlen($markdown) > 8 * 1024 * 1024) {
            return Response::json(['ok' => false, 'error' => 'empty or oversized'], 400);
        }
        $result = $this->app->markdown()->render($markdown, [
            'docDir' => '',
            'docPath' => '',
            'urlBuilder' => fn (array $params): string => $this->app->request->url($params),
            'mediaUrlBuilder' => fn (string $rel): string => $this->app->request->url(['media' => $rel]),
            'fileExists' => fn (string $rel): bool => $this->app->repo()->resolve($rel) !== null,
        ]);

        return Response::json(['ok' => true, 'html' => $result->html, 'title' => $result->title]);
    }

    private function settingsSet(string $actor): Response
    {
        $changes = $this->app->request->jsonBody();
        if ($changes === []) {
            $changes = $this->app->request->postAll();
            unset($changes['_token'], $changes['action']);
        }
        $applied = [];
        $errors = [];
        foreach ($changes as $key => $value) {
            $key = (string) $key;
            $schema = \MdViewer\Core\Config::schema()[$key] ?? null;
            if ($schema === null) {
                $errors[] = $key . ': unknown key';
                continue;
            }
            if (in_array($key, ['ADMIN_PASSWORD_HASH'], true)) {
                $errors[] = $key . ': managed automatically, refused';
                continue;
            }
            if ($key === 'ADMIN_PASSWORD') {
                if (is_string($value) && $value !== '') {
                    if (strlen($value) < 10) {
                        $errors[] = $key . ': minimum 10 characters';
                        continue;
                    }
                    $this->app->config->set('ADMIN_PASSWORD_HASH', password_hash($value, PASSWORD_DEFAULT));
                    $this->app->config->set('ADMIN_PASSWORD', '');
                    $this->app->audit->record('settings.change_password', $actor);
                    $applied[] = 'ADMIN_PASSWORD (hashed)';
                }
                continue;
            }
            $typed = match ($schema['type']) {
                'bool' => in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true),
                'int' => (int) $value,
                default => is_scalar($value) ? (string) $value : '',
            };
            if ($schema['type'] === 'string' && strlen((string) $typed) > \MdViewer\Core\Config::MAX_VALUE_BYTES) {
                $errors[] = $key . ': value too long';
                continue;
            }
            $this->app->config->set($key, $typed);
            $applied[] = $key;
        }
        if ($applied !== []) {
            $this->app->audit->record('settings.save', $actor, ['keys' => $applied]);
        }

        return Response::json(['ok' => $errors === [], 'applied' => $applied, 'errors' => $errors]);
    }

    private function regenerateApiKey(string $actor): Response
    {
        $key = \MdViewer\Core\Config::generateApiKey();
        $this->app->config->set('API_KEY', $key);
        $this->app->audit->record('settings.regenerate_api_key', $actor);

        return Response::json(['ok' => true, 'api_key' => $key]);
    }

    private function cacheClear(string $actor): Response
    {
        $cache = new \MdViewer\Markdown\RenderCache(true, $this->app->logger);
        $removed = $cache->clear();
        $this->app->search()->delete();
        $this->app->audit->record('cache.clear', $actor, ['entries' => $removed]);

        return Response::json(['ok' => true, 'removed' => $removed]);
    }

    private function themeSave(string $actor): Response
    {
        $body = $this->app->request->jsonBody() ?: $this->app->request->postAll();
        $id = (string) ($body['id'] ?? '');
        $name = (string) ($body['name'] ?? '');
        $light = (array) ($body['light'] ?? []);
        $dark = (array) ($body['dark'] ?? []);
        if ($name === '' || $light === [] || $dark === []) {
            return Response::json(['ok' => false, 'error' => 'name, light and dark tokens are required'], 400);
        }
        $saved = $this->app->themes()->saveCustom($id !== '' ? $id : $name, $name, $light, $dark);
        $this->app->audit->record('theme.save', $actor, ['id' => $saved['id'] ?? '']);

        return Response::json(['ok' => true, 'theme' => $saved]);
    }

    private function themeDelete(string $actor): Response
    {
        $id = (string) ($this->app->request->post('id') ?? '');
        $ok = $this->app->themes()->deleteCustom($id);
        $this->app->audit->record('theme.delete', $actor, ['id' => $id, 'ok' => $ok]);

        return Response::json(['ok' => $ok]);
    }

    // ── Pages ──────────────────────────────────────────────────────────────

    private function renderAdminPage(string $page): Response
    {
        $titles = [
            'dashboard' => 'Dashboard',
            'files' => 'Files',
            'editor' => 'Editor',
            'index' => 'Search index',
            'backups' => 'Backups & trash',
            'updates' => 'Updates',
            'settings' => 'Server settings',
            'themes' => 'Custom themes',
            'logs' => 'Logs & audit',
            'security' => 'Security & health',
            'tools' => 'Tools',
        ];
        $data = match ($page) {
            'dashboard' => $this->dashboardData(),
            'files' => ['tree' => $this->app->fileManager()->tree()],
            'editor' => ['openFile' => (string) ($this->app->request->query('file') ?? ''), 'tree' => $this->app->fileManager()->tree()],
            'index' => ['stats' => $this->app->search()->stats()],
            'backups' => [
                'backups' => (new BackupService($this->app->repo(), $this->app->logger, $this->app->audit))->list(),
                'trash' => $this->app->fileManager()->trashList(),
                'appBackups' => (new UpdateService($this->app->config, $this->app->logger, $this->app->audit))->appBackups(),
            ],
            'updates' => ['update' => (new UpdateService($this->app->config, $this->app->logger, $this->app->audit))->check()],
            'settings' => ['schema' => \MdViewer\Core\Config::schema()],
            'themes' => ['customThemes' => $this->app->themes()->listCustom()],
            'logs' => ['logs' => $this->app->logger->tail(3, 200), 'audit' => AuditLog::read(100)],
            'security' => ['health' => (new HealthCheck($this->app->config))->run()],
            'tools' => [],
            default => [],
        };

        return $this->page('admin/' . $page, $data + ['pageTitle' => $titles[$page] ?? 'Admin'], $page);
    }

    private function dashboardData(): array
    {
        $repo = $this->app->repo();
        $stats = $repo->stats();
        $backups = (new BackupService($repo, $this->app->logger, $this->app->audit))->list();
        $health = (new HealthCheck($this->app->config))->run();
        $indexStats = $this->app->search()->stats();

        return [
            'stats' => $stats,
            'indexStats' => $indexStats,
            'backups' => array_slice($backups, 0, 5),
            'health' => $health,
            'recentAudit' => AuditLog::read(8),
            'recentDocs' => $this->recentDocs($repo),
        ];
    }

    /** @return list<array{path: string, title: string, modified: int}> */
    private function recentDocs(MarkdownRepository $repo): array
    {
        $files = $repo::sortFiles($repo->scan(), 'modified', 'desc');
        $out = [];
        foreach (array_slice($files, 0, 8) as $file) {
            $out[] = ['path' => (string) $file['path'], 'title' => (string) $file['title'], 'modified' => (int) $file['modified']];
        }

        return $out;
    }

    private function page(string $template, array $data, string $activePage = ''): Response
    {
        $html = $this->app->tpl->render($template, array_merge([
            'app' => $this->app,
            'auth' => $this->auth,
            'activePage' => $activePage,
            'flashes' => $this->app->session->pullFlashes(),
            'version' => Version::VERSION,
            'adminJsConfig' => [
                'scriptUrl' => $this->app->request->scriptUrl(),
                'assetBase' => $this->app->request->assetBase(),
                'csrf' => $this->app->csrf->token(),
                'viewerUrl' => $this->viewerUrl(),
                'version' => Version::VERSION,
                'phpVersion' => PHP_VERSION,
                'browseDir' => $this->app->repo()->browseDir(),
                'allowUpdate' => $this->app->config->bool('ALLOW_UPDATE'),
                'allowRestore' => $this->app->config->bool('ALLOW_RESTORE'),
            ],
        ], $data));

        return Response::html($html)->noStore()
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    private function viewerUrl(): string
    {
        $base = rtrim($this->app->request->assetBase(), '/');
        if (basename($base) === 'public') {
            $base = dirname($base);
        }

        return ($base === '' || $base === '/' ? '' : $base) . '/index.php';
    }

    /** Direct file download for backups (GET, authenticated). */
    public function downloadBackup(string $id): Response
    {
        if (!$this->auth->isAuthenticated()) {
            return Response::text('Unauthorized.', 401)->noStore();
        }
        $zip = (new BackupService($this->app->repo(), $this->app->logger, $this->app->audit))->archive($id);
        if ($zip === null) {
            return Response::text('Backup not available (zip extension missing or backup not found).', 404);
        }

        return Response::download($zip, $id . '.zip');
    }
}
