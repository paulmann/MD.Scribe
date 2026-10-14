<?php
/**
 * MD.Viewer 3.0 — admin file manager.
 *
 * @var MdViewer\Core\App $app
 * @var array{directories: list<array>, files: list<array>} $tree
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Files';
$fmt = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < 3) { $size /= 1024; $i++; }
    return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
};
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<div class="mb-4 flex flex-wrap items-center gap-2">
  <div class="md-input-wrap grow max-w-sm">
    <?= Icons::svg('filter', 'h-4 w-4 text-muted-foreground') ?>
    <input id="md-fm-filter" class="md-input" type="search" placeholder="Filter files…" aria-label="Filter files">
  </div>
  <div class="ml-auto flex flex-wrap gap-2">
    <button type="button" class="md-btn md-btn-outline md-btn-sm" data-fm-action="mkdir"><?= Icons::svg('folder-plus', 'h-3.5 w-3.5') ?>New folder</button>
    <button type="button" class="md-btn md-btn-outline md-btn-sm" data-fm-action="create"><?= Icons::svg('file-plus', 'h-3.5 w-3.5') ?>New document</button>
    <button type="button" class="md-btn md-btn-outline md-btn-sm" data-fm-action="upload-image"><?= Icons::svg('image-plus', 'h-3.5 w-3.5') ?>Upload image</button>
    <button type="button" class="md-btn md-btn-primary md-btn-sm" data-fm-action="upload-md"><?= Icons::svg('upload', 'h-3.5 w-3.5') ?>Upload .md</button>
  </div>
</div>

<div class="md-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="md-table w-full" id="md-fm-table">
      <thead>
        <tr>
          <th>Path</th>
          <th class="hidden w-24 sm:table-cell">Size</th>
          <th class="hidden w-32 md:table-cell">Modified</th>
          <th class="w-[13rem] text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tree['directories'] as $folder): ?>
        <tr class="md-fm-row" data-kind="folder" data-path="<?= e((string) $folder['path']) ?>">
          <td colspan="1">
            <span class="md-fm-name">
              <?= Icons::svg('folder', 'h-4 w-4 shrink-0 text-primary/70') ?>
              <span class="font-mono text-xs"><?= e((string) $folder['path']) ?></span>
            </span>
          </td>
          <td class="hidden sm:table-cell"></td>
          <td class="hidden md:table-cell"></td>
          <td class="text-right">
            <button type="button" class="md-icon-btn" data-fm-row="rmdir" title="Remove empty folder"><?= Icons::svg('trash-2', 'h-4 w-4') ?></button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($tree['files'] as $file): $isMd = ($file['type'] ?? 'markdown') === 'markdown'; ?>
        <tr class="md-fm-row" data-kind="file" data-path="<?= e((string) $file['path']) ?>" data-type="<?= e((string) ($file['type'] ?? 'markdown')) ?>">
          <td>
            <span class="md-fm-name">
              <?= Icons::svg($isMd ? 'file-text' : 'image', 'h-4 w-4 shrink-0 ' . ($isMd ? 'text-muted-foreground' : 'text-emerald-500')) ?>
              <?php if ($isMd): ?>
              <a class="font-mono text-xs hover:text-primary hover:underline" href="<?= e($req->url(['page' => 'editor', 'file' => $file['path']])) ?>"><?= e((string) $file['path']) ?></a>
              <?php else: ?>
              <span class="font-mono text-xs"><?= e((string) $file['path']) ?></span>
              <?php endif; ?>
            </span>
          </td>
          <td class="hidden tabular-nums text-muted-foreground sm:table-cell"><?= e($fmt((int) $file['size'])) ?></td>
          <td class="hidden tabular-nums text-muted-foreground md:table-cell"><?= e(date('Y-m-d H:i', (int) $file['modified'])) ?></td>
          <td class="text-right whitespace-nowrap">
            <?php if ($isMd): ?>
            <a class="md-icon-btn" href="<?= e($req->url(['page' => 'editor', 'file' => $file['path']])) ?>" title="Edit"><?= Icons::svg('square-pen', 'h-4 w-4') ?></a>
            <a class="md-icon-btn" href="<?= e($adminJsConfig['viewerUrl']) ?>?file=<?= e(rawurlencode((string) $file['path'])) ?>" target="_blank" rel="noopener" title="View"><?= Icons::svg('eye', 'h-4 w-4') ?></a>
            <button type="button" class="md-icon-btn" data-fm-row="rename" title="Rename/move"><?= Icons::svg('type', 'h-4 w-4') ?></button>
            <?php endif; ?>
            <button type="button" class="md-icon-btn text-destructive" data-fm-row="delete" title="Delete (to trash)"><?= Icons::svg('trash-2', 'h-4 w-4') ?></button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($tree['files'] === [] && $tree['directories'] === []): ?>
        <tr><td colspan="4"><p class="py-10 text-center text-sm text-muted-foreground">The content root is empty. Create a document or upload files.</p></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="md-card-footer text-xs text-muted-foreground">
    Content root: <code class="md-code-inline"><?= e((string) $adminJsConfig['browseDir']) ?></code>
    <span class="ml-auto" id="md-fm-count"></span>
  </div>
</div>

<!-- Prompt dialog (create / mkdir / rename) -->
<div class="md-dialog-backdrop" id="fm-prompt-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="fm-prompt-title">
  <div class="md-dialog md-dialog-sm">
    <div class="md-dialog-header">
      <h2 class="md-dialog-title" id="fm-prompt-title">Input</h2>
      <button type="button" class="md-icon-btn" data-dialog-close aria-label="Close"><?= Icons::svg('x', 'h-4 w-4') ?></button>
    </div>
    <form class="md-dialog-body" id="fm-prompt-form">
      <label class="md-label" for="fm-prompt-label" id="fm-prompt-label">Value</label>
      <input class="md-input md-font-mono" id="fm-prompt-input" type="text" autocomplete="off" required>
      <p class="md-hint" id="fm-prompt-hint"></p>
      <p class="md-field-error hidden" id="fm-prompt-error" role="alert"></p>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" class="md-btn md-btn-ghost md-btn-sm" data-dialog-close>Cancel</button>
        <button type="submit" class="md-btn md-btn-primary md-btn-sm" id="fm-prompt-submit">Confirm</button>
      </div>
    </form>
  </div>
</div>

<!-- Upload dialog -->
<div class="md-dialog-backdrop" id="fm-upload-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="fm-upload-title">
  <div class="md-dialog md-dialog-sm">
    <div class="md-dialog-header">
      <h2 class="md-dialog-title" id="fm-upload-title"><?= Icons::svg('upload', 'h-4 w-4 text-primary') ?><span id="fm-upload-label">Upload</span></h2>
      <button type="button" class="md-icon-btn" data-dialog-close aria-label="Close"><?= Icons::svg('x', 'h-4 w-4') ?></button>
    </div>
    <form class="md-dialog-body" id="fm-upload-form">
      <div class="md-dropzone" id="fm-dropzone">
        <?= Icons::svg('file-up', 'h-8 w-8 text-muted-foreground') ?>
        <p class="mt-2 text-sm font-medium" id="fm-dropzone-text">Drop files here</p>
        <label class="md-btn md-btn-outline md-btn-sm cursor-pointer">
          Choose files
          <input type="file" id="fm-upload-input" class="sr-only" multiple>
        </label>
        <p class="mt-2 text-xs text-muted-foreground">Target folder (relative): <input id="fm-upload-dir" class="md-input md-input-sm md-font-mono mt-1 w-48" type="text" value="" placeholder="root"></p>
      </div>
      <div id="fm-upload-progress" class="mt-3 flex flex-col gap-1.5 text-sm"></div>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" class="md-btn md-btn-ghost md-btn-sm" data-dialog-close>Close</button>
        <button type="submit" class="md-btn md-btn-primary md-btn-sm" id="fm-upload-submit">Upload</button>
      </div>
    </form>
  </div>
</div>

<!-- Confirm dialog -->
<div class="md-dialog-backdrop" id="fm-confirm-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="fm-confirm-title">
  <div class="md-dialog md-dialog-sm">
    <div class="md-dialog-header">
      <h2 class="md-dialog-title text-destructive" id="fm-confirm-title"><?= Icons::svg('triangle-alert', 'h-4 w-4') ?>Are you sure?</h2>
    </div>
    <div class="md-dialog-body">
      <p class="text-sm" id="fm-confirm-text">This action cannot be undone.</p>
    </div>
    <div class="md-dialog-footer">
      <button type="button" class="md-btn md-btn-ghost md-btn-sm" data-dialog-close>Cancel</button>
      <button type="button" class="md-btn md-btn-destructive md-btn-sm" id="fm-confirm-ok">Delete</button>
    </div>
  </div>
</div>
<?php $this->end(); ?>
