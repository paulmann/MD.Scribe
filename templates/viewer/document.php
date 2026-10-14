<?php
/**
 * MD.Viewer 3.0 — document view.
 *
 * Three-column reading layout: folder sidebar (lg+), article, sticky TOC
 * (xl+). Mobile gets a navigation sheet and a collapsible TOC. All reading
 * preferences (width / font size / line height) apply via CSS variables set
 * from JS (CSSOM — CSP-friendly, no inline styles).
 *
 * @var MdViewer\Core\App $app
 * @var MdViewer\Markdown\RenderResult $result
 * @var string $relative
 * @var string $docDir
 * @var bool $isHome
 * @var array $breadcrumbs
 * @var array $neighbours
 * @var array $flags
 * @var bool $tocEnabled
 */

use MdViewer\View\Icons;

$req = $app->request;
$bodyPage = 'doc';
$fm = $result->frontMatter;
$tags = is_array($fm['tags'] ?? null) ? array_slice($fm['tags'], 0, 8) : [];
$authors = is_array($fm['authors'] ?? null) ? $fm['authors'] : (isset($fm['author']) ? [$fm['author']] : []);
$docDate = isset($fm['date']) ? (string) $fm['date'] : '';
$showCopy = $app->flags->enabled('FEATURE_COPY_BUTTON');
$showDownloadBtn = $app->flags->enabled('FEATURE_DOWNLOAD_BUTTON');

// Folder sidebar: siblings + folders of the current directory
$sidebarFiles = [];
$sidebarFolders = [];
$scan = $app->repo()->scan($docDir === '' ? null : $docDir);
$prefix = $docDir === '' ? '' : $docDir . '/';
foreach ($scan as $f) {
    $p = (string) $f['path'];
    if ($prefix !== '' && !str_starts_with($p, $prefix)) { continue; }
    $rest = substr($p, strlen($prefix));
    if (str_contains($rest, '/')) {
        $folder = explode('/', $rest)[0];
        $sidebarFolders[$folder] = ($sidebarFolders[$folder] ?? 0) + 1;
    } else {
        $sidebarFiles[] = $f;
    }
}
uksort($sidebarFolders, 'strnatcasecmp');
usort($sidebarFiles, static fn ($a, $b) => strnatcasecmp((string) $a['path'], (string) $b['path']));
$sidebarFiles = array_slice($sidebarFiles, 0, 60);

$pageTitle = $pageTitle ?? ($result->title ?? 'Document');
$pageDescription = $pageDescription ?? $result->description;
$jsConfig = $jsConfig ?? [];
$tocHtmlForSheet = $tocEnabled ? $result->tocHtml : '';
?>
<?php $this->extend('layout/app'); ?>
<?php $this->section('content'); ?>

