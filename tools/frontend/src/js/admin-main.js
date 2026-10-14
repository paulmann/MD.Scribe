/**
 * MD.Viewer 3.0 — admin panel behaviours.
 *
 * One bundle for every admin page: sidebar, quick actions, file manager,
 * editor (live preview, conflict detection, image insertion), settings
 * editor, backups, updater, logs, health, link checker and custom themes.
 */

import { toast, openDialog, closeDialog, toggleSwitch } from './ui.js';
import { initMermaid } from './mermaid.js';
import { initMath } from './katex.js';

const cfg = () => window.MDV_ADMIN || {};

async function adminAction(action, data = {}, { form = null } = {}) {
  const c = cfg();
  let body;
  const headers = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': c.csrf || '' };
  if (form) {
    body = form;
    form.append('_token', c.csrf || '');
  } else {
    headers['Content-Type'] = 'application/x-www-form-urlencoded';
    const params = new URLSearchParams(data);
    params.set('_token', c.csrf || '');
    body = params.toString();
  }
  const response = await fetch(`${c.scriptUrl}?action=${encodeURIComponent(action)}`, {
    method: 'POST', headers, body, credentials: 'same-origin',
  });
  let payload = null;
  try { payload = await response.json(); } catch { /* non-JSON */ }
  if (!response.ok || (payload && payload.ok === false)) {
    const err = new Error(payload?.error || `Action failed (HTTP ${response.status})`);
    err.status = response.status;
    err.payload = payload;
    throw err;
  }
  return payload;
}

