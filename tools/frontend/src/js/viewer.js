/**
 * MD.Viewer 3.0 — viewer behaviours: reading preferences, progress bar,
 * TOC scrollspy, copy engine (code / quote / source / mermaid), word-wrap
 * toggle, image lightbox, print, and the settings-page feature toggles.
 */

import { toast, toggleSwitch } from './ui.js';

/* ── Reading preferences ────────────────────────────────────────────────── */

const PREFS_KEY = 'mdv.prefs';
const WIDTHS = ['narrow', 'comfortable', 'wide', 'full'];
const WIDTH_LABELS = { narrow: 'Narrow', comfortable: 'Comfortable', wide: 'Wide', full: 'Full width' };
const FONT_STEPS = [0.85, 0.925, 1, 1.1, 1.2, 1.3];
const LEADING_STEPS = [1.5, 1.65, 1.75, 1.9, 2.1];

let prefs = { fontIndex: 2, widthIndex: 1, leadingIndex: 2 };

function loadPrefs() {
  try {
    const raw = JSON.parse(localStorage.getItem(PREFS_KEY) || 'null');
    if (raw && typeof raw === 'object') prefs = { ...prefs, ...raw };
  } catch { /* defaults */ }
}

export function applyPrefs() {
  const root = document.documentElement;
  root.style.setProperty('--md-reader-scale', String(FONT_STEPS[prefs.fontIndex] ?? 1));
  root.style.setProperty('--md-reader-leading', String(LEADING_STEPS[prefs.leadingIndex] ?? 1.75));
  const reader = document.getElementById('md-reader');
  if (reader) reader.dataset.width = WIDTHS[prefs.widthIndex] || 'comfortable';
  document.querySelectorAll('[data-pref]').forEach((stepper) => {
    const out = stepper.querySelector('[data-value]');
    if (!out) return;
    const kind = stepper.getAttribute('data-pref');
    if (kind === 'fontSize') out.textContent = `${Math.round((FONT_STEPS[prefs.fontIndex] ?? 1) * 100)}%`;
    if (kind === 'width') out.textContent = WIDTH_LABELS[WIDTHS[prefs.widthIndex]] || 'Comfortable';
    if (kind === 'lineHeight') out.textContent = String(LEADING_STEPS[prefs.leadingIndex] ?? 1.75);
  });
}

function persistPrefs() {
  try { localStorage.setItem(PREFS_KEY, JSON.stringify(prefs)); } catch { /* noop */ }
}

function stepPref(kind, delta) {
  if (kind === 'fontSize') prefs.fontIndex = clamp(prefs.fontIndex + delta, 0, FONT_STEPS.length - 1);
  if (kind === 'width') prefs.widthIndex = clamp(prefs.widthIndex + delta, 0, WIDTHS.length - 1);
  if (kind === 'lineHeight') prefs.leadingIndex = clamp(prefs.leadingIndex + delta, 0, LEADING_STEPS.length - 1);
  persistPrefs();
  applyPrefs();
}

const clamp = (v, min, max) => Math.min(max, Math.max(min, v));

/* ── Reading progress bar ───────────────────────────────────────────────── */

function initProgress() {
  const bar = document.getElementById('md-progress');
  if (!bar) return;
  let ticking = false;
  const update = () => {
    ticking = false;
    const doc = document.documentElement;
    const max = doc.scrollHeight - doc.clientHeight;
    const pct = max > 0 ? Math.min(100, (doc.scrollTop / max) * 100) : 0;
    bar.style.width = `${pct}%`;
  };
  window.addEventListener('scroll', () => {
    if (!ticking) { ticking = true; requestAnimationFrame(update); }
  }, { passive: true });
  update();
}

/* ── TOC scrollspy ──────────────────────────────────────────────────────── */

