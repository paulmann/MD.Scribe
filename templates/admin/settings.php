<?php
/**
 * MD.Viewer 3.0 — server settings editor (grouped from the config schema).
 *
 * @var MdViewer\Core\App $app
 * @var array $schema
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Settings';

$sections = [];
foreach ($schema as $key => $meta) {
    $sections[$meta['section']][$key] = $meta;
}
$sectionOrder = ['general', 'appearance', 'features', 'interface', 'content', 'security', 'performance', 'feeds', 'diagnostics'];
$sectionTitles = [
    'general' => ['General', 'house'],
    'appearance' => ['Appearance defaults', 'palette'],
    'features' => ['Rendering features', 'sparkles'],
    'interface' => ['Interface', 'layout-dashboard'],
    'content' => ['Content & uploads', 'folder-tree'],
    'security' => ['Security & admin', 'shield-check'],
    'performance' => ['Performance', 'gauge'],
    'feeds' => ['Feeds & discovery', 'rss'],
    'diagnostics' => ['Diagnostics', 'bug'],
];
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<p class="mb-4 text-sm text-muted-foreground">
  These values are written to <code class="md-code-inline">.md.ini</code> — the installation-level hard locks.
  Visitor cookie preferences cannot override anything set here. Missing installation keys are appended automatically on load.
  <span class="font-medium text-foreground">Heads-up:</span> saving a <em>rendering-feature</em> key here hard-locks it
  installation-wide (it shows as <span class="md-lock-badge">locked</span> on the public Settings page); delete the
  line from <code class="md-code-inline">.md.ini</code> to unlock it again.
</p>

<div class="mb-4 flex flex-wrap gap-1.5" id="md-settings-nav">
  <?php foreach ($sectionOrder as $section): if (!isset($sections[$section])) { continue; } ?>
  <a class="md-tag hover:text-foreground" href="#sec-<?= e($section) ?>"><?= e($sectionTitles[$section][0] ?? ucfirst($section)) ?></a>
  <?php endforeach; ?>
</div>

<form id="md-settings-form" class="flex flex-col gap-4">
  <?php foreach ($sectionOrder as $section):
      if (!isset($sections[$section])) { continue; }
      [$title, $iconName] = $sectionTitles[$section] ?? [ucfirst($section), 'settings'];
  ?>
  <section class="md-card" id="sec-<?= e($section) ?>">
    <div class="md-card-header">
      <h2 class="md-card-title"><?= Icons::svg($iconName, 'h-4 w-4 text-primary') ?><?= e($title) ?></h2>
    </div>
    <div class="md-card-body divide-y divide-border/60">
      <?php foreach ($sections[$section] as $key => $meta):
          $current = $app->config->rawValue($key);
          $value = $current ?? (is_bool($meta['default']) ? ($meta['default'] ? 'true' : 'false') : (string) $meta['default']);
          $isSecret = !empty($meta['secret']);
      ?>
      <div class="md-setting-row">
        <label class="md-setting-label" for="set-<?= e($key) ?>">
          <p class="font-medium font-mono text-[13px]"><?= e($key) ?></p>
          <p class="text-muted-foreground"><?= e((string) $meta['description']) ?></p>
        </label>
        <div class="w-56 shrink-0">
          <?php if ($meta['type'] === 'bool'): ?>
          <button type="button" role="switch" class="md-switch" id="set-<?= e($key) ?>" data-setting="<?= e($key) ?>" data-setting-type="bool"
            aria-checked="<?= MdViewer\Core\Config::parseBool((string) $value) ? 'true' : 'false' ?>">
            <span class="md-switch-thumb"></span>
          </button>
          <?php elseif ($isSecret): ?>
          <input class="md-input md-input-sm md-font-mono" id="set-<?= e($key) ?>" type="password" data-setting="<?= e($key) ?>" data-setting-type="<?= e($meta['type']) ?>"
            value="" placeholder="<?= $value !== '' ? '•••••• set (hidden)' : '(empty)' ?>" autocomplete="new-password">
          <?php else: ?>
          <input class="md-input md-input-sm md-font-mono" id="set-<?= e($key) ?>" type="<?= $meta['type'] === 'int' ? 'number' : 'text' ?>"
            data-setting="<?= e($key) ?>" data-setting-type="<?= e($meta['type']) ?>" value="<?= e((string) $value) ?>">
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <div class="sticky bottom-3 z-20 flex flex-wrap items-center gap-2 rounded-xl border border-border bg-popover/95 p-3 shadow-lg backdrop-blur">
    <p class="text-xs text-muted-foreground" id="md-settings-status" role="status">No changes yet.</p>
    <span class="ml-auto flex gap-2">
      <button type="button" class="md-btn md-btn-ghost md-btn-sm" id="md-settings-reload"><?= Icons::svg('refresh-ccw', 'h-3.5 w-3.5') ?>Discard</button>
      <button type="submit" class="md-btn md-btn-primary md-btn-sm" id="md-settings-save" disabled><?= Icons::svg('save', 'h-3.5 w-3.5') ?>Save to .md.ini</button>
    </span>
  </div>
</form>

<div class="md-card mt-4" id="security">
  <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('key-round', 'h-4 w-4 text-primary') ?>Secrets</h2></div>
  <div class="md-card-body flex flex-wrap items-center gap-3 py-4">
    <p class="text-sm text-muted-foreground">Machine API key (<code class="md-code-inline">API_KEY</code>) — used by <code class="md-code-inline">updater.php</code> and external tooling:</p>
    <button type="button" class="md-btn md-btn-outline md-btn-sm" data-regenerate-key><?= Icons::svg('refresh-cw', 'h-3.5 w-3.5') ?>Regenerate key</button>
  </div>
</div>
<?php $this->end(); ?>
