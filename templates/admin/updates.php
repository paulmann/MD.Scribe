<?php
/**
 * MD.Viewer 3.0 — updates page.
 *
 * @var MdViewer\Core\App $app
 * @var array $update
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Updates';
$allowUpdate = $app->config->bool('ALLOW_UPDATE');
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<?php if (!$allowUpdate): ?>
<div class="md-flash md-flash-warning mb-4">
  <?= Icons::svg('lock', 'h-4 w-4') ?> The update system is disabled. Set <code class="md-code-inline">ALLOW_UPDATE = true</code> in <code class="md-code-inline">.md.ini</code> to enable checking and applying updates.
</div>
<?php endif; ?>

<div class="grid gap-4 lg:grid-cols-3">
  <div class="md-card lg:col-span-2">
    <div class="md-card-header">
      <h2 class="md-card-title"><?= Icons::svg('cloud-download', 'h-4 w-4 text-primary') ?>Release channel</h2>
      <span class="ml-auto md-badge md-badge-info">current: v<?= e(MdViewer\Core\Version::VERSION) ?></span>
    </div>
    <div class="md-card-body" id="md-update-panel">
      <?php if (($update['ok'] ?? false) === true): ?>
        <?php if (($update['newer'] ?? false) === true): ?>
        <div class="md-flash md-flash-info mb-3">
          <?= Icons::svg('sparkles', 'h-4 w-4') ?> <strong>v<?= e((string) $update['latest']) ?></strong> is available
          (checked <?= e(date('H:i', (int) strtotime((string) ($update['checked_at'] ?? 'now')))) ?>).
        </div>
        <?php else: ?>
        <div class="md-flash md-flash-success mb-3">
          <?= Icons::svg('circle-check', 'h-4 w-4') ?> You are on the latest release (v<?= e((string) ($update['latest'] ?? '?')) ?>).
        </div>
        <?php endif; ?>
        <?php if (($update['notes'] ?? '') !== ''): ?>
        <details class="mb-3">
          <summary class="cursor-pointer text-sm font-medium text-primary">Release notes</summary>
          <pre class="md-error-details mt-2 max-h-72 overflow-y-auto whitespace-pre-wrap text-xs"><?= e((string) $update['notes']) ?></pre>
        </details>
        <?php endif; ?>
      <?php else: ?>
      <p class="text-sm text-muted-foreground">Last check failed: <?= e((string) ($update['error'] ?? 'unknown error')) ?></p>
      <?php endif; ?>

      <div class="flex flex-wrap gap-2">
        <button type="button" class="md-btn md-btn-outline md-btn-sm" data-update-check><?= Icons::svg('refresh-cw', 'h-3.5 w-3.5') ?>Check for updates</button>
        <?php if ($allowUpdate): ?>
        <button type="button" class="md-btn md-btn-primary md-btn-sm" data-update-apply <?= ($update['newer'] ?? false) ? '' : 'disabled' ?>><?= Icons::svg('download', 'h-3.5 w-3.5') ?>Apply update</button>
        <?php endif; ?>
        <a class="md-btn md-btn-ghost md-btn-sm" href="<?= e((string) ($update['url'] ?? MdViewer\Core\Version::REPO . '/releases')) ?>" target="_blank" rel="noopener noreferrer"><?= Icons::svg('external-link', 'h-3.5 w-3.5') ?>Releases on GitHub</a>
      </div>

      <div class="mt-5 border-t border-border/60 pt-4">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">How updating works</h3>
        <ol class="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
          <li>The latest release is fetched from the GitHub API (15-minute cache).</li>
          <li>Applying downloads the release archive <em>once</em>, verifies every file against its sha256 manifest, and refuses on any mismatch.</li>
          <li>Files that change are backed up to <code class="md-code-inline">storage/backups/app-&lt;ts&gt;/</code> before being replaced.</li>
          <li>Your <code class="md-code-inline">content/</code>, <code class="md-code-inline">storage/</code> and <code class="md-code-inline">.md.ini</code> are never touched by updates.</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('terminal', 'h-4 w-4 text-primary') ?>Machine API</h2></div>
    <div class="md-card-body text-sm text-muted-foreground">
      <p class="mb-3">External tools (control panels, installers) can drive updates with the API key from <code class="md-code-inline">.md.ini</code>:</p>
      <pre class="md-code-pre-mini">GET  updater.php?api_key=&lt;key&gt;&amp;action=status&amp;format=json
GET  updater.php?api_key=&lt;key&gt;&amp;action=conflicts&amp;format=json
POST updater.php?api_key=&lt;key&gt;&amp;action=install&amp;format=json</pre>
      <p class="mt-3 text-xs">Responses are JSON-only, never HTML. The key is the credential — <code class="md-code-inline">ALLOW_UPDATE</code> is not required for the API, matching v2 semantics.</p>
      <a class="md-btn md-btn-outline md-btn-sm mt-3" href="<?= e($req->url(['page' => 'settings'])) ?>#security"><?= Icons::svg('key-round', 'h-3.5 w-3.5') ?>Manage API key</a>
    </div>
  </div>
</div>
<?php $this->end(); ?>
