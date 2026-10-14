/**
 * MD.Viewer 3.0 — command palette (Ctrl/Cmd+K).
 *
 * shadcn "command" style: fuzzy document/heading search via ?api=suggest,
 * quick actions (navigate, mode switch, print), full keyboard control.
 */

import { apiGet } from './ui.js';
import { setMode } from './theme.js';

let overlay = null;
let input = null;
let list = null;
let items = [];
let activeIndex = -1;
let debounceTimer = null;
let open = false;

const ACTIONS = () => {
  const cfg = window.MDV_CONFIG || {};
  const url = (params) => {
    const qs = new URLSearchParams(params).toString();
    return `${cfg.scriptUrl || ''}${qs ? `?${qs}` : ''}`;
  };
  const actions = [
    { id: 'go-home', group: 'Actions', label: 'Go to home', icon: 'house', run: () => { window.location.href = url({}); } },
    { id: 'go-library', group: 'Actions', label: 'Browse library', icon: 'files', run: () => { window.location.href = url({ page: 'browser' }); } },
    { id: 'go-search', group: 'Actions', label: 'Full-text search page', icon: 'search', run: () => { window.location.href = url({ page: 'search' }); } },
    { id: 'go-themes', group: 'Actions', label: 'Theme catalog', icon: 'palette', run: () => { window.location.href = url({ page: 'themes' }); } },
    { id: 'go-settings', group: 'Actions', label: 'Settings', icon: 'settings', run: () => { window.location.href = url({ page: 'settings' }); } },
    { id: 'mode-light', group: 'Appearance', label: 'Switch to light mode', icon: 'sun', run: () => { setMode('light'); close(); } },
    { id: 'mode-dark', group: 'Appearance', label: 'Switch to dark mode', icon: 'moon', run: () => { setMode('dark'); close(); } },
    { id: 'mode-system', group: 'Appearance', label: 'Follow system theme', icon: 'monitor', run: () => { setMode('system'); close(); } },
    { id: 'print', group: 'Actions', label: 'Print this page', icon: 'printer', run: () => { close(); window.print(); } },
    { id: 'toc-current', group: 'This page', label: 'Jump to table of contents', icon: 'list', run: () => {
      close();
      document.querySelector('#md-toc-sidebar, .md-toc')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } },
  ];
  // In-document heading jumps
  document.querySelectorAll('.md-h[id]').forEach((h) => {
    actions.push({
      id: `h-${h.id}`, group: 'On this page', label: h.textContent.replace(/[#\u200b]/g, '').trim().slice(0, 90),
      icon: 'heading', run: () => { close(); document.getElementById(h.id)?.scrollIntoView({ behavior: 'smooth' }); window.history.replaceState(null, '', `#${h.id}`); },
    });
  });
  return actions;
};

function iconSvg(name) {
  const paths = {
    house: '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    files: '<path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/><path d="M3 7.6v12.8A1.6 1.6 0 0 0 4.6 22h9.8"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    palette: '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
    settings: '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    moon: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
    monitor: '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
    printer: '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3h12v6"/><path d="M6 14h12v8H6z"/>',
    list: '<path d="M3 5h.01M3 12h.01M3 19h.01M8 5h13M8 12h13M8 19h13"/>',
    heading: '<path d="M6 12h12M6 20V4M18 20V4"/>',
    'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8M16 13H8M16 17H8"/>',
    hash: '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
  };
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-muted-foreground">${paths[name] || paths.hash}</svg>`;
}

function renderItem(item, index) {
  const el = document.createElement('button');
  el.type = 'button';
  el.className = 'md-palette-item';
  el.dataset.active = index === activeIndex ? 'true' : 'false';
  el.dataset.index = String(index);
  el.setAttribute('role', 'option');
  el.innerHTML = `${iconSvg(item.icon)}<span class="min-w-0 truncate"></span>${item.sub ? `<span class="md-palette-sub truncate"></span>` : ''}`;
  el.querySelectorAll('span')[0].textContent = item.label;
  if (item.sub) el.querySelector('.md-palette-sub').textContent = item.sub;
  return el;
}

function render(results) {
  items = results;
  activeIndex = results.length > 0 ? 0 : -1;
  list.innerHTML = '';
  if (results.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'md-palette-empty';
    empty.textContent = 'No results. Try another query.';
    list.appendChild(empty);
    return;
  }
  let group = null;
  results.forEach((item, index) => {
    if (item.group !== group) {
      group = item.group;
      const h = document.createElement('p');
      h.className = 'md-palette-group';
      h.textContent = group;
      list.appendChild(h);
    }
    list.appendChild(renderItem(item, index));
  });
}

function setActive(delta) {
  if (items.length === 0) return;
  activeIndex = (activeIndex + delta + items.length) % items.length;
  list.querySelectorAll('.md-palette-item').forEach((el) => {
    const active = Number(el.dataset.index) === activeIndex;
    el.dataset.active = active ? 'true' : 'false';
    if (active) el.scrollIntoView({ block: 'nearest' });
  });
}

async function query(q) {
  const cfg = window.MDV_CONFIG || {};
  const actionList = ACTIONS().filter((a) => a.label.toLowerCase().includes(q.toLowerCase()));
  if (q.trim() === '') {
    render(actionList.slice(0, 10));
    return;
  }
  let docs = [];
  try {
    const data = await apiGet('suggest', { q: q.trim().slice(0, 120) });
    const docUrl = (file, anchor) => `${cfg.scriptUrl || ''}?file=${encodeURIComponent(file)}${anchor ? `#${anchor}` : ''}`;
    docs = (data?.results || []).map((r) => (r.kind === 'heading'
      ? { id: `s-${r.path}-${r.anchor}`, group: `In “${r.title}”`, label: r.heading, sub: r.path, icon: 'heading', run: () => { close(); window.location.href = docUrl(r.path, r.anchor); } }
      : { id: `s-${r.path}`, group: 'Documents', label: r.title, sub: r.path, icon: 'file-text', run: () => { close(); window.location.href = docUrl(r.path); } }));
  } catch { /* offline — actions only */ }
  render([...docs.slice(0, 14), ...actionList.slice(0, 6)]);
}

export function openPalette() {
  if (open) return;
  overlay = document.getElementById('md-palette');
  if (!overlay) return;
  input = overlay.querySelector('#md-palette-input');
  list = overlay.querySelector('#md-palette-list');
  overlay.hidden = false;
  open = true;
  document.body.style.overflow = 'hidden';
  input.value = '';
  query('');
  input.focus();
}

export function close() {
  if (!open || !overlay) return;
  overlay.hidden = true;
  open = false;
  document.body.style.overflow = '';
}

export function initPalette() {
  const overlayEl = document.getElementById('md-palette');
  if (!overlayEl) return;

  document.addEventListener('keydown', (event) => {
    if ((event.key === 'k' || event.key === 'K' || event.key === 'л' || event.key === 'Л') && (event.metaKey || event.ctrlKey)) {
      event.preventDefault();
      if (open) close(); else openPalette();
      return;
    }
    if (!open) return;
    if (event.key === 'Escape') { close(); return; }
    if (event.key === 'ArrowDown') { event.preventDefault(); setActive(1); return; }
    if (event.key === 'ArrowUp') { event.preventDefault(); setActive(-1); return; }
    if (event.key === 'Enter') {
      event.preventDefault();
      items[activeIndex]?.run();
    }
  });

  overlayEl.addEventListener('click', (event) => {
    if (event.target === overlayEl) close();
    const item = event.target.closest('.md-palette-item');
    if (item) items[Number(item.dataset.index)]?.run();
  });

  overlayEl.querySelector('#md-palette-input')?.addEventListener('input', (event) => {
    clearTimeout(debounceTimer);
    const value = event.target.value;
    debounceTimer = setTimeout(() => query(value), 130);
  });

  document.getElementById('md-search-trigger')?.addEventListener('click', (event) => {
    event.preventDefault();
    openPalette();
  });
}
