<?php
/**
 * MD.Viewer 3.0 — file browser (Library).
 *
 * A searchable, sortable table of every document under the current directory
 * (recursive), folder chips for quick navigation, and the flag-gated
 * Upload / Clipboard-preview controls (shadcn dialogs).
 *
 * @var MdViewer\Core\App $app
 * @var list<array{path: string, title: string, size: int, modified: int, depth: int}> $files
 * @var list<array{name: string, path: string, count: int}> $folders
 * @var string $dir
 * @var array $breadcrumbs
 * @var array $flags
 */

use MdViewer\View\Icons;

$req = $app->request;
$bodyPage = 'browser';
$canUpload = !$flags['DISABLE_UPLOAD'];
$canClipboard = !$flags['DISABLE_CLIPBOARD'];
$canSaveClipboard = !$flags['DISABLE_SAVE_CLIPBOARD_TO_FILE'];
$showDownloadIcon = $app->flags->enabled('FEATURE_DOWNLOAD_ICON');
$fmtSize = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < 3) { $size /= 1024; $i++; }
    return ($i === 0 ? (string) (int) $size : number_format($size, $size < 10 ? 1 : 0)) . ' ' . $units[$i];
};
?>
<?php $this->extend('layout/app'); ?>
<?php $this->section('content'); ?>

