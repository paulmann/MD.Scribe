<?php
/**
 * MD.Viewer 3.0 — 404 page.
 *
 * @var MdViewer\Core\App $app
 * @var string $title
 * @var string $message
 */

use MdViewer\View\Icons;

$req = $app->request;
$bodyPage = 'notfound';
?>
<?php $this->extend('layout/app'); ?>
<?php $this->section('content'); ?>

<div class="grid min-h-[55dvh] place-items-center py-10 text-center">
  <div class="max-w-md">
    <p class="mb-2 font-mono text-sm font-semibold tracking-widest text-primary">ERROR 404</p>
    <h1 class="text-3xl font-bold tracking-tight"><?= e($title ?? 'Not found') ?></h1>
    <?php if (($message ?? '') !== ''): ?>
    <p class="mt-3 text-muted-foreground text-pretty"><?= e($message) ?></p>
    <?php endif; ?>
    <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
      <a class="md-btn md-btn-primary" href="<?= e($req->url()) ?>"><?= Icons::svg('house', 'h-4 w-4') ?>Home</a>
      <a class="md-btn md-btn-outline" href="<?= e($req->url(['page' => 'browser'])) ?>"><?= Icons::svg('files', 'h-4 w-4') ?>Browse library</a>
      <?php if ($app->config->bool('FEATURE_SEARCH')): ?>
      <a class="md-btn md-btn-ghost" href="<?= e($req->url(['page' => 'search'])) ?>"><?= Icons::svg('search', 'h-4 w-4') ?>Search</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $this->end(); ?>