async function adminGet(action, params = {}) {
  const c = cfg();
  const qs = new URLSearchParams({ action, ...params });
  const response = await fetch(`${c.scriptUrl}?${qs.toString()}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
  });
  if (response.status === 401) { location.reload(); return null; }
  return response.json();
}

function busy(button, on) {
  if (!button) return;
  button.disabled = on;
  button.style.opacity = on ? '0.6' : '';
}

/* ── Sidebar / logout / quick actions ───────────────────────────────────── */

function initShell() {
  const sidebar = document.getElementById('md-admin-sidebar');
  const backdrop = document.querySelector('[data-admin-sidebar-backdrop]');
  document.querySelector('[data-admin-sidebar-open]')?.addEventListener('click', () => {
    sidebar.dataset.open = '1';
    backdrop.hidden = false;
  });
  const closeSidebar = () => { sidebar.removeAttribute('data-open'); backdrop.hidden = true; };
  document.querySelector('[data-admin-sidebar-close]')?.addEventListener('click', closeSidebar);
  backdrop?.addEventListener('click', closeSidebar);

  document.querySelector('[data-admin-logout]')?.addEventListener('click', async () => {
    try { await adminAction('logout'); } catch { /* ignore */ }
    location.href = `${cfg().scriptUrl}?page=login`;
  });

  document.addEventListener('click', async (event) => {
    const quick = event.target.closest('[data-admin-quick]');
    if (!quick) return;
    busy(quick, true);
    try {
      const result = await adminAction(quick.getAttribute('data-admin-quick'));
      toast(result?.ok ? 'Done.' : (result?.error || 'Done.'), result?.ok ? 'success' : 'error');
      setTimeout(() => location.reload(), 600);
    } catch (err) {
      toast(err.message, 'error');
      busy(quick, false);
    }
  });
}

/* ── Prompt + confirm helpers ───────────────────────────────────────────── */

function promptDialog({ title, label, value = '', hint = '', placeholder = '' }) {
  return new Promise((resolve) => {
    const dialog = document.getElementById('fm-prompt-dialog');
    if (!dialog) { resolve(null); return; }
    dialog.querySelector('#fm-prompt-title').textContent = title;
    dialog.querySelector('#fm-prompt-label').textContent = label;
    const input = dialog.querySelector('#fm-prompt-input');
    input.value = value;
    input.placeholder = placeholder;
    dialog.querySelector('#fm-prompt-hint').textContent = hint;
    const error = dialog.querySelector('#fm-prompt-error');
    error.classList.add('hidden');
    openDialog(dialog);
    setTimeout(() => { input.focus(); input.select(); }, 60);

    const form = dialog.querySelector('#fm-prompt-form');
    const onsubmit = (event) => {
      event.preventDefault();
      const v = input.value.trim();
      if (v === '') { error.textContent = 'Value is required.'; error.classList.remove('hidden'); return; }
      cleanup();
      closeDialog(dialog);
      resolve(v);
    };
    const onclose = () => { cleanup(); resolve(null); };
    const cleanup = () => {
      form.removeEventListener('submit', onsubmit);
      dialog.removeEventListener('md-dialog-close', onclose);
      dialog.querySelectorAll('[data-dialog-close]').forEach((b) => b.removeEventListener('click', onclose));
    };
    form.addEventListener('submit', onsubmit);
    dialog.querySelectorAll('[data-dialog-close]').forEach((b) => b.addEventListener('click', onclose, { once: true }));
  });
}

function confirmDialog(text, okLabel = 'Delete') {
  return new Promise((resolve) => {
    const dialog = document.getElementById('fm-confirm-dialog');
    if (!dialog) { resolve(window.confirm(text)); return; }
    dialog.querySelector('#fm-confirm-text').textContent = text;
    const ok = dialog.querySelector('#fm-confirm-ok');
    ok.textContent = okLabel;
    openDialog(dialog);
    const done = (value) => { cleanup(); closeDialog(dialog); resolve(value); };
    const onOk = () => done(true);
    const onCancel = () => done(false);
    const cleanup = () => {
      ok.removeEventListener('click', onOk);
      dialog.querySelectorAll('[data-dialog-close]').forEach((b) => b.removeEventListener('click', onCancel));
    };
    ok.addEventListener('click', onOk);
    dialog.querySelectorAll('[data-dialog-close]').forEach((b) => b.addEventListener('click', onCancel, { once: true }));
  });
}

/* ── File manager ───────────────────────────────────────────────────────── */

function initFiles() {
  const table = document.getElementById('md-fm-table');
  if (!table) return;

  const filter = document.getElementById('md-fm-filter');
  const rows = [...table.querySelectorAll('.md-fm-row')];
  const count = document.getElementById('md-fm-count');
  const applyFilter = () => {
    const q = (filter?.value || '').toLowerCase();
    let shown = 0;
    rows.forEach((row) => {
      const match = q === '' || (row.dataset.path || '').toLowerCase().includes(q);
      row.dataset.hidden = match ? '' : '1';
      if (match) shown += 1;
    });
    if (count) count.textContent = `${shown} / ${rows.length} entries`;
  };
  filter?.addEventListener('input', applyFilter);
  applyFilter();

  // Row actions
  table.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-fm-row]');
    if (!btn) return;
    const row = btn.closest('.md-fm-row');
    const path = row.dataset.path;
    const action = btn.getAttribute('data-fm-row');

    if (action === 'delete') {
      const ok = await confirmDialog(`Move “${path}” to trash? You can restore it from Backups → Trash.`);
      if (!ok) return;
      busy(btn, true);
      try {
        await adminAction('file.delete', { path });
        row.remove();
        toast(`Deleted “${path}” (recoverable from trash)`, 'success');
      } catch (err) { toast(err.message, 'error'); busy(btn, false); }
      return;
    }
    if (action === 'rmdir') {
      const ok = await confirmDialog(`Remove the empty folder “${path}”?`);
      if (!ok) return;
      try {
        await adminAction('folder.delete', { path });
        row.remove();
        toast('Folder removed', 'success');
      } catch (err) { toast(err.message, 'error'); }
      return;
    }
    if (action === 'rename') {
      const next = await promptDialog({
        title: 'Rename / move', label: 'New path', value: path,
        hint: 'Relative to the content root; folders are created automatically.',
      });
      if (!next || next === path) return;
      try {
        await adminAction('file.rename', { from: path, to: next });
        toast(`Renamed to “${next}”`, 'success');
        setTimeout(() => location.reload(), 500);
      } catch (err) { toast(err.message, 'error'); }
    }
  });

  // Toolbar actions
  document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-fm-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-fm-action');

    if (action === 'mkdir') {
      const path = await promptDialog({ title: 'New folder', label: 'Folder path', placeholder: 'guide/chapter-1' });
      if (!path) return;
      try { await adminAction('folder.create', { path }); toast('Folder created', 'success'); setTimeout(() => location.reload(), 400); }
      catch (err) { toast(err.message, 'error'); }
      return;
    }
    if (action === 'create') {
      const path = await promptDialog({ title: 'New document', label: 'File path', placeholder: 'guide/intro.md', hint: 'Must end with .md' });
      if (!path) return;
      const full = /\.md(markdown)?$/i.test(path) ? path : `${path}.md`;
      try { await adminAction('file.create', { path: full }); toast('Document created — opening editor…', 'success'); setTimeout(() => { location.href = `${cfg().scriptUrl}?page=editor&file=${encodeURIComponent(full)}`; }, 400); }
      catch (err) { toast(err.message, 'error'); }
      return;
    }
    if (action === 'upload-md' || action === 'upload-image') {
      const dialog = document.getElementById('fm-upload-dialog');
      const input = document.getElementById('fm-upload-input');
      const label = document.getElementById('fm-upload-label');
      label.textContent = action === 'upload-md' ? 'Upload Markdown' : 'Upload images';
      if (action === 'upload-md') { input.accept = '.md,.markdown'; input.multiple = true; }
      else { input.accept = '.png,.jpg,.jpeg,.gif,.webp,.avif'; input.multiple = true; }
      input.value = '';
      document.getElementById('fm-upload-progress').innerHTML = '';
      dialog.dataset.mode = action;
      openDialog(dialog);
    }
  });

  // Upload dialog machinery
  const uploadForm = document.getElementById('fm-upload-form');
  const uploadInput = document.getElementById('fm-upload-input');
  const dropzone = document.getElementById('fm-dropzone');
  const progress = document.getElementById('fm-upload-progress');
  if (uploadForm) {
    ['dragenter', 'dragover'].forEach((ev) => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.dataset.dragover = '1'; }));
    ['dragleave', 'drop'].forEach((ev) => dropzone.addEventListener(ev, (e) => { e.preventDefault(); delete dropzone.dataset.dragover; }));
    dropzone.addEventListener('drop', (e) => {
      if (e.dataTransfer?.files?.length) uploadInput.files = e.dataTransfer.files;
      showNames();
    });
    uploadInput.addEventListener('change', showNames);
    function showNames() {
      const names = [...uploadInput.files].map((f) => f.name).join(', ');
      document.getElementById('fm-dropzone-text').textContent = names ? `${uploadInput.files.length} file(s): ${names.slice(0, 80)}` : 'Drop files here';
    }

    uploadForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const dialog = document.getElementById('fm-upload-dialog');
      const mode = dialog.dataset.mode || 'upload-md';
      const files = [...uploadInput.files];
      if (files.length === 0) { toast('Choose files first', 'warning'); return; }
      const dir = document.getElementById('fm-upload-dir').value.trim();
      progress.innerHTML = '';
      let okCount = 0;
      for (const file of files) {
        const line = document.createElement('p');
        line.className = 'text-xs';
        line.textContent = `⏳ ${file.name}`;
        progress.appendChild(line);
        const fd = new FormData();
        fd.append('file', file);
        fd.append('dir', dir);
        try {
          if (mode === 'upload-md') {
            // .md uploads go through the content manager path (bypasses reader flag)
            const text = await file.text();
            const target = (dir ? `${dir.replace(/\/+$/, '')}/` : '') + file.name.replace(/\.markdown$/i, '.md');
            await adminAction('file.write', { path: target, content: text });
          } else {
            await adminAction('upload.image', {}, { form: fd });
          }
          line.textContent = `✔ ${file.name}`;
          line.classList.add('text-emerald-500');
          okCount += 1;
        } catch (err) {
          line.textContent = `✖ ${file.name}: ${err.message}`;
          line.classList.add('text-destructive');
        }
      }
      toast(`${okCount}/${files.length} uploaded`, okCount ? 'success' : 'error');
      if (okCount) setTimeout(() => location.reload(), 900);
    });
  }
}

/* ── Editor ─────────────────────────────────────────────────────────────── */

function initEditor() {
  const source = document.getElementById('md-ed-source');
  if (!source) return;
  const pathText = document.getElementById('md-ed-path-text');
  const status = document.getElementById('md-ed-status');
  const wordsOut = document.getElementById('md-ed-words');
  const hashOut = document.getElementById('md-ed-hash');
  const dirtyBadge = document.getElementById('md-ed-dirty');
  const saveBtn = document.getElementById('md-ed-save');
  const viewBtn = document.getElementById('md-ed-view');
  const previewWrap = document.getElementById('md-ed-preview');
  const panes = document.getElementById('md-ed-panes');

  let currentPath = '';
  let currentHash = '';
  let dirty = false;
  let previewTimer = null;

  const setDirty = (value) => {
    dirty = value;
    dirtyBadge.classList.toggle('hidden', !value);
    saveBtn.disabled = !value;
    status.textContent = value ? 'Unsaved changes' : 'Saved';
  };

  const wordCount = () => {
    const words = (source.value.match(/[\p{L}\p{N}]+/gu) || []).length;
    wordsOut.textContent = `${words.toLocaleString()} words · ~${Math.max(1, Math.round(words / 200))} min read`;
  };

  async function loadFile(path) {
    if (dirty && !window.confirm('Discard unsaved changes?')) return;
    status.textContent = `Loading ${path}…`;
    try {
      const data = await adminGet('file.read', { path });
      if (!data?.ok) throw new Error(data?.error || 'Load failed');
      currentPath = path;
      currentHash = data.hash || '';
      source.value = data.content || '';
      pathText.textContent = path;
      hashOut.textContent = currentHash.slice(0, 8);
      viewBtn.disabled = false;
      viewBtn.onclick = () => window.open(`${cfg().viewerUrl}?file=${encodeURIComponent(path)}`, '_blank', 'noopener');
      setDirty(false);
      wordCount();
      schedulePreview();
      document.querySelectorAll('.md-editor-file').forEach((b) => { b.dataset.active = b.dataset.file === path ? '1' : ''; });
      history.replaceState(null, '', `${cfg().scriptUrl}?page=editor&file=${encodeURIComponent(path)}`);
    } catch (err) {
      status.textContent = 'Error';
      toast(err.message, 'error');
    }
  }

  async function save(asPath = null) {
    const target = asPath || currentPath;
    if (!target) { toast('Open or create a document first', 'warning'); return; }
    busy(saveBtn, true);
    status.textContent = 'Saving…';
    try {
      const data = await adminAction('file.write', { path: target, content: source.value, hash: asPath ? '' : currentHash });
      currentPath = target;
      currentHash = data.hash || '';
      hashOut.textContent = currentHash.slice(0, 8);
      pathText.textContent = target;
      setDirty(false);
      status.textContent = `Saved ${new Date().toLocaleTimeString()}`;
      toast(`Saved “${target}”`, 'success', 2200);
    } catch (err) {
      if (err.status === 409) {
        status.textContent = 'Conflict!';
        const reload = await confirmDialog('The file changed on disk since you opened it. Reload the latest version? (Your unsaved text will be lost.)', 'Reload');
        if (reload) { dirty = false; await loadFile(currentPath); }
      } else {
        status.textContent = 'Save failed';
        toast(err.message, 'error');
      }
    }
    busy(saveBtn, false);
  }

  function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(renderPreview, 450);
  }

  async function renderPreview() {
    if (panes.dataset.preview !== '1') return;
    if (source.value.trim() === '') {
      previewWrap.innerHTML = '<p class="text-sm text-muted-foreground">Preview appears here.</p>';
      return;
    }
    try {
      const data = await adminAction('preview', { content: source.value });
      previewWrap.innerHTML = `<div class="md-body">${data.html}</div>`;
      initMermaid(previewWrap);
      initMath(previewWrap);
    } catch (err) {
      previewWrap.innerHTML = `<p class="text-sm text-destructive">Preview failed: ${err.message}</p>`;
    }
  }

  source.addEventListener('input', () => { setDirty(true); wordCount(); schedulePreview(); });
  source.addEventListener('keydown', (event) => {
    // Ctrl/Cmd+S saves; Tab inserts two spaces
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') { event.preventDefault(); save(); return; }
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'p') { event.preventDefault(); togglePreview(); return; }
    if (event.key === 'Tab') {
      event.preventDefault();
      const { selectionStart: s, selectionEnd: e } = source;
      source.setRangeText('  ', s, e, 'end');
      setDirty(true);
    }
  });
  saveBtn.addEventListener('click', () => save());

  function togglePreview() {
    panes.dataset.preview = panes.dataset.preview === '1' ? '0' : '1';
    if (panes.dataset.preview === '1') renderPreview();
  }
  document.getElementById('md-ed-preview-toggle')?.addEventListener('click', togglePreview);

  document.getElementById('md-ed-new')?.addEventListener('click', async () => {
    const path = await promptDialog({ title: 'New document', label: 'File path', placeholder: 'guide/intro.md', hint: 'Must end with .md' });
    if (!path) return;
    const full = /\.md(markdown)?$/i.test(path) ? path : `${path}.md`;
    try {
      await adminAction('file.create', { path: full, content: `# ${full.replace(/\.md$/i, '').split('/').pop().replace(/-/g, ' ')}\n\n` });
      await loadFile(full);
      location.reload();
    } catch (err) { toast(err.message, 'error'); }
  });

  // File list interactions
  const list = document.getElementById('md-ed-filelist');
  list?.addEventListener('click', (event) => {
    const btn = event.target.closest('.md-editor-file');
    if (btn) loadFile(btn.dataset.file);
  });
  document.getElementById('md-ed-filter')?.addEventListener('input', (event) => {
    const q = event.target.value.toLowerCase();
    list.querySelectorAll('.md-editor-file').forEach((b) => { b.dataset.hidden = b.dataset.file.toLowerCase().includes(q) ? '' : '1'; });
  });

  // Image insertion
  const imageInput = document.getElementById('md-ed-image-input');
  document.getElementById('md-ed-upload-image')?.addEventListener('click', () => imageInput.click());
  imageInput?.addEventListener('change', async () => {
    const file = imageInput.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    fd.append('dir', currentPath.includes('/') ? currentPath.slice(0, currentPath.lastIndexOf('/')) : '');
    try {
      const data = await adminAction('upload.image', {}, { form: fd });
      const snippet = `![${file.name.replace(/\.[^.]+$/, '')}](${data.url_path})`;
      const pos = source.selectionStart || source.value.length;
      source.setRangeText(snippet, pos, pos, 'end');
      setDirty(true);
      schedulePreview();
      toast('Image inserted', 'success', 2000);
    } catch (err) { toast(err.message, 'error'); }
    imageInput.value = '';
  });

  // Save-as / rename from editor
  document.getElementById('ed-rename-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const path = document.getElementById('ed-rename-input').value.trim();
    closeDialog(document.getElementById('ed-rename-dialog'));
    if (path) await save(path);
  });
  pathText.addEventListener('dblclick', () => {
    if (!currentPath) return;
    document.getElementById('ed-rename-input').value = currentPath;
    openDialog(document.getElementById('ed-rename-dialog'));
  });

  window.addEventListener('beforeunload', (event) => {
    if (dirty) { event.preventDefault(); event.returnValue = ''; }
  });

  // Deep link
  const params = new URLSearchParams(location.search);
  const open = params.get('file');
  if (open) loadFile(open);
}