<div class="py-6 lg:py-8">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <nav aria-label="Breadcrumb" class="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
          <?php if ($i > 0): ?><?= Icons::svg('chevron-right', 'h-3 w-3 shrink-0 opacity-60') ?><?php endif; ?>
          <?php if ($crumb['isFile'] || $crumb['path'] === ''): ?>
            <span class="<?= $crumb['path'] === '' ? '' : 'font-medium text-foreground' ?>"><?= e($crumb['name']) ?></span>
          <?php else: ?>
            <a class="md-crumb" href="<?= e($req->url(['page' => 'browser', 'dir' => $crumb['path']])) ?>"><?= e($crumb['name']) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
        <?= $dir === '' ? 'Library' : e(basename($dir)) ?>
      </h1>
      <p class="mt-1 text-sm text-muted-foreground">
        <?= count($files) ?> document<?= count($files) === 1 ? '' : 's' ?><?= $dir !== '' ? ' under ' . e($dir) : '' ?> · <?= e($totalSize ?? '') ?>
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <?php if ($app->config->bool('FEATURE_SEARCH')): ?>
      <a class="md-btn md-btn-outline" href="<?= e($req->url(['page' => 'search'])) ?>">
        <?= Icons::svg('search', 'h-4 w-4') ?><span class="hidden sm:inline">Full-text search</span>
      </a>
      <?php endif; ?>
      <?php if ($canClipboard): ?>
      <button type="button" class="md-btn md-btn-outline" data-dialog-open="clipboard-dialog">
        <?= Icons::svg('clipboard-list', 'h-4 w-4') ?><span class="hidden sm:inline">Clipboard preview</span>
      </button>
      <?php endif; ?>
      <?php if ($canUpload): ?>
      <button type="button" class="md-btn md-btn-primary" data-dialog-open="upload-dialog">
        <?= Icons::svg('upload', 'h-4 w-4') ?>Upload .md
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($folders !== []): ?>
  <div class="mb-5 flex flex-wrap gap-2" aria-label="Folders">
    <?php if ($dir !== ''): ?>
    <a class="md-folder-chip" href="<?= e($req->url(['page' => 'browser', 'dir' => dirname($dir) === '.' ? '' : dirname($dir)])) ?>">
      <?= Icons::svg('arrow-left', 'h-4 w-4 text-muted-foreground') ?>..
    </a>
    <?php endif; ?>
    <?php foreach ($folders as $folder): ?>
    <a class="md-folder-chip" href="<?= e($req->url(['page' => 'browser', 'dir' => $folder['path']])) ?>">
      <?= Icons::svg('folder', 'h-4 w-4 text-primary/80') ?>
      <span class="font-medium"><?= e($folder['name']) ?></span>
      <span class="md-sidebar-badge"><?= (int) $folder['count'] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Client-side filter + table -->
  <div class="md-card overflow-hidden">
    <div class="flex flex-wrap items-center gap-3 border-b border-border/70 px-4 py-3">
      <div class="md-input-wrap grow max-w-sm">
        <?= Icons::svg('filter', 'h-4 w-4 text-muted-foreground') ?>
        <input id="md-browser-filter" type="search" class="md-input" placeholder="Filter by name or path…" aria-label="Filter documents">
      </div>
      <p class="ml-auto text-xs text-muted-foreground" id="md-browser-count" aria-live="polite"></p>
    </div>

    <?php if ($files === []): ?>
    <div class="grid place-items-center gap-3 px-6 py-16 text-center">
      <span class="grid h-12 w-12 place-items-center rounded-full bg-muted text-muted-foreground"><?= Icons::svg('inbox', 'h-6 w-6') ?></span>
      <div>
        <p class="font-medium">No documents here yet</p>
        <p class="mt-1 text-sm text-muted-foreground">
          Drop <code class="md-code-inline">.md</code> files into <code class="md-code-inline"><?= e($app->repo()->browseDir()) ?></code>
          <?php if ($canUpload): ?>, or use <strong>Upload .md</strong><?php endif; ?>.
        </p>
      </div>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="md-table w-full" id="md-file-table" data-sort-default="<?= e($sortKey ?? 'modified') ?>" data-sort-dir-default="<?= e($sortDir ?? 'desc') ?>">
        <thead>
          <tr>
            <th class="md-th-sortable" data-sort-key="name" scope="col">Document</th>
            <th class="md-th-sortable hidden sm:table-cell" data-sort-key="size" scope="col">Size</th>
            <th class="md-th-sortable" data-sort-key="modified" scope="col">Modified</th>
            <th class="w-10" scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($files as $file):
            $name = basename((string) $file['path']);
            $dirPart = dirname((string) $file['path']);
            $dirPart = $dirPart === '.' ? '' : $dirPart;
          ?>
          <tr class="md-file-row" data-name="<?= e(mb_strtolower((string) $file['path'])) ?>">
            <td>
              <a class="md-file-link" href="<?= e($req->url(['file' => $file['path']])) ?>">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-primary/10 text-primary"><?= Icons::svg('file-text', 'h-4 w-4') ?></span>
                <span class="min-w-0">
                  <span class="block truncate font-medium leading-tight"><?= e(preg_replace('/\.md$/i', '', $name)) ?></span>
                  <span class="block truncate text-xs text-muted-foreground"><?= e(trim(($dirPart !== '' ? $dirPart . '/' : '') . $name, '/')) ?></span>
                </span>
              </a>
            </td>
            <td class="hidden text-sm text-muted-foreground tabular-nums sm:table-cell" data-size="<?= (int) $file['size'] ?>"><?= e($fmtSize((int) $file['size'])) ?></td>
            <td class="text-sm text-muted-foreground tabular-nums" data-modified="<?= (int) $file['modified'] ?>" title="<?= e(date('Y-m-d H:i', (int) $file['modified'])) ?>"><?= e(date('Y-m-d', (int) $file['modified'])) ?></td>
            <td>
              <?php if ($showDownloadIcon): ?>
              <a class="md-icon-btn" href="<?= e($req->url(['file' => $file['path'], 'download' => 1])) ?>" download aria-label="Download <?= e($name) ?>" title="Download"><?= Icons::svg('download', 'h-4 w-4') ?></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($canUpload): ?>