<div class="flex items-start gap-8 py-6 lg:py-8">

  <!-- ══ Left: folder sidebar (desktop) ═══════════════════════════════ -->
  <aside class="sticky top-[4.5rem] hidden max-h-[calc(100dvh-6rem)] w-60 shrink-0 overflow-y-auto pb-8 lg:block" aria-label="Folder navigation">
    <nav class="flex flex-col gap-0.5 text-sm">
      <a href="<?= e($req->url(['page' => 'browser'])) ?>" class="md-sidebar-link <?= $docDir === '' ? 'md-sidebar-link-active' : '' ?>">
        <?= Icons::svg('library', 'h-4 w-4') ?><span>Library</span>
      </a>
      <?php if ($docDir !== ''): ?>
      <a href="<?= e($req->url(['page' => 'browser', 'dir' => dirname($docDir) === '.' ? '' : dirname($docDir)])) ?>" class="md-sidebar-link">
        <?= Icons::svg('arrow-left', 'h-4 w-4') ?><span>Up one level</span>
      </a>
      <?php endif; ?>
      <?php foreach ($sidebarFolders as $folder => $count): ?>
      <a href="<?= e($req->url(['page' => 'browser', 'dir' => $prefix . $folder])) ?>" class="md-sidebar-link">
        <?= Icons::svg('folder', 'h-4 w-4') ?><span class="truncate"><?= e($folder) ?></span>
        <span class="md-sidebar-badge"><?= (int) $count ?></span>
      </a>
      <?php endforeach; ?>
      <?php if ($sidebarFiles !== []): ?>
      <p class="mt-4 mb-1 px-2 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">In this folder</p>
      <?php foreach ($sidebarFiles as $f): $isActive = $f['path'] === $relative; ?>
      <a href="<?= e($req->url(['file' => $f['path']])) ?>" class="md-sidebar-link <?= $isActive ? 'md-sidebar-link-active' : '' ?>">
        <?= Icons::svg('file-text', 'h-4 w-4 shrink-0') ?><span class="truncate"><?= e(preg_replace('/\.md$/i', '', basename((string) $f['path']))) ?></span>
      </a>
      <?php endforeach; endif; ?>
    </nav>
  </aside>

  <!-- ══ Center: article ══════════════════════════════════════════════ -->
  <div class="min-w-0 flex-1">
    <div class="mx-auto w-full md-reader" id="md-reader" data-doc="<?= e($relative) ?>">

      <!-- Breadcrumbs -->
      <nav aria-label="Breadcrumb" class="mb-5 hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
          <?php if ($i > 0): ?><?= Icons::svg('chevron-right', 'h-3 w-3 shrink-0 opacity-60') ?><?php endif; ?>
          <?php if ($crumb['isFile']): ?>
            <span class="font-medium text-foreground truncate max-w-[16rem]"><?= e($crumb['name']) ?></span>
          <?php else: ?>
            <a class="md-crumb truncate" href="<?= e($crumb['path'] === '' ? $req->url(['page' => 'browser']) : $req->url(['page' => 'browser', 'dir' => $crumb['path']])) ?>"><?= e($crumb['name']) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>

      <!-- Hero -->
      <header class="mb-8 border-b border-border/60 pb-6">
        <?php if (!empty($result->eyebrow)): ?>
        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-primary"><?= e($result->eyebrow) ?></p>
        <?php endif; ?>
        <h1 class="text-3xl font-bold tracking-tight text-balance sm:text-4xl md:text-[2.6rem] md:leading-[1.15]"><?= e($result->title ?? '') ?></h1>
        <?php if ($result->description !== '' && (string) ($fm['description'] ?? '') !== ''): ?>
        <p class="mt-3 max-w-2xl text-base text-muted-foreground text-pretty"><?= e($result->description) ?></p>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
          <span class="inline-flex items-center gap-1.5"><?= Icons::svg('clock', 'h-3.5 w-3.5') ?><?= (int) $result->readingMinutes ?> min read</span>
          <span class="inline-flex items-center gap-1.5"><?= Icons::svg('type', 'h-3.5 w-3.5') ?><?= number_format($result->words) ?> words</span>
          <span class="inline-flex items-center gap-1.5" title="<?= e($modified ?? '') ?>"><?= Icons::svg('calendar', 'h-3.5 w-3.5') ?>Updated <?= e($modified ?? '') ?></span>
          <span class="inline-flex items-center gap-1.5"><?= Icons::svg('hard-drive', 'h-3.5 w-3.5') ?><?= e($size ?? '') ?></span>
          <?php foreach ($authors as $author): ?>
          <span class="inline-flex items-center gap-1.5"><?= Icons::svg('user', 'h-3.5 w-3.5') ?><?= e((string) $author) ?></span>
          <?php endforeach; ?>
          <?php if ($docDate !== ''): ?>
          <span class="inline-flex items-center gap-1.5"><?= Icons::svg('calendar-days', 'h-3.5 w-3.5') ?><?= e($docDate) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($tags !== []): ?>
        <div class="mt-3 flex flex-wrap gap-1.5">
          <?php foreach ($tags as $tag): ?>
          <span class="md-tag"><?= Icons::svg('hash', 'h-3 w-3') ?><?= e((string) $tag) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="mt-5 flex flex-wrap items-center gap-1.5">
          <a href="<?= e($req->url(['file' => $relative])) ?>#md-main" class="sr-only">Skip toolbar</a>
          <?php if ($showCopy): ?>
          <button type="button" class="md-btn md-btn-outline md-btn-sm" data-copy-type="source" data-copy-source="<?= e($req->url(['file' => $relative, 'download' => 1])) ?>">
            <?= Icons::svg('copy', 'h-3.5 w-3.5') ?>Copy source
          </button>
          <?php endif; ?>
          <?php if ($showDownloadBtn): ?>
          <a class="md-btn md-btn-outline md-btn-sm" href="<?= e($req->url(['file' => $relative, 'download' => 1])) ?>" download>
            <?= Icons::svg('download', 'h-3.5 w-3.5') ?>Download .md
          </a>
          <?php endif; ?>
          <button type="button" class="md-btn md-btn-ghost md-btn-sm" data-action="print">
            <?= Icons::svg('printer', 'h-3.5 w-3.5') ?>Print
          </button>

          <!-- Reading preferences popover -->
          <div class="relative ml-auto" data-dropdown>
            <button type="button" class="md-btn md-btn-ghost md-btn-sm" data-dropdown-trigger aria-label="Reading preferences" aria-haspopup="menu" aria-expanded="false">
              <?= Icons::svg('sliders-horizontal', 'h-3.5 w-3.5') ?><span class="hidden sm:inline">Reading</span>
            </button>
            <div class="md-dropdown-menu md-popover-wide" data-dropdown-menu role="menu" hidden>
              <p class="px-3 pt-2 pb-1 text-xs font-medium text-muted-foreground">Text size</p>
              <div class="md-stepper" data-pref="fontSize">
                <button type="button" class="md-icon-btn" data-step="-1" aria-label="Decrease text size"><?= Icons::svg('minus', 'h-4 w-4') ?></button>
                <output class="md-stepper-value" data-value>100%</output>
                <button type="button" class="md-icon-btn" data-step="1" aria-label="Increase text size"><?= Icons::svg('plus', 'h-4 w-4') ?></button>
              </div>
              <p class="px-3 pt-2 pb-1 text-xs font-medium text-muted-foreground">Page width</p>
              <div class="md-stepper" data-pref="width">
                <button type="button" class="md-icon-btn" data-step="-1" aria-label="Narrower page"><?= Icons::svg('minimize', 'h-4 w-4') ?></button>
                <output class="md-stepper-value" data-value>Comfortable</output>
                <button type="button" class="md-icon-btn" data-step="1" aria-label="Wider page"><?= Icons::svg('maximize', 'h-4 w-4') ?></button>
              </div>
              <p class="px-3 pt-2 pb-1 text-xs font-medium text-muted-foreground">Line height</p>
              <div class="md-stepper" data-pref="lineHeight">
                <button type="button" class="md-icon-btn" data-step="-1" aria-label="Tighter lines"><?= Icons::svg('minus', 'h-4 w-4') ?></button>
                <output class="md-stepper-value" data-value>1.75</output>
                <button type="button" class="md-icon-btn" data-step="1" aria-label="Looser lines"><?= Icons::svg('plus', 'h-4 w-4') ?></button>
              </div>
              <div class="md-dropdown-separator"></div>
              <a class="md-dropdown-item" role="menuitem" href="<?= e($req->url(['page' => 'settings'])) ?>"><?= Icons::svg('settings', 'h-4 w-4') ?>All settings…</a>
            </div>
          </div>

          <button type="button" class="md-btn md-btn-ghost md-btn-sm xl:hidden" data-action="mobile-toc" aria-label="Table of contents">
            <?= Icons::svg('list', 'h-3.5 w-3.5') ?>Contents
          </button>
        </div>
      </header>

      <!-- Article body -->
      <article class="md-body<?= ($app->flags->get('PARAGRAPH_BREAK_STYLE') === 'paragraph') ? ' md-body--paragraph' : '' ?>" id="md-article"
        data-has-mermaid="<?= $result->hasMermaid ? '1' : '0' ?>"
        data-has-math="<?= $result->hasMath ? '1' : '0' ?>"
        data-glossary="<?= $result->glossaryTerms > 0 ? '1' : '0' ?>">
