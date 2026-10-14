<?php
/**
 * MD.Viewer 3.0 — security & health.
 *
 * @var MdViewer\Core\App $app
 * @var array $health
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Security & health';
$statusIcon = ['ok' => 'circle-check', 'warn' => 'triangle-alert', 'fail' => 'circle-x'];
$statusClass = ['ok' => 'md-badge-success', 'warn' => 'md-badge-warning', 'fail' => 'md-badge-error'];
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<div class="mb-4 flex flex-wrap items-center gap-3">
  <span class="md-badge <?= $health['ok'] ? 'md-badge-success' : 'md-badge-error' ?> text-xs">
    <?= $health['ok'] ? 'All critical checks passed' : 'One or more checks failing' ?>
  </span>
  <button type="button" class="md-btn md-btn-outline md-btn-sm" data-health-run><?= Icons::svg('stethoscope', 'h-3.5 w-3.5') ?>Re-run checks</button>
  <a class="md-btn md-btn-ghost md-btn-sm ml-auto" href="<?= e($req->url(['action' => 'health.run', 'format' => 'json'])) ?>" target="_blank" rel="noopener"><?= Icons::svg('braces', 'h-3.5 w-3.5') ?>JSON</a>
</div>

<div class="md-card mb-4">
  <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('stethoscope', 'h-4 w-4 text-primary') ?>Checks</h2></div>
  <div class="overflow-x-auto" id="md-health-table">
    <table class="md-table w-full">
      <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
      <tbody>
      <?php foreach ($health['checks'] as $check): ?>
        <tr>
          <td class="font-medium"><?= e((string) $check['label']) ?></td>
          <td><span class="md-badge <?= e($statusClass[$check['status']] ?? 'md-badge-muted') ?>"><?= Icons::svg($statusIcon[$check['status']] ?? 'info', 'h-3 w-3') ?><?= e((string) $check['status']) ?></span></td>
          <td class="font-mono text-xs text-muted-foreground break-all"><?= e((string) $check['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('shield', 'h-4 w-4 text-primary') ?>Hardening checklist</h2></div>
    <div class="md-card-body text-sm text-muted-foreground">
      <ul class="list-disc space-y-2 pl-4">
        <li>Serve over HTTPS; the session cookie upgrades to <code class="md-code-inline">Secure</code> automatically.</li>
        <li>Keep <code class="md-code-inline">DISABLE_UPLOAD = true</code> unless readers must upload (admin uploads always work).</li>
        <li>Leave <code class="md-code-inline">ALLOW_RAW_HTML = false</code> unless your authors are fully trusted — the sanitizer still runs, but escaping is the strongest gate.</li>
        <li>Restrict network access to <code class="md-code-inline">/admin.php</code> at the proxy/firewall level when possible.</li>
        <li>Rotate <code class="md-code-inline">API_KEY</code> if it ever leaked into logs or CI output.</li>
        <li>Run backups before updates; verify a restore once in a while.</li>
        <li>Point the docroot at <code class="md-code-inline">public/</code> so <code class="md-code-inline">src/</code>, <code class="md-code-inline">storage/</code> and <code class="md-code-inline">config/</code> are physically unreachable.</li>
      </ul>
    </div>
  </div>
  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('globe', 'h-4 w-4 text-primary') ?>Response headers (live)</h2></div>
    <div class="md-card-body text-sm text-muted-foreground">
      <p class="mb-2">Every public response currently sends:</p>
      <pre class="md-code-pre-mini">Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-…'; …
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()
Cross-Origin-Opener-Policy: same-origin</pre>
      <p class="mt-3 text-xs">Toggle them under <a class="md-link" href="<?= e($req->url(['page' => 'settings'])) ?>#sec-security">Settings → Security</a>. Mermaid's generated SVG needs <code class="md-code-inline">style-src 'unsafe-inline'</code>; that is the documented trade-off.</p>
    </div>
  </div>
</div>
<?php $this->end(); ?>
