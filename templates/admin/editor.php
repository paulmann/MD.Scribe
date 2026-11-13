<?php
/**
 * MD.Viewer 3.0 — admin editor: split-pane Markdown editing with live
 * server-side preview (the real engine), conflict detection (sha256),
 * frontmatter helpers and image upload at cursor.
 *
 * @var MdViewer\Core\App $app
 * @var string $openFile
 * @var array $tree
 */

use MdViewer\View\Icons;

$req = $app->request;
$pageTitle = 'Editor';
?>
<?php $this->extend('layout/admin'); ?>
<?php $this->section('content'); ?>

<div class="md-editor-shell">
  <!-- File picker column -->
  <aside class="md-editor-files md-card" aria-label="Documents">
    <div class="p-2">
      <input id="md-ed-filter" class="md-input md-input-sm" type="search" placeholder="Find document…" aria-label="Filter documents">
    </div>
    <div class="md-editor-filelist" id="md-ed-filelist">
      <?php foreach ($tree['files'] as $file): if (($file['type'] ?? 'markdown') !== 'markdown') { continue; } ?>
      <button type="button" class="md-editor-file" data-file="<?= e((string) $file['path']) ?>">
        <?= Icons::svg('file-text', 'h-3.5 w-3.5 shrink-0 text-muted-foreground') ?>
        <span class="truncate"><?= e((string) $file['path']) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <div class="border-t border-border p-2">
      <button type="button" class="md-btn md-btn-outline md-btn-sm w-full" id="md-ed-new"><?= Icons::svg('file-plus', 'h-3.5 w-3.5') ?>New document</button>
    </div>
  </aside>

  <!-- Editor column -->
  <section class="md-editor-main md-card" aria-label="Editor">
    <header class="md-editor-toolbar">
      <span class="md-editor-path" id="md-ed-path" title="Current document">
        <?= Icons::svg('file-text', 'h-4 w-4') ?>
        <span id="md-ed-path-text"><?= $openFile !== '' ? e($openFile) : 'No document open' ?></span>
      </span>
      <span class="md-editor-meta" id="md-ed-meta"></span>
      <span class="ml-auto flex items-center gap-1.5">
        <span class="md-format-group" role="group" aria-label="Insert Markdown">
          <button type="button" class="md-icon-btn font-bold" data-md-insert="bold" title="Bold (Ctrl+B)" aria-label="Bold">B</button>
          <button type="button" class="md-icon-btn italic" data-md-insert="italic" title="Italic (Ctrl+I)" aria-label="Italic">I</button>
          <button type="button" class="md-icon-btn font-mono text-[11px]" data-md-insert="code" title="Inline code" aria-label="Inline code">&lt;/&gt;</button>
          <button type="button" class="md-icon-btn" data-md-insert="link" title="Link" aria-label="Link"><?= Icons::svg('external-link', 'h-3.5 w-3.5') ?></button>
          <button type="button" class="md-icon-btn" data-md-insert="wikilink" title="Wikilink to another document" aria-label="Wikilink">[[ ]]</button>
          <button type="button" class="md-icon-btn" data-md-insert="list" title="Bullet list" aria-label="Bullet list"><?= Icons::svg('list', 'h-3.5 w-3.5') ?></button>
          <button type="button" class="md-icon-btn" data-md-insert="alert" title="Alert block" aria-label="Alert block"><?= Icons::svg('info', 'h-3.5 w-3.5') ?></button>
          <button type="button" class="md-icon-btn" data-md-insert="table" title="Table" aria-label="Table">&#9638;</button>
        </span>
        <span class="md-badge md-badge-muted hidden" id="md-ed-dirty"><?= Icons::svg('circle-alert', 'h-3 w-3') ?>unsaved</span>
        <button type="button" class="md-btn md-btn-ghost md-btn-sm" id="md-ed-preview-toggle" title="Toggle preview (Ctrl+P)"><?= Icons::svg('eye', 'h-3.5 w-3.5') ?><span class="hidden sm:inline">Preview</span></button>
        <button type="button" class="md-btn md-btn-ghost md-btn-sm" id="md-ed-upload-image" title="Insert image"><?= Icons::svg('upload', 'h-3.5 w-3.5') ?><span class="hidden sm:inline">Image</span></button>
        <button type="button" class="md-btn md-btn-ghost md-btn-sm" id="md-ed-view" title="Open in viewer" disabled><?= Icons::svg('external-link', 'h-3.5 w-3.5') ?></button>
        <button type="button" class="md-btn md-btn-primary md-btn-sm" id="md-ed-save" disabled><?= Icons::svg('save', 'h-3.5 w-3.5') ?>Save <kbd class="md-kbd ml-1">⌘S</kbd></button>
      </span>
    </header>
    <div class="md-editor-panes" id="md-ed-panes" data-preview="1">
      <div class="md-editor-pane-source">
        <textarea id="md-ed-source" class="md-editor-textarea md-font-mono" spellcheck="false" placeholder="# Start writing…&#10;&#10;Markdown, math, mermaid, alerts — the full engine renders in the preview." aria-label="Markdown source"></textarea>
      </div>
      <div class="md-editor-pane-preview" id="md-ed-preview-wrap">
        <div id="md-ed-preview" class="md-body md-clip-output p-5">
          <p class="text-sm text-muted-foreground">Preview appears here.</p>
        </div>
      </div>
    </div>
    <footer class="md-editor-statusbar">
      <span id="md-ed-status">Ready</span>
      <span class="ml-auto flex items-center gap-3">
        <span id="md-ed-words"></span>
        <span id="md-ed-hash" class="font-mono opacity-60"></span>
      </span>
    </footer>
  </section>