<?= $result->html ?>
      </article>

      <!-- Prev / next -->
      <?php if (!empty($neighbours['prev']) || !empty($neighbours['next'])): ?>
      <nav class="mt-14 grid gap-3 sm:grid-cols-2" aria-label="Document navigation">
        <?php if (!empty($neighbours['prev'])): ?>
        <a class="md-pager md-pager-prev" href="<?= e($req->url(['file' => $neighbours['prev']['path']])) ?>">
          <span class="md-pager-label"><?= Icons::svg('arrow-left', 'h-3.5 w-3.5') ?>Previous</span>
          <span class="md-pager-title"><?= e($neighbours['prev']['title']) ?></span>
        </a>
        <?php else: ?><span class="hidden sm:block"></span><?php endif; ?>
        <?php if (!empty($neighbours['next'])): ?>
        <a class="md-pager md-pager-next" href="<?= e($req->url(['file' => $neighbours['next']['path']])) ?>">
          <span class="md-pager-label">Next<?= Icons::svg('arrow-right', 'h-3.5 w-3.5') ?></span>
          <span class="md-pager-title"><?= e($neighbours['next']['title']) ?></span>
        </a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

      <p class="mt-10 text-center text-xs text-muted-foreground">
        Last modified <?= e($modified ?? '—') ?> · <?= e($size ?? '') ?>
        <?php if ($app->config->bool('ADMIN_ENABLED')): ?>
        · <a class="md-link" href="<?= e(rtrim($req->assetBase(), '/') . '/admin.php') ?>?page=editor&amp;file=<?= e(rawurlencode($relative)) ?>">Edit in admin</a>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <!-- ══ Right: sticky TOC (desktop) ══════════════════════════════════ -->
  <?php if ($tocEnabled && $result->tocHtml !== ''): ?>
  <aside class="sticky top-[4.5rem] hidden max-h-[calc(100dvh-6rem)] w-64 shrink-0 overflow-y-auto pb-8 xl:block" aria-label="On this page">
    <p class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">On this page</p>
    <div id="md-toc-sidebar" class="md-toc-sidebar" data-scrollspy>
      <?= $result->tocHtml ?>
    </div>
    <?php if ($result->glossaryTerms > 0): ?>
    <p class="mt-6 border-t border-border/60 px-2 pt-4 text-[11px] leading-relaxed text-muted-foreground">
      <?= Icons::svg('info', 'h-3 w-3 inline-block align-[-1px] mr-1') ?>
      This document has a glossary — hover or tap <span class="glossary-term-demo">dotted terms</span> for definitions.
    </p>
    <?php endif; ?>
  </aside>
  <?php endif; ?>
</div>

<!-- Mobile TOC drawer -->
<div id="md-toc-mobile" class="md-sheet-backdrop" hidden>
  <div class="md-sheet md-sheet-bottom" role="dialog" aria-modal="true" aria-label="Table of contents">
    <div class="flex items-center justify-between border-b border-border px-4 py-3">
      <span class="text-sm font-semibold">On this page</span>
      <button type="button" class="md-icon-btn" data-sheet-close aria-label="Close contents"><?= Icons::svg('x', 'h-4 w-4') ?></button>
    </div>
    <div class="max-h-[70dvh] overflow-y-auto p-3"><?= $tocEnabled ? $result->tocHtml : '' ?></div>
  </div>
</div>

<!-- Image lightbox -->
<div id="md-lightbox" class="md-lightbox" hidden role="dialog" aria-modal="true" aria-label="Image preview">
  <button type="button" class="md-lightbox-close" data-lightbox-close aria-label="Close image"><?= Icons::svg('x', 'h-5 w-5') ?></button>
  <img id="md-lightbox-img" src="" alt="">
  <p id="md-lightbox-caption" class="md-lightbox-caption"></p>
</div>
<?php $this->end(); ?>
