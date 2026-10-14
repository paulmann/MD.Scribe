<?php
/**
 * MD.Viewer 3.0 — tools: link checker, caches, feeds, index.php link.
 *
 * @var MdViewer\Core\App $app
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Tools';
$indexPhpExists = is_file(MdViewer\Core\Paths::root() . '/index.php');
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<div class="grid gap-4 lg:grid-cols-2">
  <!-- Link checker -->
  <div class="md-card">
    <div class="md-card-header">
      <h2 class="md-card-title"><?= Icons::svg('link-2', 'h-4 w-4 text-primary') ?>Link &amp; content integrity</h2>
      <button type="button" class="md-btn md-btn-primary md-btn-sm ml-auto" data-links-check><?= Icons::svg('scan-eye', 'h-3.5 w-3.5') ?>Run check</button>
    </div>
    <div class="md-card-body text-sm text-muted-foreground">
      <p>Finds broken relative <code class="md-code-inline">.md</code> links, dead anchors, missing images and orphaned documents (nothing links to them).</p>
      <div id="md-links-result" class="mt-3"></div>
    </div>
  </div>

  <!-- Caches -->
  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('database', 'h-4 w-4 text-primary') ?>Caches</h2></div>
    <div class="md-card-body flex flex-col gap-3 text-sm text-muted-foreground">
      <p>Clears the rendered-document cache and deletes the search index (it rebuilds on the next search).</p>
      <div>
        <button type="button" class="md-btn md-btn-outline md-btn-sm" data-admin-quick="cache.clear"><?= Icons::svg('refresh-ccw', 'h-3.5 w-3.5') ?>Clear all caches</button>
      </div>
    </div>
  </div>

  <!-- Feeds & discovery -->
  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('rss', 'h-4 w-4 text-primary') ?>Feeds &amp; discovery</h2></div>
    <div class="md-card-body flex flex-col gap-2 text-sm text-muted-foreground">
      <a class="md-action-row" href="<?= e($app->request->assetBase()) ?>/index.php?page=sitemap" target="_blank" rel="noopener"><?= Icons::svg('map', 'h-4 w-4') ?>sitemap.xml — every document, with lastmod</a>
      <a class="md-action-row" href="<?= e($app->request->assetBase()) ?>/index.php?page=feed" target="_blank" rel="noopener"><?= Icons::svg('rss', 'h-4 w-4') ?>feed.xml — 30 most recently modified documents</a>
      <p class="mt-2 text-xs">Both are generated on the fly and toggleable under Settings → Feeds.</p>
    </div>
  </div>

  <!-- Root entry -->
  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('house', 'h-4 w-4 text-primary') ?>Root entry point</h2></div>
    <div class="md-card-body text-sm text-muted-foreground">
      <p class="mb-2">
        v3 ships a real <code class="md-code-inline">index.php</code> at the project root (<?= $indexPhpExists ? '<span class="md-badge md-badge-success">present</span>' : '<span class="md-badge md-badge-warning">missing</span>' ?>),
        so the v2 "create index.php hard link" dance is no longer needed. For docroot-at-<code class="md-code-inline">public/</code>
        deployments the server config handles <code class="md-code-inline">/</code> directly.
      </p>
      <p class="text-xs">The <code class="md-code-inline">ALLOW_CREATE_INDEX_PHP_LINK</code> flag is kept for compatibility but has no destructive action in v3 — restoring v2 behaviour would mean deleting the shipped file, which the panel refuses to do.</p>
    </div>
  </div>
</div>
<?php $this->end(); ?>