function initScrollspy() {
  const container = document.querySelector('[data-scrollspy]');
  if (!container) return;
  const links = [...container.querySelectorAll('a[data-toc-anchor]')];
  if (links.length === 0) return;
  const byAnchor = new Map(links.map((a) => [a.getAttribute('data-toc-anchor'), a]));
  const headings = [...document.querySelectorAll('.md-h[id]')]
    .filter((h) => byAnchor.has(h.id));
  if (headings.length === 0) return;

  let current = null;
  const setActive = (id) => {
    if (id === current) return;
    current = id;
    links.forEach((a) => a.classList.remove('md-toc-active'));
    const link = byAnchor.get(id);
    if (link) {
      link.classList.add('md-toc-active');
      // Keep the sidebar in view when the active entry drifts away
      const sidebar = link.closest('.md-toc-sidebar, aside');
      if (sidebar) {
        const lr = link.getBoundingClientRect();
        const sr = sidebar.getBoundingClientRect();
        if (lr.top < sr.top + 20 || lr.bottom > sr.bottom - 20) {
          link.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
      }
    }
  };

  const observer = new IntersectionObserver((entries) => {
    const visible = entries.filter((e) => e.isIntersecting)
      .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
    if (visible.length > 0) setActive(visible[0].target.id);
  }, { rootMargin: '-80px 0px -70% 0px', threshold: [0, 1] });

  headings.forEach((h) => observer.observe(h));
}

/* ── Copy engine (delegated) ────────────────────────────────────────────── */

const CHECK_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M20 6 9 17l-5-5"/></svg>';

async function copyText(text, button) {
  try {
    if (navigator.clipboard?.writeText && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
    }
    markCopied(button);
    return true;
  } catch {
    toast('Clipboard access was blocked by the browser', 'error');
    return false;
  }
}

function markCopied(button) {
  if (!button) return;
  button.dataset.copied = '1';
  const original = button.innerHTML;
  const label = button.querySelector('span');
  if (label) label.textContent = 'Copied';
  else button.insertAdjacentHTML('beforeend', CHECK_SVG);
  setTimeout(() => {
    delete button.dataset.copied;
    button.innerHTML = original;
  }, 1600);
}

function initCopy() {
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-type]');
    if (!button) return;
    event.preventDefault();
    const kind = button.getAttribute('data-copy-type');

    if (kind === 'code') {
      const code = button.closest('.md-code')?.querySelector('pre code');
      if (code) await copyText(code.textContent || '', button);
      return;
    }
    if (kind === 'quote') {
      const quote = button.closest('.md-quote');
      if (quote) {
        const clone = quote.cloneNode(true);
        clone.querySelector('.md-quote-copy')?.remove();
        await copyText((clone.textContent || '').trim(), button);
      }
      return;
    }
    if (kind === 'mermaid') {
      const block = button.closest('.md-mermaid');
      const source = block?.querySelector('[data-mermaid-source]')?.getAttribute('data-mermaid-source');
      if (source) await copyText(source, button);
      return;
    }
    if (kind === 'source') {
      const url = button.getAttribute('data-copy-source');
      if (!url) return;
      try {
        const response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        await copyText(await response.text(), button);
      } catch {
        toast('Could not fetch the document source', 'error');
      }
    }
  });

  // Word-wrap toggle inside code blocks
  document.addEventListener('click', (event) => {
    const wrapBtn = event.target.closest('[data-action="wrap"]');
    if (!wrapBtn) return;
    const block = wrapBtn.closest('.md-code');
    if (!block) return;
    const on = block.getAttribute('data-wrap') === '1';
    if (on) { block.removeAttribute('data-wrap'); wrapBtn.removeAttribute('data-active'); }
    else { block.setAttribute('data-wrap', '1'); wrapBtn.setAttribute('data-active', '1'); }
  });
}

/* ── Image lightbox ─────────────────────────────────────────────────────── */

function initLightbox() {
  const box = document.getElementById('md-lightbox');
  if (!box) return;
  const img = box.querySelector('#md-lightbox-img');
  const caption = box.querySelector('#md-lightbox-caption');

  document.addEventListener('click', (event) => {
    const target = event.target.closest('img[data-lightbox]');
    if (!target) return;
    img.src = target.currentSrc || target.src;
    img.alt = target.alt || '';
    const figcaption = target.closest('figure')?.querySelector('figcaption');
    caption.textContent = figcaption?.textContent || target.getAttribute('title') || '';
    caption.hidden = caption.textContent === '';
    box.hidden = false;
    document.body.style.overflow = 'hidden';
  });

  const close = () => { box.hidden = true; document.body.style.overflow = ''; img.src = ''; };
  box.addEventListener('click', (event) => {
    if (event.target === box || event.target === img || event.target.closest('[data-lightbox-close]')) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !box.hidden) close();
  });
}

