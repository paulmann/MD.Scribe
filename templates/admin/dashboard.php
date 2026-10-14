<?php
/**
 * MD.Viewer 3.0 — admin dashboard.
 *
 * @var MdViewer\Core\App $app
 * @var array $stats
 * @var array $indexStats
 * @var array $health
 * @var list<array> $backups
 * @var list<array> $recentAudit
 * @var list<array{path: string, title: string, modified: int}> $recentDocs
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Dashboard';
$fmt = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < 3) { $size /= 1024; $i++; }
    return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
};
$okCount = count(array_filter($health['checks'], static fn ($c) => $c['status'] === 'ok'));
$warnCount = count(array_filter($health['checks'], static fn ($c) => $c['status'] === 'warn'));
$failCount = count(array_filter($health['checks'], static fn ($c) => $c['status'] === 'fail'));
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<div class="grid gap-4 lg:grid-cols-4">
  <!-- Stat cards -->
  <a class="md-stat-card" href="<?= e($req->url(['page' => 'files'])) ?>">
    <span class="md-stat-icon"><?= Icons::svg('file-text', 'h-5 w-5') ?></span>
    <span class="md-stat-value"><?= number_format((int) $stats['files']) ?></span>
    <span class="md-stat-label">Documents</span>
  </a>
  <a class="md-stat-card" href="<?= e($req->url(['page' => 'files'])) ?>">
    <span class="md-stat-icon"><?= Icons::svg('hard-drive', 'h-5 w-5') ?></span>
    <span class="md-stat-value"><?= e($fmt((int) $stats['bytes'])) ?></span>
    <span class="md-stat-label">Content size · <?= number_format((int) $stats['directories']) ?> folders</span>
  </a>
  <a class="md-stat-card" href="<?= e($req->url(['page' => 'index'])) ?>">
    <span class="md-stat-icon"><?= Icons::svg('file-search', 'h-5 w-5') ?></span>
    <span class="md-stat-value"><?= number_format((int) ($indexStats['documents'] ?? 0)) ?></span>
    <span class="md-stat-label">Indexed · <?= number_format((int) ($indexStats['terms'] ?? 0)) ?> terms<?= !empty($indexStats['fresh']) ? ' · fresh' : ' · stale' ?></span>
  </a>
  <a class="md-stat-card" href="<?= e($req->url(['page' => 'security'])) ?>">
    <span class="md-stat-icon <?= $failCount > 0 ? 'text-destructive' : ($warnCount > 0 ? 'text-amber-500' : 'text-emerald-500') ?>"><?= Icons::svg('shield-check', 'h-5 w-5') ?></span>
    <span class="md-stat-value"><?= $okCount ?>/<?= count($health['checks']) ?></span>
    <span class="md-stat-label">Health checks passed<?= $warnCount ? ' · ' . $warnCount . ' warnings' : '' ?><?= $failCount ? ' · ' . $failCount . ' failing' : '' ?></span>
  </a>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
  <!-- Quick actions -->
  <div class="md-card lg:col-span-1">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('zap', 'h-4 w-4 text-primary') ?>Quick actions</h2></div>
    <div class="md-card-body flex flex-col gap-1.5 py-3">
      <a class="md-action-row" href="<?= e($req->url(['page' => 'editor'])) ?>"><?= Icons::svg('file-plus', 'h-4 w-4') ?>New document</a>
      <a class="md-action-row" href="<?= e($req->url(['page' => 'files'])) ?>"><?= Icons::svg('upload', 'h-4 w-4') ?>Upload files</a>
      <button type="button" class="md-action-row" data-admin-quick="backup.create"><?= Icons::svg('history', 'h-4 w-4') ?>Create backup now</button>
      <button type="button" class="md-action-row" data-admin-quick="index.rebuild"><?= Icons::svg('refresh-cw', 'h-4 w-4') ?>Rebuild search index</button>
      <button type="button" class="md-action-row" data-admin-quick="cache.clear"><?= Icons::svg('database', 'h-4 w-4') ?>Clear render cache</button>
      <a class="md-action-row" href="<?= e($req->url(['page' => 'tools'])) ?>"><?= Icons::svg('link-2', 'h-4 w-4') ?>Check broken links</a>
      <a class="md-action-row" href="<?= e($req->url(['page' => 'updates'])) ?>"><?= Icons::svg('cloud-download', 'h-4 w-4') ?>Check for updates</a>
    </div>
  </div>

  <!-- Recent documents -->
  <div class="md-card lg:col-span-1">
    <div class="md-card-header">
      <h2 class="md-card-title"><?= Icons::svg('clock', 'h-4 w-4 text-primary') ?>Recently modified</h2>
      <a class="md-btn md-btn-ghost md-btn-sm ml-auto" href="<?= e($req->url(['page' => 'files'])) ?>">All files</a>
    </div>
    <div class="md-card-body py-2">
      <?php if ($recentDocs === []): ?>
      <p class="px-1 py-6 text-center text-sm text-muted-foreground">No documents yet.</p>
      <?php else: foreach ($recentDocs as $doc): ?>
      <a class="md-recent-row" href="<?= e($req->url(['page' => 'editor', 'file' => $doc['path']])) ?>">
        <?= Icons::svg('file-text', 'h-4 w-4 shrink-0 text-muted-foreground') ?>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-medium"><?= e((string) $doc['title']) ?></span>
          <span class="block truncate font-mono text-[11px] text-muted-foreground"><?= e((string) $doc['path']) ?></span>
        </span>
        <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground" title="<?= e(date('c', (int) $doc['modified'])) ?>"><?= e(date('M j', (int) $doc['modified'])) ?></span>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Recent activity -->
  <div class="md-card lg:col-span-1">
    <div class="md-card-header">
      <h2 class="md-card-title"><?= Icons::svg('activity', 'h-4 w-4 text-primary') ?>Activity</h2>
      <a class="md-btn md-btn-ghost md-btn-sm ml-auto" href="<?= e($req->url(['page' => 'logs'])) ?>">Audit log</a>
    </div>
    <div class="md-card-body py-2">
      <?php if ($recentAudit === []): ?>
      <p class="px-1 py-6 text-center text-sm text-muted-foreground">No activity recorded yet.</p>
      <?php else: foreach ($recentAudit as $entry): ?>
      <div class="md-recent-row">
        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-muted"><?= Icons::svg('circle-check', 'h-3.5 w-3.5 text-muted-foreground') ?></span>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm"><span class="font-mono text-xs font-semibold"><?= e((string) $entry['action']) ?></span> · <?= e((string) $entry['actor']) ?></span>
          <span class="block truncate text-[11px] text-muted-foreground"><?= e((string) ($entry['details']['path'] ?? $entry['details']['id'] ?? '')) ?></span>
        </span>
        <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground"><?= e(date('M j H:i', (int) strtotime((string) $entry['ts']))) ?></span>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($backups)): ?>
<div class="md-card mt-4">
  <div class="md-card-header">
    <h2 class="md-card-title"><?= Icons::svg('history', 'h-4 w-4 text-primary') ?>Latest backups</h2>
    <a class="md-btn md-btn-ghost md-btn-sm ml-auto" href="<?= e($req->url(['page' => 'backups'])) ?>">Manage</a>
  </div>
  <div class="overflow-x-auto">
    <table class="md-table w-full">
      <thead><tr><th>Backup</th><th>Created</th><th class="hidden sm:table-cell">Files</th><th class="hidden sm:table-cell">Size</th><th class="hidden md:table-cell">Note</th></tr></thead>
      <tbody>
      <?php foreach ($backups as $backup): ?>
        <tr>
          <td class="font-mono text-xs"><?= e((string) $backup['id']) ?></td>
          <td class="text-muted-foreground"><?= e((string) ($backup['created'] ?? '')) ?></td>
          <td class="hidden tabular-nums text-muted-foreground sm:table-cell"><?= (int) ($backup['files'] ?? 0) ?></td>
          <td class="hidden tabular-nums text-muted-foreground sm:table-cell"><?= e($fmt((int) ($backup['bytes'] ?? 0))) ?></td>
          <td class="hidden text-muted-foreground md:table-cell"><?= e((string) ($backup['note'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php $this->end(); ?>
