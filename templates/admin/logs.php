<?php
/**
 * MD.Viewer 3.0 — logs & audit trail.
 *
 * @var MdViewer\Core\App $app
 * @var list<array{file: string, lines: list<string>}> $logs
 * @var list<array<string, mixed>> $audit
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Logs & audit';
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<div class="mb-4 flex flex-wrap gap-2">
  <button type="button" class="md-btn md-btn-outline md-btn-sm" data-logs-refresh><?= Icons::svg('refresh-cw', 'h-3.5 w-3.5') ?>Refresh</button>
  <span class="text-xs text-muted-foreground self-center">App log level: <code class="md-code-inline"><?= e($app->config->str('LOG_LEVEL')) ?></code> · retention <?= (int) $app->config->int('LOG_RETENTION_DAYS') ?> days</span>
</div>

<div class="grid gap-4 xl:grid-cols-2">
  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('scroll-text', 'h-4 w-4 text-primary') ?>Application log</h2></div>
    <div class="md-card-body p-0" id="md-app-logs">
      <?php if ($logs === []): ?>
      <p class="p-6 text-center text-sm text-muted-foreground">No log entries in the last 3 days.</p>
      <?php else: foreach ($logs as $log): ?>
      <details class="border-b border-border/60 last:border-0" <?= count($logs) === 1 ? 'open' : '' ?>>
        <summary class="cursor-pointer px-4 py-2.5 font-mono text-xs font-semibold text-muted-foreground hover:text-foreground"><?= e((string) $log['file']) ?> (<?= count($log['lines']) ?>)</summary>
        <pre class="md-log-pre max-h-96 overflow-auto px-4 pb-3 font-mono text-[11px] leading-relaxed"><?= e(implode("\n", $log['lines'])) ?></pre>
      </details>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('activity', 'h-4 w-4 text-primary') ?>Audit trail</h2><span class="ml-auto text-xs text-muted-foreground">last <?= count($audit) ?></span></div>
    <div class="overflow-x-auto" id="md-audit-log">
      <table class="md-table w-full">
        <thead><tr><th>When</th><th>Action</th><th>Actor</th><th class="hidden md:table-cell">Detail</th></tr></thead>
        <tbody>
        <?php foreach ($audit as $entry): ?>
          <tr>
            <td class="whitespace-nowrap tabular-nums text-muted-foreground"><?= e(date('M j H:i:s', (int) strtotime((string) $entry['ts']))) ?></td>
            <td><span class="md-badge md-badge-info font-mono"><?= e((string) $entry['action']) ?></span></td>
            <td class="text-muted-foreground"><?= e((string) $entry['actor']) ?> <span class="opacity-60">(<?= e((string) ($entry['ip'] ?? '')) ?>)</span></td>
            <td class="hidden max-w-xs truncate font-mono text-[11px] text-muted-foreground md:table-cell"><?= e(json_encode($entry['details'] ?? null, JSON_UNESCAPED_SLASHES) ?: '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($audit === []): ?><tr><td colspan="4"><p class="py-8 text-center text-sm text-muted-foreground">No audited actions yet.</p></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $this->end(); ?>