/* ── Print + misc actions ───────────────────────────────────────────────── */

function initActions() {
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-action="print"]')) { window.print(); }
  });
}

/* ── Reading steppers (popover + settings page) ─────────────────────────── */

function initSteppers() {
  document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-pref] [data-step]');
    if (!btn) return;
    const kind = btn.closest('[data-pref]').getAttribute('data-pref');
    stepPref(kind, Number(btn.getAttribute('data-step')) || 0);
  });
}

/* ── Settings page: feature toggles (cookies) + reset ───────────────────── */

const COOKIE_CONSENT_KEY = 'mdv.cookieConsent';
const FEAT_PREFIX = 'mdv_feat_';

function cookiesAllowed() {
  const stored = localStorage.getItem(COOKIE_CONSENT_KEY);
  return stored === null ? true : stored === '1'; // first-party, opt-out
}

function setFeatureCookie(key, value) {
  const maxAge = 60 * 60 * 24 * 365;
  document.cookie = `${FEAT_PREFIX}${key}=${value ? '1' : '0'}; path=/; max-age=${maxAge}; SameSite=Lax`;
}

function clearFeatureCookies() {
  document.cookie.split(';').forEach((c) => {
    const name = c.split('=')[0].trim();
    if (name.startsWith(FEAT_PREFIX)) {
      document.cookie = `${name}=; path=/; max-age=0; SameSite=Lax`;
    }
  });
}

export function initSettingsPage() {
  const toggles = document.querySelectorAll('[data-feature-toggle]');
  if (toggles.length === 0) return;

  const applyBtn = document.querySelector('[data-settings-apply]');
  const status = document.querySelector('[data-settings-status]');
  let pending = 0;

  const note = document.getElementById('md-cookie-note');
  if (note) {
    note.textContent = cookiesAllowed()
      ? 'Changes apply on the next page load.'
      : 'Cookie storage is off — toggles will not persist.';
  }

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      if (toggle.disabled) return;
      const next = toggleSwitch(toggle);
      const key = toggle.getAttribute('data-feature-toggle');
      if (!cookiesAllowed()) {
        toggle.setAttribute('aria-checked', String(!next));
        toast('Enable “First-party preference storage” to change rendering features', 'warning', 5000);
        return;
      }
      setFeatureCookie(key, next);
      pending += 1;
      if (applyBtn) applyBtn.hidden = false;
      if (status) status.textContent = `${pending} change${pending === 1 ? '' : 's'} pending`;
    });
  });

  applyBtn?.addEventListener('click', () => location.reload());

  document.querySelector('[data-settings-reset]')?.addEventListener('click', () => {
    ['mdv.prefs', 'mdv.theme'].forEach((k) => { try { localStorage.removeItem(k); } catch { /* noop */ } });
    clearFeatureCookies();
    toast('Preferences reset — reloading…', 'success', 1400);
    setTimeout(() => location.reload(), 700);
  });

  const consent = document.querySelector('[data-cookie-consent]');
  if (consent) {
    consent.setAttribute('aria-checked', cookiesAllowed() ? 'true' : 'false');
    consent.addEventListener('click', () => {
      const next = toggleSwitch(consent);
      localStorage.setItem(COOKIE_CONSENT_KEY, next ? '1' : '0');
      if (!next) {
        clearFeatureCookies();
        toast('Feature cookies cleared; toggles no longer persist', 'info');
      } else {
        toast('First-party preference storage enabled', 'success');
      }
      if (note) {
        note.textContent = next ? 'Changes apply on the next page load.' : 'Cookie storage is off — toggles will not persist.';
      }
    });
  }
}

/* ── Entry ──────────────────────────────────────────────────────────────── */

export function initViewer() {
  loadPrefs();
  applyPrefs();
  initProgress();
  initScrollspy();
  initCopy();
  initLightbox();
  initActions();
  initSteppers();
  initSettingsPage();
}
