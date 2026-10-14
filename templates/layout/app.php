<?php
/**
 * MD.Scribe 3.0 — main layout (viewer + pages).
 *
 * Design system: shadcn/ui token model on Tailwind v4. The <html> element
 * carries data-neutral / data-accent / data-radius / data-font and the .dark
 * class; an inline bootstrap script (nonce'd, CSP-safe) applies the stored
 * theme BEFORE first paint so there is no flash and no CDN, ever.
 *
 * @var MdViewer\Core\App $app
 * @var string $pageTitle
 * @var string $pageDescription
 * @var array<string, mixed> $jsConfig
 */

use MdViewer\View\Icons;

$themeDefault = $app->config->str('THEME_DEFAULT');
$modeDefault = $app->config->str('MODE_DEFAULT');
$siteName = $app->config->str('SITE_NAME');
$req = $app->request;
$nonce = $app->nonce();
$adminEnabled = $app->config->bool('ADMIN_ENABLED');
$searchEnabled = $app->config->bool('FEATURE_SEARCH');
$paletteEnabled = $app->config->bool('FEATURE_COMMAND_PALETTE') && $searchEnabled;
$progressEnabled = $app->flags->enabled('FEATURE_READING_PROGRESS');
$flash = [];
$tocHtmlForSheet = '';
if (isset($result) && $result instanceof MdViewer\Markdown\RenderResult && ($tocEnabled ?? true)) {
    $tocHtmlForSheet = $result->tocHtml;
}
?>
<!doctype html>
<html lang="en" class="<?= e($modeDefault === 'dark' ? 'dark' : '') ?>" data-neutral="zinc" data-accent="default" data-radius="md" data-font="inter" data-theme-preset="<?= e($themeDefault) ?>" data-mode-default="<?= e($modeDefault) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle ?? $siteName) ?></title>
<meta name="description" content="<?= e($pageDescription ?? '') ?>">
<meta name="generator" content="MD.Scribe <?= e(MdViewer\Core\Version::VERSION) ?>">
<meta name="color-scheme" content="light dark">
<meta property="og:title" content="<?= e($pageTitle ?? $siteName) ?>">
<meta property="og:description" content="<?= e($pageDescription ?? '') ?>">
<meta property="og:type" content="article">
<link rel="icon" href="<?= e($req->assetUrl('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/themes.css')) ?>">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/typography.css')) ?>">
<?php if (!empty($customCss ?? '')): ?>
<style nonce="<?= e($nonce) ?>"><?= $customCss ?></style>
<?php endif; ?>
<?php if ($app->config->bool('RSS_ENABLED')): ?>
<link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> feed" href="<?= e($req->url(['page' => 'feed'])) ?>">
<?php endif; ?>
<script nonce="<?= e($nonce) ?>">
/* MD.Scribe theme bootstrap — runs before first paint: stored theme → root
   attributes + .dark class; falls back to the server default, then to the
   OS preference. No CDN, no flash, no layout shift. */
(function () {
  var root = document.documentElement;
  var stored = null;
  try { stored = JSON.parse(localStorage.getItem('mdv.theme') || 'null'); } catch (e) {}
  var preset = (stored && stored.preset) || root.getAttribute('data-theme-preset') || 'zinc';
  var neutral = (stored && stored.neutral) || null;
  var accent = (stored && stored.accent) || null;
  var radius = (stored && stored.radius) || 'md';
  var font = (stored && stored.font) || 'inter';
  var mode = (stored && stored.mode) || root.getAttribute('data-mode-default') || 'system';
  if (neutral) root.setAttribute('data-neutral', neutral);
  if (accent) root.setAttribute('data-accent', accent);
  root.setAttribute('data-radius', radius);
  root.setAttribute('data-font', font);
  function applyMode(m) {
    var dark = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    root.classList.toggle('dark', dark);
    root.setAttribute('data-mode', dark ? 'dark' : 'light');
  }
  applyMode(mode);
  root.setAttribute('data-mode-pref', mode);
  try {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    mq.addEventListener('change', function () {
      var s = null; try { s = JSON.parse(localStorage.getItem('mdv.theme') || 'null'); } catch (e) {}
      if (!s || !s.mode || s.mode === 'system') applyMode('system');
    });
  } catch (e) {}
  window.MDV_THEME_BOOT = { preset: preset, neutral: neutral, accent: accent, radius: radius, font: font, mode: mode, applyMode: applyMode };
})();
</script>
</head>
<body class="min-h-dvh bg-background text-foreground antialiased [text-rendering:optimizeLegibility] selection:bg-primary/20" data-page="<?= e($bodyPage ?? 'page') ?>">

<a href="#md-main" class="sr-only focus:not-sr-only focus:absolute focus:z-[100] focus:m-2 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground">Skip to content</a>

