<?php
/**
 * MD.Viewer 3.0 — admin layout: fixed sidebar + topbar, same design tokens
 * as the public app.
 *
 * @var MdViewer\Core\App $app
 * @var string $activePage
 * @var MdViewer\Admin\AdminAuth $auth
 * @var list<array{type: string, message: string}> $flashes
 * @var array<string, mixed> $adminJsConfig
 */

use MdViewer\View\Icons;

$req = $app->request;
$nonce = $app->nonce();
$nav = [
    'Overview' => [
        ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
        ['page' => 'security', 'label' => 'Security & health', 'icon' => 'shield-check'],
    ],
    'Content' => [
        ['page' => 'files', 'label' => 'Files', 'icon' => 'folder-tree'],
        ['page' => 'editor', 'label' => 'Editor', 'icon' => 'square-pen'],
        ['page' => 'index', 'label' => 'Search index', 'icon' => 'file-search'],
    ],
    'System' => [
        ['page' => 'backups', 'label' => 'Backups & trash', 'icon' => 'history'],
        ['page' => 'updates', 'label' => 'Updates', 'icon' => 'cloud-download'],
        ['page' => 'settings', 'label' => 'Settings', 'icon' => 'settings'],
        ['page' => 'themes', 'label' => 'Custom themes', 'icon' => 'palette'],
    ],
    'Diagnostics' => [
        ['page' => 'logs', 'label' => 'Logs & audit', 'icon' => 'scroll-text'],
        ['page' => 'tools', 'label' => 'Tools', 'icon' => 'wrench'],
    ],
];
$pageTitle = $pageTitle ?? 'Admin';
?>
<!doctype html>
<html lang="en" data-neutral="zinc" data-accent="default" data-radius="md" data-font="inter" data-theme-preset="<?= e($app->config->str('THEME_DEFAULT')) ?>" data-mode-default="<?= e($app->config->str('MODE_DEFAULT')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?> · MD.Viewer Admin</title>
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="<?= e($req->assetUrl('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/themes.css')) ?>">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/typography.css')) ?>">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/admin.css')) ?>">
<script nonce="<?= e($nonce) ?>">
(function () {
  var root = document.documentElement;
  var stored = null;
  try { stored = JSON.parse(localStorage.getItem('mdv.theme') || 'null'); } catch (e) {}
  if (stored) {
    if (stored.neutral) root.setAttribute('data-neutral', stored.neutral);
    if (stored.accent) root.setAttribute('data-accent', stored.accent);
    if (stored.radius) root.setAttribute('data-radius', stored.radius);
    if (stored.font) root.setAttribute('data-font', stored.font);
  }
  var mode = (stored && stored.mode) || root.getAttribute('data-mode-default') || 'system';
  var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  root.classList.toggle('dark', dark);
  root.setAttribute('data-mode', dark ? 'dark' : 'light');
  window.MDV_THEME_BOOT = { applyMode: function (m) {
    var d = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    root.classList.toggle('dark', d);
    root.setAttribute('data-mode', d ? 'dark' : 'light');
  }};
})();
</script>
</head>
<body class="min-h-dvh bg-background text-foreground antialiased">

