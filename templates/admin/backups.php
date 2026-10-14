<?php
/**
 * MD.Viewer 3.0 — backups & trash.
 *
 * @var MdViewer\Core\App $app
 * @var list<array> $backups
 * @var list<array> $trash
 * @var list<array> $appBackups
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Backups & trash';
$allowRestore = $app->config->bool('ALLOW_RESTORE');
$fmt = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0; $size = (float) $bytes;
    while ($size >= 1024 && $i < 3) { $size /= 1024; $i++; }
    return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
};
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<?php if (!$allowRestore): ?>
<div class="md-flash md-flash-warning mb-4">
  <?= Icons::svg('lock', 'h-4 w-4') ?> Restoring is disabled (<code class="md-code-inline">ALLOW_RESTORE = false</code> in <code class="md-code-inline">.md.ini</code>). Creating and downloading backups still works.
</div>
<?php endif; ?>

<div class="mb-4 flex flex-wrap items-center gap-2">
  <button type="button" class="md-btn md-btn-primary md-btn-sm" data-backup-create><?= Icons::svg('history', 'h-3.5 w-3.5') ?>Create backup now</button>
  <input id="md-backup-note" class="md-input md-input-sm max-w-xs" type="text" placeholder="Optional note for this backup">
  <span class="text-xs text-muted-foreground">Snapshots cover the content root + .md.ini.</span>
</div>

<div class="md-card mb-4">
  <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('database', 'h-4 w-4 text-primary') ?>Content backups</h2></div>
  <div class="overflow-x-auto">
    <table class="md-table w-full" id="md-backups-table">
      <thead><tr><th>Backup</th><th>Created</th><th>Files</th><th>Size</th><th class="hidden md:table-cell">Note</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($backups as $backup): ?>
        <tr data-backup-id="<?= e((string) $backup['id']) ?>">
          <td class="font-mono text-xs"><?= e((string) $backup['id']) ?></td>
          <td class="text-muted-foreground"><?= e(is_string($backup['created'] ?? null) ? (string) $backup['created'] : '—') ?></td>
          <td class="tabular-nums text-muted-foreground"><?= (int) ($backup['files'] ?? 0) ?></td>
          <td class="tabular-nums text-muted-foreground"><?= e($fmt((int) ($backup['bytes'] ?? 0))) ?></td>
          <td class="hidden text-muted-foreground md:table-cell"><?= e((string) ($backup['note'] ?? '')) ?></td>
          <td class="text-right whitespace-nowrap">
            <a class="md-icon-btn" href="<?= e($req->url(['action' => 'backup.download', 'id' => $backup['id']])) ?>" title="Download .zip"><?= Icons::svg('download', 'h-4 w-4') ?></a>
            <?php if ($allowRestore): ?>
            <button type="button" class="md-icon-btn" data-backup-restore title="Restore over content root"><?= Icons::svg('refresh-ccw', 'h-4 w-4') ?></button>
            <?php endif; ?>
            <button type="button" class="md-icon-btn text-destructive" data-backup-delete title="Delete backup"><?= Icons::svg('trash-2', 'h-4 w-4') ?></button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($backups === []): ?>
        <tr><td colspan="6"><p class="py-8 text-center text-sm text-muted-foreground">No backups yet. Create one before making bulk changes.</p></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('trash-2', 'h-4 w-4 text-primary') ?>Trash (deleted documents)</h2></div>
    <div class="overflow-x-auto">
      <table class="md-table w-full" id="md-trash-table">
        <thead><tr><th>Entry</th><th class="hidden sm:table-cell">Size</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($trash as $item): ?>
          <tr data-trash-name="<?= e((string) $item['name']) ?>">
            <td class="font-mono text-xs break-all"><?= e((string) $item['name']) ?></td>
            <td class="hidden tabular-nums text-muted-foreground sm:table-cell"><?= e($fmt((int) $item['size'])) ?></td>
            <td class="text-right">
              <button type="button" class="md-icon-btn" data-trash-restore title="Restore"><?= Icons::svg('refresh-ccw', 'h-4 w-4') ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($trash === []): ?><tr><td colspan="3"><p class="py-6 text-center text-sm text-muted-foreground">Trash is empty.</p></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="md-card">
    <div class="md-card-header"><h2 class="md-card-title"><?= Icons::svg('package', 'h-4 w-4 text-primary') ?>Application backups (pre-update)</h2></div>
    <div class="overflow-x-auto">
      <table class="md-table w-full">
        <thead><tr><th>Backup</th><th class="hidden sm:table-cell">Version</th><th class="text-right">Files</th></tr></thead>
        <tbody>
        <?php foreach ($appBackups as $backup): ?>
          <tr>
            <td class="font-mono text-xs"><?= e((string) $backup['id']) ?></td>
            <td class="hidden text-muted-foreground sm:table-cell"><?= e((string) $backup['from']) ?> → <?= e((string) $backup['to']) ?></td>
            <td class="text-right tabular-nums text-muted-foreground"><?= (int) $backup['files'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($appBackups === []): ?><tr><td colspan="3"><p class="py-6 text-center text-sm text-muted-foreground">Created automatically before every update.</p></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $this->end(); ?>
