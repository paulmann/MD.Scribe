<?php
/**
 * MD.Viewer 3.0 — error page (500 and friends).
 *
 * @var MdViewer\Core\App $app
 * @var string $title
 * @var string|null $details  only populated in development mode
 * @var int $status
 */

use MdViewer\View\Icons;

$req = $app->request;
$bodyPage = 'error';
?>
<?php $this->extend('layout/app'); ?>
<?php $this->section('content'); ?>

<div class="grid min-h-[55dvh] place-items-center py-10">
  <div class="w-full max-w-2xl text-center">
    <p class="mb-2 font-mono text-sm font-semibold tracking-widest text-destructive">ERROR <?= (int) ($status ?? 500) ?></p>
    <h1 class="text-3xl font-bold tracking-tight"><?= e($title ?? 'Something went wrong') ?></h1>
    <p class="mt-3 text-muted-foreground">
      The incident was written to the application log.
      <?php if (($details ?? null) === null): ?>If it keeps happening, tell the site administrator.<?php endif; ?>
    </p>
    <?php if (($details ?? null) !== null): ?>
    <pre class="md-error-details mt-6 overflow-x-auto rounded-xl border border-border bg-muted/50 p-4 text-left text-xs leading-relaxed"><?= e((string) $details) ?></pre>
    <?php endif; ?>
    <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
      <a class="md-btn md-btn-primary" href="<?= e($req->url()) ?>"><?= Icons::svg('house', 'h-4 w-4') ?>Home</a>
      <a class="md-btn md-btn-outline" href="<?= e($req->url(['page' => 'browser'])) ?>"><?= Icons::svg('files', 'h-4 w-4') ?>Browse library</a>
    </div>
  </div>
</div>
<?php $this->end(); ?>