<!-- ══ Header ═══════════════════════════════════════════════════════════ -->
<header class="sticky top-0 z-40 w-full border-b border-border/70 bg-background/80 backdrop-blur-xl supports-[backdrop-filter]:bg-background/60" id="md-header">
  <?php if ($progressEnabled): ?>
  <div class="absolute inset-x-0 bottom-0 h-px bg-transparent" aria-hidden="true">
    <div id="md-progress" class="h-px w-0 bg-primary transition-[width] duration-150 ease-out"></div>
  </div>
  <?php endif; ?>
  <div class="mx-auto flex h-14 w-full max-w-[1600px] items-center gap-2 px-3 sm:px-4 lg:px-6">
    <button type="button" id="md-sidebar-toggle" class="md-icon-btn lg:hidden -ml-1" aria-label="Toggle navigation" data-action="sidebar">
      <?= Icons::svg('panel-left', 'h-5 w-5') ?>
    </button>

    <a href="<?= e($req->url()) ?>" class="flex items-center gap-2.5 rounded-lg px-1 py-1 font-semibold tracking-tight hover:opacity-80 transition-opacity">
      <span class="grid h-7 w-7 place-items-center rounded-md bg-primary text-primary-foreground shadow-sm">
        <?= Icons::svg('book-marked', 'h-4 w-4') ?>
      </span>
      <span class="hidden sm:inline text-sm"><?= e($siteName) ?></span>
    </a>

    <nav class="ml-1 hidden items-center gap-1 md:flex" aria-label="Main">
      <a href="<?= e($req->url(['page' => 'browser'])) ?>" class="md-nav-link <?= ($bodyPage ?? '') === 'browser' ? 'md-nav-link-active' : '' ?>">
        <?= Icons::svg('files', 'h-4 w-4') ?><span>Library</span>
      </a>
      <?php if ($searchEnabled): ?>
      <a href="<?= e($req->url(['page' => 'search'])) ?>" class="md-nav-link <?= ($bodyPage ?? '') === 'search' ? 'md-nav-link-active' : '' ?>">
        <?= Icons::svg('search', 'h-4 w-4') ?><span>Search</span>
      </a>
      <?php endif; ?>
      <a href="<?= e($req->url(['page' => 'themes'])) ?>" class="md-nav-link <?= ($bodyPage ?? '') === 'themes' ? 'md-nav-link-active' : '' ?>">
        <?= Icons::svg('palette', 'h-4 w-4') ?><span>Themes</span>
      </a>
    </nav>

    <div class="ml-auto flex items-center gap-1">
      <?php if ($searchEnabled): ?>
      <button type="button" id="md-search-trigger" class="md-search-trigger" aria-label="Search documents" data-action="search">
        <?= Icons::svg('search', 'h-4 w-4 shrink-0') ?>
        <span class="hidden sm:inline text-muted-foreground">Search docs…</span>
        <kbd class="md-kbd ml-auto hidden sm:inline-flex"><?= Icons::svg('command', 'h-3 w-3') ?>K</kbd>
      </button>
      <?php endif; ?>

      <!-- Mode toggle: light / dark / system (shadcn dropdown-menu) -->
      <div class="relative" data-dropdown>
        <button type="button" class="md-icon-btn" data-dropdown-trigger aria-label="Toggle theme" aria-haspopup="menu" aria-expanded="false">
          <span class="dark:hidden"><?= Icons::svg('sun', 'h-[1.15rem] w-[1.15rem]') ?></span>
          <span class="hidden dark:inline"><?= Icons::svg('moon', 'h-[1.15rem] w-[1.15rem]') ?></span>
        </button>
        <div class="md-dropdown-menu" data-dropdown-menu role="menu" hidden>
          <button type="button" role="menuitem" class="md-dropdown-item" data-mode-select="light"><?= Icons::svg('sun', 'h-4 w-4') ?>Light</button>
          <button type="button" role="menuitem" class="md-dropdown-item" data-mode-select="dark"><?= Icons::svg('moon', 'h-4 w-4') ?>Dark</button>
          <button type="button" role="menuitem" class="md-dropdown-item" data-mode-select="system"><?= Icons::svg('monitor', 'h-4 w-4') ?>System</button>
          <div class="md-dropdown-separator"></div>
          <a class="md-dropdown-item" role="menuitem" href="<?= e($req->url(['page' => 'themes'])) ?>"><?= Icons::svg('palette', 'h-4 w-4') ?>Theme catalog…</a>
        </div>
      </div>

      <a href="<?= e($req->url(['page' => 'settings'])) ?>" class="md-icon-btn" aria-label="Settings" title="Settings">
        <?= Icons::svg('settings', 'h-[1.15rem] w-[1.15rem]') ?>
      </a>

      <?php if ($adminEnabled): ?>
      <a href="<?= e(rtrim($req->assetBase(), '/') . '/admin.php') ?>" class="md-icon-btn" aria-label="Admin panel" title="Admin panel">
        <?= Icons::svg('shield', 'h-[1.15rem] w-[1.15rem]') ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ══ Main ═════════════════════════════════════════════════════════════ -->
<main id="md-main" class="mx-auto w-full max-w-[1600px] px-3 sm:px-4 lg:px-6">
<?= $this->yield('content') ?>
</main>