/* ── Settings page ──────────────────────────────────────────────────────── */

function initSettings() {
  const form = document.getElementById('md-settings-form');
  if (!form) return;
  const status = document.getElementById('md-settings-status');
  const saveBtn = document.getElementById('md-settings-save');
  const changed = new Map();

  const mark = () => {
    saveBtn.disabled = changed.size === 0;
    status.textContent = changed.size === 0 ? 'No changes yet.' : `${changed.size} pending change(s).`;
  };

  form.addEventListener('click', (event) => {
    const sw = event.target.closest('[data-setting][data-setting-type="bool"]');
    if (!sw) return;
    const next = toggleSwitch(sw);
    changed.set(sw.dataset.setting, next ? 'true' : 'false');
    mark();
  });
  form.addEventListener('input', (event) => {
    const input = event.target.closest('[data-setting][data-setting-type="string"], [data-setting][data-setting-type="int"]');
    if (!input) return;
    if (input.value === '') { changed.delete(input.dataset.setting); }
    else changed.set(input.dataset.setting, input.value);
    mark();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (changed.size === 0) return;
    busy(saveBtn, true);
    status.textContent = 'Saving…';
    try {
      const response = await fetch(`${cfg().scriptUrl}?action=settings.set`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg().csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(Object.fromEntries(changed)),
        credentials: 'same-origin',
      });
      const data = await response.json();
      if (data.errors?.length) {
        status.textContent = `Errors: ${data.errors.join('; ')}`;
        toast('Some changes were refused — see status line', 'warning', 6000);
      } else {
        toast(`Saved ${data.applied?.length || 0} setting(s)`, 'success');
        status.textContent = 'Saved.';
        changed.clear();
        mark();
      }
    } catch (err) {
      status.textContent = 'Save failed';
      toast(err.message, 'error');
    }
    busy(saveBtn, false);
  });

  document.getElementById('md-settings-reload')?.addEventListener('click', () => location.reload());

  document.querySelector('[data-regenerate-key]')?.addEventListener('click', async (event) => {
    const ok = await confirmDialog('Regenerate the machine API key? External tools using the old key will stop working.', 'Regenerate');
    if (!ok) return;
    busy(event.target.closest('button'), true);
    try {
      const data = await adminAction('settings.regenerate-key');
      await navigator.clipboard?.writeText(data.api_key).catch(() => {});
      toast(`New API key generated and copied to clipboard: ${data.api_key.slice(0, 12)}…`, 'success', 8000);
    } catch (err) { toast(err.message, 'error'); }
  });
}

