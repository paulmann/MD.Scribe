<?php
/**
 * MD.Viewer 3.0 — Settings page (reading preferences + feature toggles).
 *
 * Two layers, exactly like v2 but as a first-class page:
 *  - reading preferences (text size / width / line height) → localStorage;
 *  - rendering features → first-party `mdv_feat_*` cookies read by PHP on the
 *    next request; keys hard-locked in .md.ini are shown locked.
 *
 * @var MdViewer\Core\App $app
 * @var array{features: list<array<string, mixed>>, interface: list<array<string, mixed>>} $sections
 */

use MdViewer\View\Icons;

$req = $app->request;
$bodyPage = 'settings';
?>
<?php $this->extend('layout/app'); ?>
<?php $this->section('content'); ?>

<div class="mx-auto max-w-3xl py-8 lg:py-12">
  <header class="mb-8">
    <h1 class="text-3xl font-bold tracking-tight"><?= Icons::svg('settings', 'h-7 w-7 mr-2 inline-block align-[-4px] text-primary') ?>Settings</h1>
    <p class="mt-2 text-muted-foreground">
      Preferences live in this browser only. Server-side defaults are managed in
      <a class="md-link" href="<?= e(rtrim($req->assetBase(), '/') . '/admin.php') ?>?page=settings">the admin panel</a>.
    </p>
  </header>

  <!-- Appearance -->
  <section class="md-card mb-6" aria-labelledby="appearance-h">
    <div class="md-card-header">
      <h2 id="appearance-h" class="md-card-title"><?= Icons::svg('palette', 'h-4 w-4 text-primary') ?>Appearance</h2>
      <a class="md-btn md-btn-outline md-btn-sm ml-auto" href="<?= e($req->url(['page' => 'themes'])) ?>"><?= Icons::svg('palette', 'h-3.5 w-3.5') ?>Open theme catalog</a>
    </div>
    <div class="md-card-body divide-y divide-border/60">
      <div class="md-setting-row">
        <div class="md-setting-label">
          <p class="font-medium">Color mode</p>
          <p class="text-muted-foreground">Light, dark, or follow the system. Auto-detected on first visit.</p>
        </div>
        <div class="md-segmented" data-mode-group>
          <button type="button" class="md-segment md-segment-icon" data-mode-select="light" aria-label="Light mode"><?= Icons::svg('sun', 'h-4 w-4') ?></button>
          <button type="button" class="md-segment md-segment-icon" data-mode-select="dark" aria-label="Dark mode"><?= Icons::svg('moon', 'h-4 w-4') ?></button>
          <button type="button" class="md-segment md-segment-icon" data-mode-select="system" aria-label="System mode"><?= Icons::svg('monitor', 'h-4 w-4') ?></button>
        </div>
      </div>
      <div class="md-setting-row">
        <div class="md-setting-label">
          <p class="font-medium">Text size</p>
          <p class="text-muted-foreground">Base font size of documents.</p>
        </div>
        <div class="md-stepper" data-pref="fontSize">
          <button type="button" class="md-icon-btn" data-step="-1" aria-label="Decrease text size"><?= Icons::svg('minus', 'h-4 w-4') ?></button>
          <output class="md-stepper-value" data-value>100%</output>
          <button type="button" class="md-icon-btn" data-step="1" aria-label="Increase text size"><?= Icons::svg('plus', 'h-4 w-4') ?></button>
        </div>
      </div>
      <div class="md-setting-row">
        <div class="md-setting-label">
          <p class="font-medium">Page width</p>
          <p class="text-muted-foreground">How wide the reading column is.</p>
        </div>
        <div class="md-stepper" data-pref="width">
          <button type="button" class="md-icon-btn" data-step="-1" aria-label="Narrower"><?= Icons::svg('minimize', 'h-4 w-4') ?></button>
          <output class="md-stepper-value" data-value>Comfortable</output>
          <button type="button" class="md-icon-btn" data-step="1" aria-label="Wider"><?= Icons::svg('maximize', 'h-4 w-4') ?></button>
        </div>
      </div>
      <div class="md-setting-row">
        <div class="md-setting-label">
          <p class="font-medium">Line height</p>
          <p class="text-muted-foreground">Vertical rhythm of paragraphs.</p>
        </div>
        <div class="md-stepper" data-pref="lineHeight">
          <button type="button" class="md-icon-btn" data-step="-1" aria-label="Tighter"><?= Icons::svg('minus', 'h-4 w-4') ?></button>
          <output class="md-stepper-value" data-value>1.75</output>
          <button type="button" class="md-icon-btn" data-step="1" aria-label="Looser"><?= Icons::svg('plus', 'h-4 w-4') ?></button>
        </div>
      </div>
    </div>
  </section>

  <!-- Rendering features -->
  <section class="md-card mb-6" aria-labelledby="features-h">
    <div class="md-card-header">
      <h2 id="features-h" class="md-card-title"><?= Icons::svg('sparkles', 'h-4 w-4 text-primary') ?>Rendering features</h2>
      <p class="ml-auto text-xs text-muted-foreground" id="md-cookie-note"></p>
    </div>
    <div class="md-card-body divide-y divide-border/60">
      <?php foreach ($sections['features'] as $feature): ?>
      <div class="md-setting-row">
        <label class="md-setting-label" for="feat-<?= e((string) $feature['key']) ?>">
          <p class="font-medium">
            <?= e((string) $feature['label']) ?>
            <?php if ($feature['hardLocked']): ?>
            <span class="md-lock-badge" title="Hard-locked in .md.ini — cannot be changed from the browser"><?= Icons::svg('lock', 'h-3 w-3') ?>locked</span>
            <?php endif; ?>
          </p>
          <p class="text-muted-foreground"><?= e((string) $feature['description']) ?></p>
        </label>
        <button type="button" role="switch" class="md-switch" id="feat-<?= e((string) $feature['key']) ?>"
          data-feature-toggle="<?= e((string) $feature['key']) ?>"
          data-feature-default="<?= $feature['default'] ? '1' : '0' ?>"
          <?= $feature['hardLocked'] ? 'disabled data-hard-locked="1"' : '' ?>
          aria-checked="<?= $app->flags->enabled((string) $feature['key']) ? 'true' : 'false' ?>">
          <span class="md-switch-thumb"></span>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Interface -->
  <section class="md-card mb-6" aria-labelledby="interface-h">
    <div class="md-card-header">
      <h2 id="interface-h" class="md-card-title"><?= Icons::svg('layout-dashboard', 'h-4 w-4 text-primary') ?>Interface</h2>
    </div>
    <div class="md-card-body divide-y divide-border/60">
      <?php foreach ($sections['interface'] as $feature): ?>
      <div class="md-setting-row">
        <label class="md-setting-label" for="feat-<?= e((string) $feature['key']) ?>">
          <p class="font-medium">
            <?= e((string) $feature['label']) ?>
            <?php if ($feature['hardLocked']): ?>
            <span class="md-lock-badge" title="Hard-locked in .md.ini"><?= Icons::svg('lock', 'h-3 w-3') ?>locked</span>
            <?php endif; ?>
          </p>
          <p class="text-muted-foreground"><?= e((string) $feature['description']) ?></p>
        </label>
        <button type="button" role="switch" class="md-switch" id="feat-<?= e((string) $feature['key']) ?>"
          data-feature-toggle="<?= e((string) $feature['key']) ?>"
          data-feature-default="<?= $feature['default'] ? '1' : '0' ?>"
          <?= $feature['hardLocked'] ? 'disabled data-hard-locked="1"' : '' ?>
          aria-checked="<?= $app->flags->enabled((string) $feature['key']) ? 'true' : 'false' ?>">
          <span class="md-switch-thumb"></span>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Privacy -->
  <section class="md-card mb-6" aria-labelledby="privacy-h">
    <div class="md-card-header">
      <h2 id="privacy-h" class="md-card-title"><?= Icons::svg('shield-check', 'h-4 w-4 text-primary') ?>Privacy</h2>
    </div>
    <div class="md-card-body">
      <div class="md-setting-row">
        <label class="md-setting-label" for="md-cookie-consent">
          <p class="font-medium">First-party preference storage</p>
          <p class="text-muted-foreground">
            Store the feature toggles above in first-party cookies so they apply on the next page load.
            No tracking, no third parties — MD.Viewer never sends data anywhere.
            Reading preferences are always kept in localStorage.
          </p>
        </label>
        <button type="button" role="switch" class="md-switch" id="md-cookie-consent" data-cookie-consent aria-checked="true">
          <span class="md-switch-thumb"></span>
        </button>
      </div>
    </div>
  </section>

  <div class="flex flex-wrap items-center gap-3">
    <button type="button" class="md-btn md-btn-outline" data-settings-reset><?= Icons::svg('refresh-ccw', 'h-4 w-4') ?>Reset everything to defaults</button>
    <button type="button" class="md-btn md-btn-primary" data-settings-apply hidden><?= Icons::svg('check', 'h-4 w-4') ?>Apply &amp; reload</button>
    <span class="text-xs text-muted-foreground" data-settings-status role="status"></span>
  </div>
</div>
<?php $this->end(); ?>
