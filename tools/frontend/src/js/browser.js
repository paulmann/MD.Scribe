/**
 * MD.Viewer 3.0 — file browser behaviours: client-side filter + column
 * sorting, upload dialog (drag & drop), clipboard preview dialog (render /
 * save / paste).
 */

import { api, toast, openDialog } from './ui.js';
import { initMermaid } from './mermaid.js';
import { initMath } from './katex.js';
import { initGlossary } from './glossary.js';

/* ── Filter + sort ──────────────────────────────────────────────────────── */

function initTable() {
  const table = document.getElementById('md-file-table');
  if (!table) return;
  const filterInput = document.getElementById('md-browser-filter');
  const countOut = document.getElementById('md-browser-count');
  const rows = [...table.querySelectorAll('tbody tr.md-file-row')];
  let sortKey = table.dataset.sortDefault || 'modified';
  let sortDir = (table.dataset.sortDirDefault || 'desc') === 'asc' ? 1 : -1;

  const applyFilter = () => {
    const q = (filterInput?.value || '').trim().toLowerCase();
    let visible = 0;
    rows.forEach((row) => {
      const match = q === '' || (row.dataset.name || '').includes(q);
      row.dataset.hidden = match ? '' : '1';
      if (match) visible += 1;
    });
    if (countOut) {
      countOut.textContent = q === ''
        ? `${rows.length} document${rows.length === 1 ? '' : 's'}`
        : `${visible} of ${rows.length} shown`;
    }
  };

  const applySort = () => {
    const tbody = table.querySelector('tbody');
    const sorted = [...rows].sort((a, b) => {
      let cmp = 0;
      if (sortKey === 'name') cmp = (a.dataset.name || '').localeCompare(b.dataset.name || '');
      if (sortKey === 'size') cmp = Number(a.dataset.size || a.querySelector('[data-size]')?.dataset.size || 0) - Number(b.dataset.size || b.querySelector('[data-size]')?.dataset.size || 0);
      if (sortKey === 'modified') cmp = Number(a.querySelector('[data-modified]')?.dataset.modified || 0) - Number(b.querySelector('[data-modified]')?.dataset.modified || 0);
      return cmp * sortDir || (a.dataset.name || '').localeCompare(b.dataset.name || '');
    });
    sorted.forEach((row) => tbody.appendChild(row));
    table.querySelectorAll('th[data-sort-key]').forEach((th) => {
      if (th.dataset.sortKey === sortKey) th.dataset.sorted = sortDir === 1 ? 'asc' : 'desc';
      else delete th.dataset.sorted;
    });
  };

  filterInput?.addEventListener('input', applyFilter);
  table.querySelectorAll('th[data-sort-key]').forEach((th) => {
    th.addEventListener('click', () => {
      const key = th.dataset.sortKey;
      if (key === sortKey) sortDir *= -1;
      else { sortKey = key; sortDir = key === 'name' ? 1 : -1; }
      applySort();
    });
  });

  applySort();
  applyFilter();
}

/* ── Upload dialog ──────────────────────────────────────────────────────── */

