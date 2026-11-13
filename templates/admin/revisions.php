<?php
/**
 * MD.Scribe 3.3 — document revisions.
 *
 * Two levels: documents that have history, and the history of one document
 * (with a read-only preview and a restore button). Restoring keeps the current
 * content as a revision first, so it is undoable.
 *
 * @var MdViewer\Core\App $app
 * @var list<array{path: string, count: int, last: int, actor: string}> $documents
 * @var string $file
 * @var list<array{id: string, time: int, actor: string, bytes: int, sha256: string}> $revisions
 * @var string $revision
 * @var string|null $preview
 * @var bool $enabled
 * @var int $max
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Revisions';

$fmtBytes = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < 3) {
        $size /= 1024;
        $i++;
    }

    return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
};
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<?php if (!$enabled): ?>
<div class="md-flash md-flash-warning mb-4">
  <?= Icons::svg('lock', 'h-4 w-4') ?> Revision history is disabled (<code class="md-code-inline">REVISIONS_ENABLED = false</code> in <code class="md-code-inline">.md.ini</code>). Existing revisions can still be read and restored.
</div>
<?php endif; ?>

<div class="md-card mb-4">
  <div class="md-card-header">
    <h2 class="md-card-title"><?= Icons::svg('clock', 'h-4 w-4 text-primary') ?>Documents with history</h2>
    <span class="ml-auto text-xs text-muted-foreground">Keeping the newest <?= (int) $max ?> revisions per document</span>
  </div>
  <div class="overflow-x-auto">
    <table class="md-table w-full">
      <thead><tr><th>Document</th><th>Revisions</th><th>Last save</th><th class="hidden md:table-cell">Actor</th></tr></thead>
      <tbody>
      <?php foreach ($documents as $document): ?>
        <tr>
          <td class="font-mono text-xs">
            <a class="md-link" href="<?= e($req->url(['page' => 'revisions', 'file' => $document['path']])) ?>"><?= e($document['path']) ?></a>
          </td>
          <td class="tabular-nums text-muted-foreground"><?= (int) $document['count'] ?></td>
          <td class="text-muted-foreground"><?= e(date('Y-m-d H:i', $document['last'])) ?></td>
          <td class="hidden text-muted-foreground md:table-cell"><?= e($document['actor']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($documents === []): ?>
        <tr><td colspan="4" class="py-8 text-center text-sm text-muted-foreground">No revisions yet — save a document in the editor and it appears here.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($file !== ''): ?>
<div class="md-card mb-4">
  <div class="md-card-header">
    <h2 class="md-card-title"><?= Icons::svg('file-text', 'h-4 w-4 text-primary') ?><?= e($file) ?></h2>
    <a class="md-btn md-btn-outline md-btn-sm ml-auto" href="<?= e($req->url(['page' => 'editor', 'file' => $file])) ?>">
      <?= Icons::svg('square-pen', 'h-3.5 w-3.5') ?>Open in editor
    </a>
  </div>
  <div class="overflow-x-auto">
    <table class="md-table w-full">
      <thead><tr><th>Revision</th><th>Saved</th><th>Size</th><th class="hidden md:table-cell">Actor</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($revisions as $item): ?>
        <tr>
          <td class="font-mono text-xs"><?= e($item['id']) ?></td>
          <td class="text-muted-foreground"><?= e(date('Y-m-d H:i:s', $item['time'])) ?></td>
          <td class="tabular-nums text-muted-foreground"><?= e($fmtBytes($item['bytes'])) ?></td>
          <td class="hidden text-muted-foreground md:table-cell"><?= e($item['actor']) ?></td>
          <td class="text-right whitespace-nowrap">
            <a class="md-icon-btn" title="Preview this revision"
               href="<?= e($req->url(['page' => 'revisions', 'file' => $file, 'rev' => $item['id']])) ?>"><?= Icons::svg('eye', 'h-4 w-4') ?></a>
            <button type="button" class="md-icon-btn" title="Restore this revision"
                    data-revision-restore="<?= e($item['id']) ?>" data-revision-path="<?= e($file) ?>"><?= Icons::svg('refresh-ccw', 'h-4 w-4') ?></button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($revisions === []): ?>
        <tr><td colspan="5" class="py-8 text-center text-sm text-muted-foreground">No revisions for this document.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($preview !== null): ?>
<div class="md-card">
  <div class="md-card-header">
    <h2 class="md-card-title"><?= Icons::svg('eye', 'h-4 w-4 text-primary') ?>Revision <?= e($revision) ?></h2>
    <span class="ml-auto text-xs text-muted-foreground">Read-only preview. Restoring overwrites the current document.</span>
  </div>
  <pre class="md-log-pre max-h-[60vh] overflow-auto p-4 font-mono text-[11px] leading-relaxed"><?= e($preview) ?></pre>
</div>
<?php endif; ?>

<script nonce="<?= e($app->nonce()) ?>">
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-revision-restore]').forEach(function (button) {
    button.addEventListener('click', async function () {
      var id = button.getAttribute('data-revision-restore');
      var path = button.getAttribute('data-revision-path');
      if (!id || !path || !window.confirm('Restore revision ' + id + '? The current content is kept as a revision first.')) {
        return;
      }
      var config = window.MDV_ADMIN || {};
      var body = new URLSearchParams({ path: path, id: id, _token: config.csrf || '' });
      button.disabled = true;
      try {
        var response = await fetch(config.scriptUrl + '?action=revision.restore', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': config.csrf || '',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body.toString(),
          credentials: 'same-origin'
        });
        var data = await response.json().catch(function () { return null; });
        if (!response.ok || !data || data.ok !== true) {
          window.alert((data && data.error) || 'Restore failed.');
          button.disabled = false;
          return;
        }
        window.location.reload();
      } catch (error) {
        window.alert(error.message);
        button.disabled = false;
      }
    });
  });
});
</script>

<?php $this->end(); ?>