</div>

<!-- Rename dialog for the editor -->
<div class="md-dialog-backdrop" id="ed-rename-dialog" hidden role="dialog" aria-modal="true">
  <div class="md-dialog md-dialog-sm">
    <div class="md-dialog-header"><h2 class="md-dialog-title"><?= Icons::svg('type', 'h-4 w-4 text-primary') ?>Save as / rename</h2>
      <button type="button" class="md-icon-btn" data-dialog-close aria-label="Close"><?= Icons::svg('x', 'h-4 w-4') ?></button></div>
    <form class="md-dialog-body" id="ed-rename-form">
      <label class="md-label" for="ed-rename-input">Path (relative to content root)</label>
      <input class="md-input md-font-mono" id="ed-rename-input" type="text" required>
      <p class="md-hint">Example: <code class="md-code-inline">guide/intro.md</code> — folders are created automatically.</p>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" class="md-btn md-btn-ghost md-btn-sm" data-dialog-close>Cancel</button>
        <button type="submit" class="md-btn md-btn-primary md-btn-sm">Save</button>
      </div>
    </form>
  </div>
</div>

<input type="file" id="md-ed-image-input" accept=".png,.jpg,.jpeg,.gif,.webp,.avif" class="hidden">

<script nonce="<?= e($app->nonce()) ?>">
document.addEventListener('DOMContentLoaded', function () {
  var textarea = document.getElementById('md-ed-source');
  if (!textarea) return;

  function insert(kind) {
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var selected = textarea.value.slice(start, end);
    var text;
    switch (kind) {
      case 'bold': text = '**' + (selected || 'bold') + '**'; break;
      case 'italic': text = '*' + (selected || 'italic') + '*'; break;
      case 'code': text = '`' + (selected || 'code') + '`'; break;
      case 'link': text = '[' + (selected || 'text') + '](https://)'; break;
      case 'wikilink': text = '[[' + (selected || 'Page') + ']]'; break;
      case 'list': text = '- ' + selected; break;
      case 'alert': text = '> [!NOTE]\n> ' + selected; break;
      case 'table': text = '| Column | Column |\n| --- | --- |\n|  |  |'; break;
      default: return;
    }
    textarea.setRangeText(text, start, end, 'end');
    // The editor listens for input: this keeps the dirty flag, word count and
    // the live preview in sync with an insertion.
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
  }

  document.querySelectorAll('[data-md-insert]').forEach(function (button) {
    button.addEventListener('click', function () {
      insert(button.getAttribute('data-md-insert'))
    });
  });

  textarea.addEventListener('keydown', function (event) {
    if (!(event.ctrlKey || event.metaKey)) return;
    var key = event.key.toLowerCase();
    if (key === 'b') { event.preventDefault(); insert('bold'); }
    else if (key === 'i') { event.preventDefault(); insert('italic'); }
  });
});
</script>
<?php $this->end(); ?>