<!-- ══ Upload dialog ═══════════════════════════════════════════════════ -->
<div class="md-dialog-backdrop" id="upload-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="upload-dialog-title">
  <div class="md-dialog md-dialog-sm">
    <div class="md-dialog-header">
      <h2 id="upload-dialog-title" class="md-dialog-title"><?= Icons::svg('upload', 'h-4 w-4 text-primary') ?>Upload a Markdown file</h2>
      <button type="button" class="md-icon-btn" data-dialog-close aria-label="Close"><?= Icons::svg('x', 'h-4 w-4') ?></button>
    </div>
    <form id="md-upload-form" class="md-dialog-body" enctype="multipart/form-data">
      <div class="md-dropzone" id="md-dropzone" data-upload-zone>
        <?= Icons::svg('file-up', 'h-8 w-8 text-muted-foreground') ?>
        <p class="mt-2 text-sm font-medium">Drop your <code class="md-code-inline">.md</code> file here</p>
        <p class="text-xs text-muted-foreground">or</p>
        <label class="md-btn md-btn-outline md-btn-sm cursor-pointer">
          Choose file
          <input type="file" id="md-upload-input" name="file" accept=".md,.markdown,text/markdown" class="sr-only" required>
        </label>
        <p class="mt-3 text-xs text-muted-foreground" id="md-upload-name" aria-live="polite">Max <?= (int) $app->config->int('MAX_UPLOAD_MB') ?> MB · saved into <?= e($dir === '' ? 'the library root' : $dir) ?></p>
      </div>
      <p class="mt-3 hidden text-sm" id="md-upload-status" role="status"></p>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($canClipboard): ?>
<!-- ══ Clipboard preview dialog ════════════════════════════════════════ -->
<div class="md-dialog-backdrop" id="clipboard-dialog" hidden role="dialog" aria-modal="true" aria-labelledby="clipboard-dialog-title">
  <div class="md-dialog md-dialog-lg">
    <div class="md-dialog-header">
      <h2 id="clipboard-dialog-title" class="md-dialog-title"><?= Icons::svg('clipboard-list', 'h-4 w-4 text-primary') ?>Clipboard preview</h2>
      <button type="button" class="md-icon-btn" data-dialog-close aria-label="Close"><?= Icons::svg('x', 'h-4 w-4') ?></button>
    </div>
    <div class="md-dialog-body grid gap-4 lg:grid-cols-2">
      <div class="flex min-w-0 flex-col gap-2">
        <label for="md-clip-input" class="text-xs font-medium text-muted-foreground">Paste Markdown</label>
        <textarea id="md-clip-input" class="md-textarea md-font-mono min-h-[16rem] flex-1" placeholder="# Hello&#10;&#10;Paste any Markdown here — renders instantly with the full engine (tables, math, mermaid, alerts…)." spellcheck="false"></textarea>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" class="md-btn md-btn-primary md-btn-sm" id="md-clip-render"><?= Icons::svg('eye', 'h-3.5 w-3.5') ?>Preview</button>
          <button type="button" class="md-btn md-btn-ghost md-btn-sm" id="md-clip-paste"><?= Icons::svg('clipboard-list', 'h-3.5 w-3.5') ?>Paste from clipboard</button>
          <?php if ($canSaveClipboard): ?>
          <span class="ml-auto flex items-center gap-1.5" id="md-clip-save-bar" hidden>
            <input id="md-clip-filename" class="md-input md-input-sm w-40" type="text" placeholder="notes.md" aria-label="File name" pattern="[^/\\]*\.md" required>
            <button type="button" class="md-btn md-btn-outline md-btn-sm" id="md-clip-save"><?= Icons::svg('save', 'h-3.5 w-3.5') ?>Save</button>
          </span>
          <?php endif; ?>
        </div>
        <p class="hidden text-sm" id="md-clip-status" role="status"></p>
      </div>
      <div class="min-w-0">
        <p class="mb-2 text-xs font-medium text-muted-foreground">Rendered</p>
        <div id="md-clip-output" class="md-body md-clip-output md-card max-h-[24rem] overflow-y-auto p-4 lg:max-h-[28rem]">
          <p class="text-sm text-muted-foreground">The preview appears here.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php $this->end(); ?>
