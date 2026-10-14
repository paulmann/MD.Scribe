/**
 * MD.Viewer 3.0 — theme engine.
 *
 * State: { preset, neutral, accent, radius, font, mode } persisted in
 * localStorage['mdv.theme']. Applying = setting data-* attributes + the
 * .dark class on <html>; the pre-paint bootstrap in the layout reads the
 * same storage key, so there is never a flash of the wrong theme.
 *
 * mode: 'light' | 'dark' | 'system' — system follows prefers-color-scheme
 * live (the bootstrap installs the media query listener).
 */

const STORAGE_KEY = 'mdv.theme';

const DEFAULTS = {
  preset: null, neutral: null, accent: null,
  radius: 'md', font: 'inter', mode: null,
};

let state = { ...DEFAULTS };
const listeners = [];

export function loadState() {
  try {
    const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
    if (raw && typeof raw === 'object') state = { ...DEFAULTS, ...raw };
  } catch { /* corrupted storage — fall back to defaults */ }
  const boot = window.MDV_THEME_BOOT;
  if (boot) {
    // Merge anything the pre-paint bootstrap already knew.
    state = { ...state, ...Object.fromEntries(Object.entries(boot).filter(([, v]) => v != null && typeof v !== 'function')) };
  }
  return state;
}

export function getState() { return { ...state }; }

export function isDark() {
  return document.documentElement.classList.contains('dark');
}