<div class="md-admin-shell">
  <!-- Sidebar -->
  <aside class="md-admin-sidebar" id="md-admin-sidebar">
    <div class="flex h-14 items-center gap-2 border-b border-sidebar-border px-4">
      <span class="grid h-7 w-7 place-items-center rounded-md bg-primary text-primary-foreground"><?= Icons::svg('shield', 'h-4 w-4') ?></span>
      <span class="text-sm font-semibold tracking-tight">MD.Viewer <span class="text-muted-foreground font-normal">Admin</span></span>
      <button type="button" class="md-icon-btn ml-auto lg:hidden" data-admin-sidebar-close aria-label="Close menu"><?= Icons::svg('x', 'h-4 w-4') ?></button>
    </div>
    <nav class="flex-1 overflow-y-auto p-3" aria-label="Admin">
      <?php foreach ($nav as $group => $links): ?>
      <p class="mt-3 mb-1 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground first:mt-0"><?= e($group) ?></p>
      <div class="flex flex-col gap-0.5">
        <?php foreach ($links as $link): ?>
        <a href="<?= e($req->url(['page' => $link['page']])) ?>" class="md-sidebar-link <?= $activePage === $link['page'] ? 'md-sidebar-link-active' : '' ?>">
          <?= Icons::svg($link['icon'], 'h-4 w-4 shrink-0') ?><span><?= e($link['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </nav>
    <div class="border-t border-sidebar-border p-3">
      <a href="<?= e(rtrim($adminJsConfig['viewerUrl'], '/') ?: '/') ?>" class="md-sidebar-link" target="_blank" rel="noopener">
        <?= Icons::svg('external-link', 'h-4 w-4 shrink-0') ?><span>View site</span>
      </a>
      <p class="mt-2 px-2 text-[10px] text-muted-foreground">v<?= e($adminJsConfig['version']) ?> · PHP <?= e($adminJsConfig['phpVersion']) ?></p>
    </div>
  </aside>
  <div class="md-admin-sidebar-backdrop" data-admin-sidebar-backdrop hidden></div>

  <!-- Main column -->
  <div class="md-admin-main">
    <header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-border/70 bg-background/85 px-4 backdrop-blur-xl lg:px-6">
      <button type="button" class="md-icon-btn lg:hidden" data-admin-sidebar-open aria-label="Open menu"><?= Icons::svg('panel-left', 'h-5 w-5') ?></button>
      <h1 class="text-sm font-semibold tracking-tight"><?= e($pageTitle ?? 'Admin') ?></h1>
      <div class="ml-auto flex items-center gap-1">
        <div class="relative" data-dropdown>
          <button type="button" class="md-icon-btn" data-dropdown-trigger aria-label="Toggle theme" aria-haspopup="menu" aria-expanded="false">
            <span class="dark:hidden"><?= Icons::svg('sun', 'h-[1.1rem] w-[1.1rem]') ?></span>
            <span class="hidden dark:inline"><?= Icons::svg('moon', 'h-[1.1rem] w-[1.1rem]') ?></span>
          </button>
          <div class="md-dropdown-menu" data-dropdown-menu role="menu" hidden>
            <button type="button" role="menuitem" class="md-dropdown-item" data-mode-select="light"><?= Icons::svg('sun', 'h-4 w-4') ?>Light</button>
            <button type="button" role="menuitem" class="md-dropdown-item" data-mode-select="dark"><?= Icons::svg('moon', 'h-4 w-4') ?>Dark</button>
            <button type="button" role="menuitem" class="md-dropdown-item" data-mode-select="system"><?= Icons::svg('monitor', 'h-4 w-4') ?>System</button>
          </div>
        </div>
        <?php if (!empty($auth) && $auth->isAuthenticated()): ?>
        <div class="relative" data-dropdown>
          <button type="button" class="md-btn md-btn-ghost md-btn-sm" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false">
            <?= Icons::svg('user', 'h-4 w-4') ?><span class="hidden sm:inline"><?= e($auth->user()) ?></span><?= Icons::svg('chevron-down', 'h-3.5 w-3.5') ?>
          </button>
          <div class="md-dropdown-menu" data-dropdown-menu role="menu" hidden>
            <button type="button" role="menuitem" class="md-dropdown-item text-destructive" data-admin-logout><?= Icons::svg('log-out', 'h-4 w-4') ?>Sign out</button>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </header>

    <main class="p-4 lg:p-6">
      <?php foreach ($flashes ?? [] as $flash): ?>
      <div class="md-flash md-flash-<?= e($flash['type']) ?> mb-4"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
      <?= $this->yield('content') ?>
    </main>
  </div>
</div>

<ol id="md-toasts" class="md-toaster" aria-live="polite"></ol>

<script nonce="<?= e($nonce) ?>">window.MDV_ADMIN = <?= json_encode($adminJsConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= e($app->tpl->asset('js/app.js')) ?>" defer></script>
<script src="<?= e($app->tpl->asset('js/admin.js')) ?>" defer></script>
</body>
</html>