/* ── Backups page ───────────────────────────────────────────────────────── */

function initBackups() {
  const createBtn = document.querySelector('[data-backup-create]');
  if (!createBtn) return;

  createBtn.addEventListener('click', async () => {
    busy(createBtn, true);
    try {
      const note = document.getElementById('md-backup-note')?.value || '';
      const data = await adminAction('backup.create', { note });
      toast(`Backup ${data.id} created (${data.files} files)`, 'success');
      setTimeout(() => location.reload(), 700);
    } catch (err) { toast(err.message, 'error'); busy(createBtn, false); }
  });

  document.addEventListener('click', async (event) => {
    const restoreBtn = event.target.closest('[data-backup-restore]');
    if (restoreBtn) {
      const id = restoreBtn.closest('tr').dataset.backupId;
      const ok = await confirmDialog(`Restore ${id} over the content root? The current content is saved as a pre-restore backup first.`);
      if (!ok) return;
      busy(restoreBtn, true);
      try {
        const data = await adminAction('backup.restore', { id });
        toast(`Restored ${data.files} files (safety copy: ${data.pre_restore})`, 'success', 6000);
        setTimeout(() => location.reload(), 900);
      } catch (err) { toast(err.message, 'error'); busy(restoreBtn, false); }
      return;
    }
    const delBtn = event.target.closest('[data-backup-delete]');
    if (delBtn) {
      const id = delBtn.closest('tr').dataset.backupId;
      const ok = await confirmDialog(`Delete backup ${id}? This cannot be undone.`);
      if (!ok) return;
      try { await adminAction('backup.delete', { id }); delBtn.closest('tr').remove(); toast('Backup deleted', 'success'); }
      catch (err) { toast(err.message, 'error'); }
      return;
    }
    const trashBtn = event.target.closest('[data-trash-restore]');
    if (trashBtn) {
      const name = trashBtn.closest('tr').dataset.trashName;
      busy(trashBtn, true);
      try { await adminAction('trash.restore', { name }); toast('Restored from trash', 'success'); setTimeout(() => location.reload(), 600); }
      catch (err) { toast(err.message, 'error'); busy(trashBtn, false); }
    }
  });
}

