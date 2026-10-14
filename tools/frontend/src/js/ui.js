/**
 * MD.Viewer 3.0 — UI primitives (shadcn/ui-style behaviours, vanilla JS):
 * dropdown menus, dialogs, sheets, toasts, switches, focus trapping.
 */

/* ── Toasts ─────────────────────────────────────────────────────────────── */

const TOAST_ICONS = {
  success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 mt-px"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
  error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 mt-px"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
  warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 mt-px"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
  info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 mt-px"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
};

export function toast(message, type = 'info', timeout = 3800) {
  const list = document.getElementById('md-toasts');
  if (!list) return null;
  const li = document.createElement('li');
  li.className = `md-toast md-toast-${type}`;
  li.setAttribute('role', 'status');
  li.innerHTML = `${TOAST_ICONS[type] || TOAST_ICONS.info}<span></span>`;
  li.querySelector('span').textContent = message;
  list.appendChild(li);
  const kill = () => {
    li.dataset.leaving = '1';
    setTimeout(() => li.remove(), 200);
  };
  const timer = timeout > 0 ? setTimeout(kill, timeout) : null;
  li.addEventListener('click', () => { clearTimeout(timer); kill(); });
  return kill;
}

window.MDVToast = { show: toast };

/* ── Dropdown menus ─────────────────────────────────────────────────────── */

export function initDropdowns() {
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-dropdown-trigger]');
    if (trigger) {
      event.preventDefault();
      event.stopPropagation();
      const wrap = trigger.closest('[data-dropdown]');
      const menu = wrap?.querySelector('[data-dropdown-menu]');
      if (!menu) return;
      const wasHidden = menu.hidden;
      closeAllDropdowns();
      if (wasHidden) {
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        menu.querySelector('[role="menuitem"], button, a')?.focus();
      }
      return;
    }
    if (!event.target.closest('[data-dropdown-menu]')) closeAllDropdowns();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeAllDropdowns();
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      const menu = document.querySelector('[data-dropdown-menu]:not([hidden])');
      if (!menu) return;
      event.preventDefault();
      const items = [...menu.querySelectorAll('[role="menuitem"]')];
      if (!items.length) return;
      const idx = items.indexOf(document.activeElement);
      const next = event.key === 'ArrowDown'
        ? items[(idx + 1) % items.length]
        : items[(idx - 1 + items.length) % items.length];
      next.focus();
    }
  });

  document.addEventListener('focusin', (event) => {
    if (!event.target.closest('[data-dropdown]')) closeAllDropdowns();
  });
}

function closeAllDropdowns() {
  document.querySelectorAll('[data-dropdown-menu]:not([hidden])').forEach((menu) => {
    menu.hidden = true;
    menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
  });
}

/* ── Dialogs ────────────────────────────────────────────────────────────── */

const openDialogs = [];

export function openDialog(dialog) {
  if (!dialog || dialog.dataset.open === '1') return;
  dialog.dataset.open = '1';
  dialog.hidden = false;
  document.body.style.overflow = 'hidden';
  openDialogs.push(dialog);
  const focusTarget = dialog.querySelector('[data-autofocus], input, textarea, button');
  focusTarget?.focus();
}

export function closeDialog(dialog) {
  if (!dialog || dialog.dataset.open !== '1') return;
  dialog.dataset.open = '';
  dialog.hidden = true;
  const idx = openDialogs.indexOf(dialog);
  if (idx >= 0) openDialogs.splice(idx, 1);
  if (openDialogs.length === 0) document.body.style.overflow = '';
}

export function topDialog() { return openDialogs[openDialogs.length - 1] || null; }

export function initDialogs() {
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');
    if (opener) {
      event.preventDefault();
      openDialog(document.getElementById(opener.getAttribute('data-dialog-open')));
      return;
    }
    const closer = event.target.closest('[data-dialog-close]');
    if (closer) {
      closeDialog(closer.closest('.md-dialog-backdrop'));
      return;
    }
    // Backdrop click closes
    if (event.target.classList?.contains('md-dialog-backdrop')) {
      closeDialog(event.target);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      const dialog = topDialog();
      if (dialog) { closeDialog(dialog); event.stopPropagation(); return; }
      const sheet = document.querySelector('.md-sheet-backdrop:not([hidden])');
      if (sheet) sheet.hidden = true;
    }
    if (event.key === 'Tab') {
      const dialog = topDialog() || document.querySelector('.md-sheet-backdrop:not([hidden]) .md-sheet');
      if (dialog) trapFocus(dialog, event);
    }
  });
}

function trapFocus(container, event) {
  const focusables = [...container.querySelectorAll(
    'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])',
  )].filter((el) => el.offsetParent !== null || el === document.activeElement);
  if (focusables.length === 0) return;
  const first = focusables[0];
  const last = focusables[focusables.length - 1];
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}

/* ── Sheets ─────────────────────────────────────────────────────────────── */

export function initSheets() {
  const openSheet = (sheet) => {
    if (!sheet) return;
    sheet.hidden = false;
    document.body.style.overflow = 'hidden';
  };
  const closeSheet = (sheet) => {
    if (!sheet) return;
    sheet.hidden = true;
    if (!topDialog()) document.body.style.overflow = '';
  };

  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-action="sidebar"]')) { openSheet(document.getElementById('md-sheet')); return; }
    if (event.target.closest('[data-action="mobile-toc"]')) { openSheet(document.getElementById('md-toc-mobile')); return; }
    if (event.target.closest('[data-sheet-close]')) { closeSheet(event.target.closest('.md-sheet-backdrop')); return; }
    if (event.target.classList?.contains('md-sheet-backdrop')) closeSheet(event.target);
    // TOC links close the sheet
    if (event.target.closest('.md-sheet-backdrop a[href^="#"]')) closeSheet(event.target.closest('.md-sheet-backdrop'));
  });
}

/* ── Switches (visual state handled by aria-checked) ────────────────────── */

export function toggleSwitch(el) {
  const next = el.getAttribute('aria-checked') !== 'true';
  el.setAttribute('aria-checked', next ? 'true' : 'false');
  return next;
}

/* ── Small fetch helper with CSRF + JSON ────────────────────────────────── */

export async function api(action, data = {}, { method = 'POST', form = null } = {}) {
  const cfg = window.MDV_CONFIG || {};
  const url = `${cfg.scriptUrl || ''}?api=${encodeURIComponent(action)}`;
  const headers = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': cfg.csrf || '' };
  let body = null;
  if (form) {
    body = form;
    form.append('_token', cfg.csrf || '');
  } else if (method === 'POST') {
    headers['Content-Type'] = 'application/x-www-form-urlencoded';
    const params = new URLSearchParams(data);
    params.set('_token', cfg.csrf || '');
    body = params.toString();
  }
  const response = await fetch(url, { method, headers, body, credentials: 'same-origin' });
  let payload = null;
  try { payload = await response.json(); } catch { /* non-JSON */ }
  if (!response.ok || (payload && payload.ok === false)) {
    const err = new Error(payload?.error || `Request failed (${response.status})`);
    err.status = response.status;
    err.payload = payload;
    throw err;
  }
  return payload;
}

export async function apiGet(action, params = {}) {
  const cfg = window.MDV_CONFIG || {};
  const qs = new URLSearchParams({ api: action, ...params });
  const response = await fetch(`${cfg.scriptUrl || ''}?${qs.toString()}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  });
  return response.json();
}
