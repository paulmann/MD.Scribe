<?php
/**
 * MD.Viewer 3.0 — Themes catalog page.
 *
 * A gallery of curated presets (live mini-previews, light & dark), plus the
 * advanced mixer: any neutral × any accent, corner radius and font. Choices
 * persist to localStorage and apply instantly (CSS variables on <html>), with
 * automatic system-preference detection on first visit.
 *
 * @var MdViewer\Core\App $app
 * @var list<array<string, mixed>> $presets
 * @var list<array{id: string, name: string}> $neutrals
 * @var list<array{id: string, name: string}> $accents
 * @var list<string> $radii
 * @var array<string, array{name: string}> $fonts
 * @var string $customCss
 */

use MdViewer\View\Icons;

$req = $app->request;
$bodyPage = 'themes';
$activePreset = $app->config->str('THEME_DEFAULT');
?>
<?php $this->extend('layout/app'); ?>
<?php $this->section('content'); ?>

<div class="mx-auto max-w-5xl py-8 lg:py-12">
  <header class="mb-10 text-center">
    <p class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/50 px-3 py-1 text-xs font-medium text-muted-foreground">
      <?= Icons::svg('palette', 'h-3.5 w-3.5 text-primary') ?><?= count($presets) ?> curated themes · light &amp; dark · fully local
    </p>
    <h1 class="text-3xl font-bold tracking-tight text-balance sm:text-4xl">Make it yours</h1>
    <p class="mx-auto mt-3 max-w-xl text-muted-foreground text-pretty">
      Pick a preset or mix your own: a neutral base, an accent color, corner radius and font.
      Your choice is stored in this browser and follows the system light/dark preference automatically.
    </p>
  </header>

  <!-- Mode selector -->
  <div class="mb-8 flex justify-center">
    <div class="md-segmented" role="radiogroup" aria-label="Color mode" data-mode-group>
      <button type="button" class="md-segment" role="radio" data-mode-select="light" aria-checked="false"><?= Icons::svg('sun', 'h-4 w-4') ?>Light</button>
      <button type="button" class="md-segment" role="radio" data-mode-select="dark" aria-checked="false"><?= Icons::svg('moon', 'h-4 w-4') ?>Dark</button>
      <button type="button" class="md-segment" role="radio" data-mode-select="system" aria-checked="true"><?= Icons::svg('monitor', 'h-4 w-4') ?>System</button>
    </div>
  </div>

  <!-- Preset gallery -->
  <section aria-labelledby="presets-heading" class="mb-12">
    <h2 id="presets-heading" class="mb-4 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Presets</h2>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
      <?php foreach ($presets as $preset): $pid = (string) $preset['id']; ?>
      <button type="button"
        class="md-theme-card group"
        data-theme-preset-btn="<?= e($pid) ?>"
        data-neutral="<?= e((string) $preset['neutral']) ?>"
        data-accent="<?= e((string) $preset['accent']) ?>"
        aria-pressed="<?= $pid === $activePreset ? 'true' : 'false' ?>">
        <span class="md-theme-preview">
          <span class="md-theme-preview-bar"></span>
          <span class="md-theme-preview-body">
            <span class="md-theme-preview-side"></span>
            <span class="md-theme-preview-main">
              <span class="md-theme-preview-title"></span>
              <span class="md-theme-preview-line w-4/5"></span>
              <span class="md-theme-preview-line w-3/5"></span>
              <span class="md-theme-preview-btn">Button</span>
            </span>
          </span>
        </span>
        <span class="md-theme-card-label">
          <span class="font-medium"><?= e((string) $preset['name']) ?></span>
          <span class="md-theme-check" aria-hidden="true"><?= Icons::svg('circle-check', 'h-4 w-4 text-primary') ?></span>
        </span>
        <span class="block truncate text-xs text-muted-foreground"><?= e((string) ($preset['description'] ?? '')) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Advanced mixer -->
  <section aria-labelledby="mix-heading" class="mb-12">
    <h2 id="mix-heading" class="mb-4 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Mix your own</h2>
    <div class="md-card grid gap-6 p-5 sm:grid-cols-2">
      <div>
        <p class="mb-2 text-xs font-medium text-muted-foreground">Neutral base</p>
        <div class="flex flex-wrap gap-2" data-neutral-group role="radiogroup" aria-label="Neutral base">
          <?php foreach ($neutrals as $neutral): ?>
          <button type="button" class="md-swatch" role="radio" aria-checked="false" data-neutral-select="<?= e((string) $neutral['id']) ?>" title="<?= e((string) $neutral['name']) ?>">
            <span class="md-swatch-dot" data-neutral-dot="<?= e((string) $neutral['id']) ?>"></span><?= e((string) $neutral['name']) ?>
          </button>
          <?php endforeach; ?>
        </div>
        <p class="mt-5 mb-2 text-xs font-medium text-muted-foreground">Accent color</p>
        <div class="flex flex-wrap gap-2" data-accent-group role="radiogroup" aria-label="Accent color">
          <?php foreach ($accents as $accent): ?>
          <button type="button" class="md-swatch" role="radio" aria-checked="false" data-accent-select="<?= e((string) $accent['id']) ?>" title="<?= e((string) $accent['name']) ?>">
            <span class="md-swatch-dot" data-accent-dot="<?= e((string) $accent['id']) ?>"></span><?= e((string) $accent['name']) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <p class="mb-2 text-xs font-medium text-muted-foreground">Corner radius</p>
        <div class="md-segmented w-full" data-radius-group role="radiogroup" aria-label="Corner radius">
          <?php foreach ($radii as $radius): ?>
          <button type="button" class="md-segment grow" role="radio" aria-checked="false" data-radius-select="<?= e((string) $radius) ?>"><?= e(ucfirst((string) $radius)) ?></button>
          <?php endforeach; ?>
        </div>
        <p class="mt-5 mb-2 text-xs font-medium text-muted-foreground">Font</p>
        <div class="md-segmented w-full" data-font-group role="radiogroup" aria-label="Font">
          <?php foreach ($fonts as $fontId => $font): ?>
          <button type="button" class="md-segment grow" role="radio" aria-checked="false" data-font-select="<?= e((string) $fontId) ?>" style="font-family: <?= e((string) ($font['sans'] ?? '')) ?>"><?= e((string) ($font['name'] ?? $fontId)) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="mt-6 flex items-center gap-2">
          <button type="button" class="md-btn md-btn-outline md-btn-sm" data-theme-reset><?= Icons::svg('refresh-ccw', 'h-3.5 w-3.5') ?>Reset to default</button>
          <span class="text-xs text-muted-foreground" data-theme-status role="status"></span>
        </div>
      </div>
    </div>
  </section>

  <!-- Live sample -->
  <section aria-labelledby="sample-heading">
    <h2 id="sample-heading" class="mb-4 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Live preview</h2>
    <div class="md-card p-6">
      <div class="md-body md-body-sample">
        <h2>The quick brown fox</h2>
        <p>
          Jumps over the lazy dog — <strong>bold</strong>, <em>italic</em>, <code class="md-code-inline">inline code</code>,
          <a class="md-link" href="#" onclick="return false">a link</a>, <mark>highlight</mark>, and a
          <span class="glossary-term-demo">glossary term</span>.
        </p>
        <blockquote class="md-quote"><p>Design is not just what it looks like. Design is how it works.</p></blockquote>
        <div class="md-alert md-alert-note" data-alert="note" role="note">
          <p class="md-alert-title"><?= Icons::svg('info', 'h-4 w-4') ?><span>Note</span></p>
          <div class="md-alert-body"><p>Alerts, callouts and every component follow the active theme — light and dark.</p></div>
        </div>
        <ul class="md-task-list">
          <li><input type="checkbox" checked disabled> Themed components</li>
          <li><input type="checkbox" disabled> Zero CDN dependencies</li>
        </ul>
        <div class="flex flex-wrap gap-2 pt-2">
          <button type="button" class="md-btn md-btn-primary md-btn-sm" onclick="return false">Primary action</button>
          <button type="button" class="md-btn md-btn-outline md-btn-sm" onclick="return false">Secondary</button>
          <span class="md-tag"><?= Icons::svg('hash', 'h-3 w-3') ?>tag</span>
        </div>
      </div>
    </div>
  </section>
</div>
<?php $this->end(); ?>