/* ── Updates page ───────────────────────────────────────────────────────── */

function initUpdates() {
  const checkBtn = document.querySelector('[data-update-check]');
  checkBtn?.addEventListener('click', async () => {
    busy(checkBtn, true);
    try {
      const data = await adminGet('update.check', { force: '1' });
      if (!data?.ok) throw new Error(data?.error || 'Check failed');
      toast(data.newer ? `v${data.latest} available` : `Up to date (v${data.latest})`, data.newer ? 'info' : 'success', 5000);
      setTimeout(() => location.reload(), 800);
    } catch (err) { toast(err.message, 'error'); busy(checkBtn, false); }
  });

  const applyBtn = document.querySelector('[data-update-apply]');
  applyBtn?.addEventListener('click', async () => {
    const ok = await confirmDialog('Apply the update now? Changed application files are backed up first; content is never touched.', 'Apply update');
    if (!ok) return;
    busy(applyBtn, true);
    applyBtn.innerHTML = 'Applying… (this downloads the release archive)';
    try {
      const data = await adminAction('update.apply');
      toast(`Updated ${data.from} → ${data.to} (${data.replaced} files replaced, backup ${data.backup})`, 'success', 8000);
      setTimeout(() => location.reload(), 1500);
    } catch (err) { toast(err.message, 'error', 8000); busy(applyBtn, false); }
  });
}