function initUpload() {
  const form = document.getElementById('md-upload-form');
  if (!form) return;
  const input = document.getElementById('md-upload-input');
  const zone = document.getElementById('md-dropzone');
  const nameOut = document.getElementById('md-upload-name');
  const status = document.getElementById('md-upload-status');
  const cfg = window.MDV_CONFIG || {};

  const showName = () => {
    if (input?.files?.length) {
      const f = input.files[0];
      nameOut.textContent = `${f.name} · ${(f.size / 1024).toFixed(1)} KB`;
    }
  };
  input?.addEventListener('change', showName);

  ['dragenter', 'dragover'].forEach((ev) => zone?.addEventListener(ev, (e) => { e.preventDefault(); zone.dataset.dragover = '1'; }));
  ['dragleave', 'drop'].forEach((ev) => zone?.addEventListener(ev, (e) => { e.preventDefault(); delete zone.dataset.dragover; }));
  zone?.addEventListener('drop', (e) => {
    const file = e.dataTransfer?.files?.[0];
    if (!file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    showName();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const file = input?.files?.[0];
    if (!file) { toast('Choose a file first', 'warning'); return; }
    if (!/\.md(markdown)?$/i.test(file.name)) { toast('Only .md files are accepted', 'error'); return; }
    const maxBytes = (cfg.maxUploadMb || 8) * 1024 * 1024;
    if (file.size > maxBytes) { toast(`File exceeds the ${cfg.maxUploadMb || 8} MB cap`, 'error'); return; }

    status.className = 'mt-3 text-sm text-muted-foreground';
    status.textContent = 'Uploading…';
    try {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('dir', cfg.currentDir || '');
      const result = await api('upload', {}, { form: fd });
      status.textContent = '';
      toast('Uploaded — opening…', 'success');
      setTimeout(() => { window.location.href = `${cfg.scriptUrl || ''}?file=${encodeURIComponent(result.path)}`; }, 500);
    } catch (err) {
      status.className = 'mt-3 text-sm text-destructive';
      status.textContent = err.message || 'Upload failed';
    }
  });
}

/* ── Clipboard preview ──────────────────────────────────────────────────── */

const CLIP_KEY = 'mdv.clip.draft';

function initClipboard() {
  const dialog = document.getElementById('clipboard-dialog');
  if (!dialog) return;
  const input = document.getElementById('md-clip-input');
  const output = document.getElementById('md-clip-output');
  const renderBtn = document.getElementById('md-clip-render');
  const pasteBtn = document.getElementById('md-clip-paste');
  const saveBar = document.getElementById('md-clip-save-bar');
  const saveBtn = document.getElementById('md-clip-save');
  const filename = document.getElementById('md-clip-filename');
  const status = document.getElementById('md-clip-status');
  const cfg = window.MDV_CONFIG || {};

  // Restore draft
  try { input.value = sessionStorage.getItem(CLIP_KEY) || ''; } catch { /* noop */ }
  const persistDraft = () => { try { sessionStorage.setItem(CLIP_KEY, input.value); } catch { /* noop */ } };
  input?.addEventListener('input', persistDraft);

  const openBtn = document.querySelector('[data-dialog-open="clipboard-dialog"]');
  openBtn?.addEventListener('click', () => {
    openDialog(dialog);
    setTimeout(() => input?.focus(), 60);
  });

  pasteBtn?.addEventListener('click', async () => {
    try {
      input.value = await navigator.clipboard.readText();
      persistDraft();
      toast('Pasted from clipboard', 'success', 1800);
    } catch {
      toast('The browser blocked clipboard access — paste manually (Ctrl+V)', 'warning', 5000);
      input.focus();
    }
  });

  renderBtn?.addEventListener('click', async () => {
    const markdown = input.value;
    if (markdown.trim() === '') { toast('Paste some Markdown first', 'warning'); return; }
    output.innerHTML = '<p class="text-sm text-muted-foreground">Rendering…</p>';
    try {
      const result = await api('render', { markdown, dir: cfg.currentDir || '' });
      output.innerHTML = `<div class="md-body">${result.html}</div>`;
      if (saveBar) saveBar.hidden = false;
      initMermaid(output);
      initMath(output);
      initGlossary(output);
    } catch (err) {
      output.innerHTML = `<p class="text-sm text-destructive">${err.message || 'Render failed'}</p>`;
    }
  });

  saveBtn?.addEventListener('click', async () => {
    const name = (filename?.value || '').trim();
    if (!name || !/\.md$/i.test(name)) { toast('Enter a file name ending in .md', 'warning'); return; }
    status.className = 'text-sm text-muted-foreground';
    status.hidden = false;
    status.textContent = 'Saving…';
    try {
      const result = await api('save', { content: input.value, filename: name, dir: cfg.currentDir || '' });
      toast('Saved — opening…', 'success');
      setTimeout(() => { window.location.href = `${cfg.scriptUrl || ''}?file=${encodeURIComponent(result.path)}`; }, 450);
    } catch (err) {
      status.className = 'text-sm text-destructive';
      status.textContent = err.message || 'Save failed';
    }
  });
}

export function initBrowser() {
  initTable();
  initUpload();
  initClipboard();
}