<!-- ══ Footer ═══════════════════════════════════════════════════════════ -->
<footer class="mt-16 border-t border-border/70 py-8">
  <div class="mx-auto flex w-full max-w-[1600px] flex-col items-center justify-between gap-3 px-4 text-xs text-muted-foreground sm:flex-row lg:px-6">
    <p>Powered by <a class="md-link" href="https://github.com/paulmann/MD.Scribe" target="_blank" rel="noopener noreferrer">MD.Scribe</a> <?= e(MdViewer\Core\Version::VERSION) ?> · MIT License</p>
    <p class="flex items-center gap-3">
      <?php if ($searchEnabled): ?><a class="md-link" href="<?= e($req->url(['page' => 'search'])) ?>">Search</a><?php endif; ?>
      <?php if ($app->config->bool('SITEMAP_ENABLED')): ?><a class="md-link" href="<?= e($req->url(['page' => 'sitemap'])) ?>">Sitemap</a><?php endif; ?>
      <?php if ($app->config->bool('RSS_ENABLED')): ?><a class="md-link" href="<?= e($req->url(['page' => 'feed'])) ?>">RSS</a><?php endif; ?>
      <a class="md-link" href="<?= e($req->url(['page' => 'themes'])) ?>">Themes</a>
    </p>
  </div>
</footer>

<?php if ($paletteEnabled): ?>
<!-- ══ Command palette (shadcn command dialog) ══════════════════════════ -->
<div id="md-palette" class="md-dialog-backdrop" data-palette hidden role="dialog" aria-modal="true" aria-label="Command palette">
  <div class="md-dialog md-palette-dialog" data-palette-dialog>
    <div class="md-palette-input-row">
      <?= Icons::svg('search', 'h-4 w-4 shrink-0 text-muted-foreground') ?>
      <input id="md-palette-input" class="md-palette-input" type="text" placeholder="Search documents, jump to sections, run actions…" autocomplete="off" autocorrect="off" spellcheck="false" role="combobox" aria-expanded="false" aria-controls="md-palette-list">
      <kbd class="md-kbd">ESC</kbd>
    </div>
    <div id="md-palette-list" class="md-palette-list" role="listbox" aria-label="Results"></div>
    <div class="md-palette-footer">
      <span><kbd class="md-kbd">↑↓</kbd> navigate</span>
      <span><kbd class="md-kbd">↵</kbd> open</span>
      <span class="ml-auto text-muted-foreground"><?= e($siteName) ?></span>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ Mobile navigation sheet ══════════════════════════════════════════ -->
<div id="md-sheet" class="md-sheet-backdrop" hidden>
  <div class="md-sheet" role="dialog" aria-modal="true" aria-label="Navigation">
    <div class="flex items-center justify-between border-b border-border px-4 py-3">
      <span class="text-sm font-semibold"><?= e($siteName) ?></span>
      <button type="button" class="md-icon-btn" data-sheet-close aria-label="Close navigation"><?= Icons::svg('x', 'h-4 w-4') ?></button>
    </div>
    <nav class="flex flex-col gap-1 p-3" aria-label="Mobile">
      <a href="<?= e($req->url()) ?>" class="md-sheet-link"><?= Icons::svg('house', 'h-4 w-4') ?>Home</a>
      <a href="<?= e($req->url(['page' => 'browser'])) ?>" class="md-sheet-link"><?= Icons::svg('files', 'h-4 w-4') ?>Library</a>
      <?php if ($searchEnabled): ?><a href="<?= e($req->url(['page' => 'search'])) ?>" class="md-sheet-link"><?= Icons::svg('search', 'h-4 w-4') ?>Search</a><?php endif; ?>
      <a href="<?= e($req->url(['page' => 'themes'])) ?>" class="md-sheet-link"><?= Icons::svg('palette', 'h-4 w-4') ?>Themes</a>
      <a href="<?= e($req->url(['page' => 'settings'])) ?>" class="md-sheet-link"><?= Icons::svg('settings', 'h-4 w-4') ?>Settings</a>
      <?php if ($adminEnabled): ?><a href="<?= e(rtrim($req->assetBase(), '/') . '/admin.php') ?>" class="md-sheet-link"><?= Icons::svg('shield', 'h-4 w-4') ?>Admin</a><?php endif; ?>
    </nav>
    <?php if (($tocHtmlForSheet ?? '') !== ''): ?>
    <div class="border-t border-border p-3">
      <p class="mb-2 px-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">On this page</p>
      <div class="md-sheet-toc max-h-[45dvh] overflow-y-auto"><?= $tocHtmlForSheet ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ Toasts (sonner-style) ════════════════════════════════════════════ -->
<ol id="md-toasts" class="md-toaster" aria-live="polite" aria-label="Notifications"></ol>

<script nonce="<?= e($nonce) ?>">window.MDV_CONFIG = <?= json_encode($jsConfig ?? [], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= e($app->tpl->asset('js/app.js')) ?>" defer></script>
<?php if (!empty($extraScripts ?? [])): foreach ($extraScripts as $script): ?>
<script src="<?= e($script) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