/* ── Logs / health / links ──────────────────────────────────────────────── */

function initLogs() {
  document.querySelector('[data-logs-refresh]')?.addEventListener('click', async (event) => {
    busy(event.target.closest('button'), true);
    try {
      const data = await adminGet('logs.tail');
      const appOut = document.getElementById('md-app-logs');
      if (appOut && data?.logs) {
        appOut.innerHTML = data.logs.map((l) => `<details open class="border-b border-border/60"><summary class="cursor-pointer px-4 py-2.5 font-mono text-xs font-semibold text-muted-foreground">${l.file} (${l.lines.length})</summary><pre class="md-log-pre max-h-96 overflow-auto px-4 pb-3 font-mono text-[11px] leading-relaxed">${escapeHtml(l.lines.join('\n'))}</pre></details>`).join('') || '<p class="p-6 text-center text-sm text-muted-foreground">Empty.</p>';
      }
      toast('Logs refreshed', 'success', 1600);
    } catch (err) { toast(err.message, 'error'); }
    busy(event.target.closest('button'), false);
  });
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function initHealth() {
  document.querySelector('[data-health-run]')?.addEventListener('click', async (event) => {
    busy(event.target.closest('button'), true);
    try {
      const data = await adminGet('health.run');
      const body = document.querySelector('#md-health-table tbody');
      const icons = { ok: '✔', warn: '⚠', fail: '✖' };
      body.innerHTML = data.checks.map((c) => `<tr>
        <td class="font-medium">${escapeHtml(c.label)}</td>
        <td><span class="md-badge md-badge-${c.status === 'ok' ? 'success' : c.status === 'warn' ? 'warning' : 'error'}">${icons[c.status]} ${c.status}</span></td>
        <td class="font-mono text-xs text-muted-foreground break-all">${escapeHtml(c.detail)}</td>
      </tr>`).join('');
      toast('Health checks re-run', 'success', 1600);
    } catch (err) { toast(err.message, 'error'); }
    busy(event.target.closest('button'), false);
  });
}

function initLinks() {
  const btn = document.querySelector('[data-links-check]');
  btn?.addEventListener('click', async () => {
    busy(btn, true);
    const out = document.getElementById('md-links-result');
    out.innerHTML = '<p class="text-sm text-muted-foreground">Scanning corpus…</p>';
    try {
      const data = await adminGet('links.check');
      const parts = [];
      parts.push(`<p class="text-xs text-muted-foreground mb-2">${data.documents} documents scanned in ${data.took_ms} ms</p>`);
      const section = (title, items, fmtRow) => {
        if (!items?.length) return `<p class="text-sm"><span class="md-badge md-badge-success">✔</span> ${title}: none</p>`;
        return `<p class="text-sm mb-1"><span class="md-badge md-badge-warning">${items.length}</span> ${title}</p>
          <ul class="mb-3 max-h-48 overflow-y-auto rounded-md border border-border/60 p-2 font-mono text-[11px] text-muted-foreground">${items.slice(0, 100).map(fmtRow).map((li) => `<li class="py-0.5">${li}</li>`).join('')}</ul>`;
      };
      parts.push(section('Broken document links / anchors', data.broken_links, (l) => `${escapeHtml(l.source)}:${l.line} → ${escapeHtml(l.target)} <em>(${l.kind})</em>`));
      parts.push(section('Missing media', data.broken_media, (l) => `${escapeHtml(l.source)}:${l.line} → ${escapeHtml(l.target)}`));
      parts.push(section('Orphaned documents (no inbound links)', data.orphans, (o) => escapeHtml(o)));
      out.innerHTML = parts.join('');
    } catch (err) { out.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(err.message)}</p>`; }
    busy(btn, false);
  });
}

/* ── Custom themes page ─────────────────────────────────────────────────── */

function initThemesAdmin() {
  const form = document.getElementById('md-theme-form');
  if (!form) return;
  const light = document.getElementById('theme-light');
  const dark = document.getElementById('theme-dark');
  const preview = document.querySelector('#theme-preview .md-btn');

  const updatePreview = () => {
    const rootDark = document.documentElement.classList.contains('dark');
    preview.style.background = rootDark ? dark.value : light.value;
    preview.style.color = rootDark ? document.getElementById('theme-dark-fg').value : document.getElementById('theme-light-fg').value;
  };
  [light, dark].forEach((el) => el.addEventListener('input', updatePreview));
  updatePreview();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const name = document.getElementById('theme-name').value.trim();
    const payload = {
      id: name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
      name,
      light: { primary: light.value.trim(), 'primary-foreground': document.getElementById('theme-light-fg').value.trim(), ring: light.value.trim() },
      dark: { primary: dark.value.trim(), 'primary-foreground': document.getElementById('theme-dark-fg').value.trim(), ring: dark.value.trim() },
    };
    try {
      const response = await fetch(`${cfg().scriptUrl}?action=theme.save`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg().csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      });
      const data = await response.json();
      if (!data.ok) throw new Error(data.error || 'Save failed');
      toast(`Theme “${data.theme.name}” saved`, 'success');
      setTimeout(() => location.reload(), 600);
    } catch (err) { toast(err.message, 'error'); }
  });

  document.addEventListener('click', async (event) => {
    const del = event.target.closest('[data-theme-delete]');
    if (!del) return;
    const id = del.getAttribute('data-theme-delete');
    const ok = await confirmDialog(`Delete custom theme “${id}”?`);
    if (!ok) return;
    try { await adminAction('theme.delete', { id }); toast('Theme deleted', 'success'); setTimeout(() => location.reload(), 500); }
    catch (err) { toast(err.message, 'error'); }
  });
}

/* ── Index page ─────────────────────────────────────────────────────────── */

function initIndexPage() {
  document.querySelector('[data-admin-action="index.rebuild"]')?.addEventListener('click', async (event) => {
    const btn = event.target.closest('button');
    busy(btn, true);
    try {
      const data = await adminAction('index.rebuild');
      toast(`Index rebuilt: ${data.documents} docs, ${data.terms} terms in ${data.took_ms} ms`, 'success', 5000);
      setTimeout(() => location.reload(), 800);
    } catch (err) { toast(err.message, 'error'); busy(btn, false); }
  });
}

/* ── Boot ───────────────────────────────────────────────────────────────── */

function boot() {
  initShell();
  initFiles();
  initEditor();
  initSettings();
  initBackups();
  initUpdates();
  initLogs();
  initHealth();
  initLinks();
  initThemesAdmin();
  initIndexPage();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