export function effectiveMode() {
  const m = state.mode || 'system';
  if (m !== 'system') return m;
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function persist() {
  try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch { /* private mode */ }
}

export function apply() {
  const root = document.documentElement;
  if (state.neutral) root.setAttribute('data-neutral', state.neutral);
  if (state.accent) root.setAttribute('data-accent', state.accent);
  root.setAttribute('data-radius', state.radius || 'md');
  root.setAttribute('data-font', state.font || 'inter');
  root.setAttribute('data-mode-pref', state.mode || 'system');
  if (window.MDV_THEME_BOOT?.applyMode) {
    window.MDV_THEME_BOOT.applyMode(state.mode || 'system');
  } else {
    root.classList.toggle('dark', effectiveMode() === 'dark');
  }
  syncControls();
  for (const fn of listeners) { try { fn(state); } catch { /* keep going */ } }
}

export function setMode(mode) {
  state.mode = mode;
  persist();
  apply();
}

export function setPreset(preset, neutral, accent) {
  state.preset = preset;
  state.neutral = neutral || null;
  state.accent = accent || null;
  persist();
  apply();
}

export function setPart(key, value) {
  if (key === 'preset') state.preset = value;
  else state[key] = value;
  persist();
  apply();
}

export function resetTheme() {
  const root = document.documentElement;
  state = { ...DEFAULTS };
  try { localStorage.removeItem(STORAGE_KEY); } catch { /* noop */ }
  root.removeAttribute('data-neutral');
  root.removeAttribute('data-accent');
  // Fall back to server defaults
  const preset = root.getAttribute('data-theme-preset') || 'zinc';
  const modeDefault = root.getAttribute('data-mode-default') || 'system';
  state.preset = null;
  state.mode = null;
  persist();
  root.setAttribute('data-radius', 'md');
  root.setAttribute('data-font', 'inter');
  root.setAttribute('data-mode-pref', modeDefault);
  if (window.MDV_THEME_BOOT?.applyMode) window.MDV_THEME_BOOT.applyMode(modeDefault);
  void preset;
  syncControls();
  for (const fn of listeners) { try { fn(state); } catch { /* noop */ } }
}

export function onThemeChange(fn) { listeners.push(fn); }

/** Reflect state onto every control on the page (dropdowns, segmented, swatches). */
function syncControls() {
  const mode = state.mode || document.documentElement.getAttribute('data-mode-default') || 'system';
  document.querySelectorAll('[data-mode-select]').forEach((el) => {
    const active = el.getAttribute('data-mode-select') === mode;
    if (el.hasAttribute('role') && el.getAttribute('role') === 'radio') el.setAttribute('aria-checked', active ? 'true' : 'false');
    if (el.hasAttribute('data-checked')) el.setAttribute('data-checked', active ? 'true' : 'false');
    el.classList.toggle('md-segment-active', active);
  });

  const preset = state.preset || document.documentElement.getAttribute('data-theme-preset');
  document.querySelectorAll('[data-theme-preset-btn]').forEach((el) => {
    el.setAttribute('aria-pressed', el.getAttribute('data-theme-preset-btn') === preset ? 'true' : 'false');
  });

  const neutral = state.neutral || document.documentElement.getAttribute('data-neutral') || 'zinc';
  document.querySelectorAll('[data-neutral-select]').forEach((el) => {
    el.setAttribute('aria-checked', el.getAttribute('data-neutral-select') === neutral ? 'true' : 'false');
  });
  const accent = state.accent || document.documentElement.getAttribute('data-accent') || 'default';
  document.querySelectorAll('[data-accent-select]').forEach((el) => {
    el.setAttribute('aria-checked', el.getAttribute('data-accent-select') === accent ? 'true' : 'false');
  });
  const radius = state.radius || 'md';
  document.querySelectorAll('[data-radius-select]').forEach((el) => {
    el.setAttribute('aria-checked', el.getAttribute('data-radius-select') === radius ? 'true' : 'false');
  });
  const font = state.font || 'inter';
  document.querySelectorAll('[data-font-select]').forEach((el) => {
    el.setAttribute('aria-checked', el.getAttribute('data-font-select') === font ? 'true' : 'false');
  });

  paintPreviews();
}

/** Token probe: read computed CSS variables for a neutral/accent combination. */
function probeTokens(attrs, dark = false) {
  const probe = document.createElement('span');
  Object.assign(probe.dataset, attrs);
  probe.style.display = 'none';
  if (dark) probe.classList.add('mdv-dark');
  document.body.appendChild(probe);
  const cs = getComputedStyle(probe);
  const out = {};
  for (const t of ['background', 'foreground', 'card', 'border', 'muted', 'primary', 'primary-foreground', 'ring']) {
    out[t] = cs.getPropertyValue('--' + t).trim();
  }
  probe.remove();
  return out;
}

/** Paint theme-card previews + swatch dots from live tokens. */
export function paintPreviews() {
  const dark = isDark();
  document.querySelectorAll('[data-theme-preset-btn]').forEach((card) => {
    card.classList.toggle('mdv-dark', dark);
    const tokens = probeTokens({
      neutral: card.getAttribute('data-neutral') || 'zinc',
      accent: card.getAttribute('data-accent') || 'default',
    }, dark);
    card.style.setProperty('--md-pv-bg', tokens.background);
    card.style.setProperty('--md-pv-card', tokens.card);
    card.style.setProperty('--md-pv-border', tokens.border);
    card.style.setProperty('--md-pv-fg', tokens.foreground);
    card.style.setProperty('--md-pv-muted', tokens.muted);
    card.style.setProperty('--md-pv-accent', tokens.primary);
    card.style.setProperty('--md-pv-accent-fg', tokens['primary-foreground']);
  });
  document.querySelectorAll('[data-neutral-dot]').forEach((dot) => {
    const tokens = probeTokens({ neutral: dot.getAttribute('data-neutral-dot') }, dark);
    dot.style.background = `linear-gradient(135deg, ${tokens.background} 0 50%, ${tokens.foreground} 50% 100%)`;
  });
  document.querySelectorAll('[data-accent-dot]').forEach((dot) => {
    const id = dot.getAttribute('data-accent-dot');
    const neutral = document.documentElement.getAttribute('data-neutral') || 'zinc';
    const tokens = id === 'default'
      ? probeTokens({ neutral }, dark)
      : probeTokens({ neutral, accent: id }, dark);
    dot.style.background = tokens.primary;
  });
}

/** Wire every theme control on the page. */
export function initThemeControls() {
  loadState();
  apply();

  document.addEventListener('click', (event) => {
    const modeBtn = event.target.closest('[data-mode-select]');
    if (modeBtn) {
      event.preventDefault();
      setMode(modeBtn.getAttribute('data-mode-select'));
      closeMenus();
      return;
    }
    const presetBtn = event.target.closest('[data-theme-preset-btn]');
    if (presetBtn) {
      const presetId = presetBtn.getAttribute('data-theme-preset-btn');
      // Presets carry their combination; reading the gallery attributes keeps
      // the PHP catalog the single source of truth.
      const neutral = presetBtn.getAttribute('data-neutral');
      const accent = presetBtn.getAttribute('data-accent');
      setPreset(presetId, neutral, accent);
      flashStatus(presetBtn, `Theme “${presetBtn.querySelector('.font-medium')?.textContent || presetId}” applied`);
      return;
    }
    for (const [attr, key] of [['data-neutral-select', 'neutral'], ['data-accent-select', 'accent'], ['data-radius-select', 'radius'], ['data-font-select', 'font']]) {
      const el = event.target.closest(`[${attr}]`);
      if (el) {
        setPart(key, el.getAttribute(attr));
        if (key === 'neutral' || key === 'accent') state.preset = null, persist();
        flashStatus(el, null);
        return;
      }
    }
    if (event.target.closest('[data-theme-reset]')) {
      resetTheme();
      if (window.MDVToast) window.MDVToast.show('Theme reset to defaults', 'info');
    }
  });

  function closeMenus() {
    document.querySelectorAll('[data-dropdown-menu]:not([hidden])').forEach((m) => {
      m.hidden = true;
      m.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
    });
  }

  function flashStatus(anchor, message) {
    const status = document.querySelector('[data-theme-status]');
    if (!status) return;
    status.textContent = message || 'Applied';
    clearTimeout(flashStatus._t);
    flashStatus._t = setTimeout(() => { status.textContent = ''; }, 1600);
    void anchor;
  }
}
