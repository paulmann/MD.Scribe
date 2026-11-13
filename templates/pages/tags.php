<?php
/**
 * MD.Scribe 3.3 — public tag index.
 *
 * Every tag declared in document frontmatter, with the documents that carry
 * it. `?tag=…` narrows the list to one tag.
 *
 * @var MdViewer\Core\App $app
 * @var array<string, list<array{path: string, title: string}>> $tags
 * @var string|null $selected
 * @var list<array{path: string, title: string}> $documents
 */

use MdViewer\View\Icons;

$req = $app->request;
$bodyPage = 'tags';

$tagCount = count($tags);
$paths = [];
foreach ($tags as $entries) {
    foreach ($entries as $entry) {
        $paths[$entry['path']] = true;
    }
}
$documentCount = count($paths);
?>
<?php $this->extend('layout/app'); ?>
<?php $this->section('content'); ?>

<div class="mx-auto max-w-3xl py-8 lg:py-12">
  <header class="mb-6">
    <h1 class="text-3xl font-bold tracking-tight">
      <?= Icons::svg('hash', 'h-7 w-7 mr-1 inline-block align-[-4px] text-primary') ?>Tags
    </h1>
    <p class="mt-2 text-muted-foreground">
      <?= $tagCount ?> <?= $tagCount === 1 ? 'tag' : 'tags' ?>
      across <?= $documentCount ?> <?= $documentCount === 1 ? 'document' : 'documents' ?>.
      Tags come from the <code class="md-code-inline">tags:</code> key in document frontmatter.
    </p>
  </header>

  <?php if ($tags === []): ?>
  <div class="md-card px-6 py-12 text-center">
    <p class="font-medium">No tags yet</p>
    <p class="mt-2 text-sm text-muted-foreground">
      Add <code class="md-code-inline">tags: [docs, guide]</code> (or a YAML block list) to the top of a document
      and it appears here on the next request.
    </p>
  </div>
  <?php else: ?>
  <div class="mb-8 flex flex-wrap gap-2" aria-label="All tags">
    <?php foreach ($tags as $tag => $entries): ?>
    <a class="md-tag <?= $selected === $tag ? 'md-tag-active' : '' ?>"
       href="<?= e($req->url(['page' => 'tags', 'tag' => $tag])) ?>"
       <?= $selected === $tag ? 'aria-current="page"' : '' ?>>
      <?= Icons::svg('hash', 'h-3 w-3') ?><?= e($tag) ?>
      <span class="ml-1 text-[10px] opacity-70"><?= count($entries) ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($selected !== null): ?>
  <section aria-labelledby="md-tag-heading">
    <h2 id="md-tag-heading" class="mb-3 text-xl font-semibold tracking-tight">
      <?= Icons::svg('hash', 'h-5 w-5 mr-0.5 inline-block align-[-3px] text-primary') ?><?= e($selected) ?>
    </h2>
    <?php if ($documents === []): ?>
    <p class="text-sm text-muted-foreground">No documents carry this tag.</p>
    <?php else: ?>
    <ul class="flex flex-col gap-2">
      <?php foreach ($documents as $document): ?>
      <li class="flex flex-wrap items-baseline gap-x-2">
        <a class="md-link font-medium" href="<?= e($req->url(['file' => $document['path']])) ?>"><?= e($document['title']) ?></a>
        <span class="font-mono text-[11px] text-muted-foreground"><?= e($document['path']) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
  <?php else: ?>
  <p class="text-sm text-muted-foreground">Pick a tag to see its documents.</p>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php $this->end(); ?>
