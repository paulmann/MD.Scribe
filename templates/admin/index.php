<?php
/**
 * MD.Viewer 3.0 — search index management.
 *
 * @var MdViewer\Core\App $app
 * @var array $stats
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Search index';
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<div class="grid gap-4 lg:grid-cols-3">
  <div class="md-card lg:col-span-2">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('file-search', 'h-4 w-4 text-primary') ?>Index status</h2></div>
    <div class="md-card-body">
      <dl class="md-deflist" id="md-index-stats">
        <div><dt>Documents indexed</dt><dd><?= number_format((int) ($stats['documents'] ?? 0)) ?></dd></div>
        <div><dt>Distinct terms</dt><dd><?= number_format((int) ($stats['terms'] ?? 0)) ?></dd></div>
        <div><dt>Built at</dt><dd><?= e((string) ($stats['built_at'] ?? 'never')) ?></dd></div>
        <div><dt>Index size</dt><dd><?= number_format(((int) ($stats['size_bytes'] ?? 0)) / 1024, 1) ?> KB</dd></div>
        <div><dt>Freshness</dt><dd><?= !empty($stats['fresh']) ? '<span class="md-badge md-badge-success">fresh</span>' : '<span class="md-badge md-badge-warning">stale — rebuilds on next search</span>' ?></dd></div>
      </dl>
      <p class="mt-4 text-sm text-muted-foreground">
        The index is a pure-PHP inverted index stored as JSON — no database. It rebuilds automatically
        when the corpus fingerprint changes, or on demand:
      </p>
      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="md-btn md-btn-primary md-btn-sm" data-admin-action="index.rebuild" data-refresh="md-index-stats"><?= Icons::svg('refresh-cw', 'h-3.5 w-3.5') ?>Rebuild now</button>
      </div>
    </div>
  </div>
  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('info', 'h-4 w-4 text-primary') ?>How search works</h2></div>
    <div class="md-card-body text-sm text-muted-foreground">
      <ul class="list-disc space-y-1.5 pl-4">
        <li>Titles and headings weigh more than body text; documents matching <em>all</em> query terms rank first.</li>
        <li>Snippets are generated live from the source file for the top results.</li>
        <li>CJK text is indexed as unigrams + bigrams, so Chinese/Japanese/Korean queries work.</li>
        <li>The command palette (Ctrl+K) uses the same index for typeahead.</li>
      </ul>
    </div>
  </div>
</div>
<?php $this->end(); ?>
