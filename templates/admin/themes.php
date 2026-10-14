<?php
/**
 * MD.Viewer 3.0 — custom theme management (saved accent combinations).
 *
 * @var MdViewer\Core\App $app
 * @var list<array> $customThemes
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Custom themes';
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<div class="grid gap-4 lg:grid-cols-3">
  <div class="md-card lg:col-span-2">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('palette', 'h-4 w-4 text-primary') ?>Saved custom accents</h2></div>
    <div class="md-card-body py-3">
      <?php if ($customThemes === []): ?>
      <p class="px-1 py-6 text-center text-sm text-muted-foreground">
        No custom themes yet. Create one on the right — it becomes selectable in the
        public <a class="md-link" href="<?= e($req->url(['page' => 'themes'])) ?>" target="_blank">theme catalog</a> immediately.
      </p>
      <?php else: ?>
      <div class="grid gap-2 sm:grid-cols-2">
        <?php foreach ($customThemes as $theme): ?>
        <div class="flex items-center gap-3 rounded-lg border border-border p-3" data-custom-theme="<?= e((string) $theme['id']) ?>">
          <span class="h-9 w-9 shrink-0 rounded-md border border-border" style="background: <?= e((string) ($theme['light']['primary'] ?? '#888')) ?>"></span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium"><?= e((string) $theme['name']) ?></span>
            <span class="block truncate font-mono text-[11px] text-muted-foreground"><?= e((string) $theme['id']) ?></span>
          </span>
          <button type="button" class="md-icon-btn text-destructive" data-theme-delete="<?= e((string) $theme['id']) ?>" title="Delete theme"><?= Icons::svg('trash-2', 'h-4 w-4') ?></button>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('plus', 'h-4 w-4 text-primary') ?>New custom accent</h2></div>
    <form class="md-card-body flex flex-col gap-3 py-4" id="md-theme-form">
      <div>
        <label class="md-label" for="theme-name">Name</label>
        <input class="md-input md-input-sm" id="theme-name" type="text" required maxlength="40" placeholder="Brand blue">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="md-label" for="theme-light">Light primary</label>
          <input class="md-input md-input-sm md-font-mono" id="theme-light" type="text" required placeholder="oklch(0.55 0.2 260)">
          <p class="md-hint">oklch(), hsl(), rgb() or #hex</p>
        </div>
        <div>
          <label class="md-label" for="theme-dark">Dark primary</label>
          <input class="md-input md-input-sm md-font-mono" id="theme-dark" type="text" required placeholder="oklch(0.65 0.19 260)">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="md-label" for="theme-light-fg">On-primary (light)</label>
          <input class="md-input md-input-sm md-font-mono" id="theme-light-fg" type="text" value="oklch(0.985 0 0)">
        </div>
        <div>
          <label class="md-label" for="theme-dark-fg">On-primary (dark)</label>
          <input class="md-input md-input-sm md-font-mono" id="theme-dark-fg" type="text" value="oklch(0.141 0.005 285.823)">
        </div>
      </div>
      <div id="theme-preview" class="flex items-center gap-2 rounded-lg border border-border p-3">
        <span class="md-btn md-btn-primary md-btn-sm" style="pointer-events:none">Preview button</span>
        <span class="text-xs text-muted-foreground">Live preview</span>
      </div>
      <button type="submit" class="md-btn md-btn-primary md-btn-sm"><?= Icons::svg('save', 'h-3.5 w-3.5') ?>Save theme</button>
    </form>
  </div>
</div>

<p class="mt-4 text-xs text-muted-foreground">
  <?= Icons::svg('info', 'h-3.5 w-3.5 inline-block align-[-2px] mr-1') ?>
  Custom themes live in <code class="md-code-inline">storage/themes/*.json</code> and are injected as a small runtime
  stylesheet. To change the site-wide default, set <code class="md-code-inline">THEME_DEFAULT</code> in Settings.
</p>
<?php $this->end(); ?>
