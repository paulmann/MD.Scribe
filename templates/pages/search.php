<?php
/**
 * MD.Viewer 3.0 — full-text search page.
 *
 * Server-rendered results with live client-side re-querying (fetch to
 * ?api=search) so typing feels instant without a framework.
 *
 * @var MdViewer\Core\App $app
 * @var string $q
 * @var array{query: string, took_ms: int, total: int, results: list<array{path: string, title: string, snippet: string, score: int, modified: int}>}|null $results
 */

use MdViewer\View\Icons;

$req = $app->request;
$bodyPage = 'search';
?>
<?php $this->extend('layout/app'); ?>
<?php $this->section('content'); ?>

<div class="mx-auto max-w-3xl py-8 lg:py-12">
  <header class="mb-6">
    <h1 class="text-3xl font-bold tracking-tight"><?= Icons::svg('search', 'h-7 w-7 mr-2 inline-block align-[-4px] text-primary') ?>Search</h1>
    <p class="mt-2 text-muted-foreground">Full-text search across every document in the library. No database, no cloud.</p>
  </header>

  <form class="md-input-wrap md-input-lg mb-6" action="<?= e($req->scriptUrl()) ?>" method="get" role="search" data-search-form>
    <?= Icons::svg('search', 'h-5 w-5 text-muted-foreground') ?>
    <input type="search" name="q" value="<?= e($q) ?>" class="md-input md-input-lg" placeholder="Search titles, headings and full text…" aria-label="Search query" autofocus data-search-input>
    <input type="hidden" name="page" value="search">
    <button type="submit" class="md-btn md-btn-primary md-btn-sm mr-1">Search</button>
  </form>

  <div data-search-results aria-live="polite">
  <?php if ($q === ''): ?>
    <div class="md-card grid place-items-center gap-2 px-6 py-14 text-center">
      <span class="grid h-12 w-12 place-items-center rounded-full bg-muted text-muted-foreground"><?= Icons::svg('file-search', 'h-6 w-6') ?></span>
      <p class="font-medium">Search the library</p>
      <p class="max-w-sm text-sm text-muted-foreground">
        Try a title, a heading, or any word that appears in a document. Press
        <kbd class="md-kbd">Ctrl</kbd>+<kbd class="md-kbd">K</kbd> anywhere to search without leaving the page.
      </p>
    </div>
  <?php elseif ($results !== null && $results['results'] === []): ?>
    <div class="md-card grid place-items-center gap-2 px-6 py-14 text-center">
      <span class="grid h-12 w-12 place-items-center rounded-full bg-muted text-muted-foreground"><?= Icons::svg('search', 'h-6 w-6') ?></span>
      <p class="font-medium">No results for “<?= e($q) ?>”</p>
      <p class="text-sm text-muted-foreground">Check the spelling, or try a broader term.</p>
    </div>
  <?php elseif ($results !== null): ?>
    <p class="mb-3 text-sm text-muted-foreground">
      <?= (int) $results['total'] ?> result<?= (int) $results['total'] === 1 ? '' : 's' ?> for “<?= e($q) ?>”
      <span class="opacity-70">· <?= (int) $results['took_ms'] ?> ms</span>
    </p>
    <div class="flex flex-col gap-2" data-result-list>
      <?php foreach ($results['results'] as $hit): ?>
      <a class="md-result-card" href="<?= e($req->url(['file' => $hit['path']])) ?>">
        <span class="md-result-head">
          <?= Icons::svg('file-text', 'h-4 w-4 shrink-0 text-primary') ?>
          <span class="truncate font-medium"><?= e((string) $hit['title']) ?></span>
        </span>
        <span class="md-result-path"><?= e((string) $hit['path']) ?></span>
        <?php if ((string) $hit['snippet'] !== ''): ?>
        <span class="md-result-snippet"><?= $hit['snippet'] /* pre-escaped + <mark> by SearchIndex */ ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
</div>
<?php $this->end(); ?>
