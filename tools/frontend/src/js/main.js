/**
 * MD.Scribe 3.0 — front-end entry point.
 *
 * One small bundle for the whole public app. Heavy vendor libraries
 * (Mermaid, KaTeX) are injected lazily, only on pages that need them and
 * only from local assets — never from a CDN.
 */

import { initDropdowns, initDialogs, initSheets, toast } from './ui.js';
import { initThemeControls, onThemeChange } from './theme.js';
import { initViewer, applyPrefs } from './viewer.js';
import { initPalette } from './palette.js';
import { initBrowser } from './browser.js';
import { initGlossary } from './glossary.js';
import { initMermaid, watchModeForMermaid } from './mermaid.js';
import { initMath } from './katex.js';

function boot() {
  initThemeControls();
  initDropdowns();
  initDialogs();
  initSheets();
  initViewer();
  initPalette();
  initBrowser();

  // Rich content — only what the page actually contains gets loaded.
  const article = document.getElementById('md-article');
  if (article) {
    if (article.dataset.glossary === '1') initGlossary(article);
    if (article.dataset.hasMermaid === '1') { initMermaid(article); watchModeForMermaid(); }
    if (article.dataset.hasMath === '1') initMath(article);
  } else {
    // Non-document pages may still embed samples
    initGlossary(document);
  }

  // Reading prefs affect layout variables — re-apply after webfonts settle
  applyPrefs();
  document.fonts?.ready.then(() => applyPrefs()).catch(() => { /* noop */ });

  // Re-render math after theme switch (KaTeX inherits currentColor)
  onThemeChange(() => {
    document.querySelectorAll('[data-mdx-math][data-math-rendered="1"]').forEach((el) => {
      // KaTeX output is theme-agnostic (uses currentColor); nothing to do.
      void el;
    });
  });

  console.info(
    `%c MD.Scribe %c v${window.MDV_CONFIG?.version || '3'} — self-hosted, zero-CDN `,
    'background:#18181b;color:#fafafa;border-radius:4px 0 0 4px;padding:2px 6px;font-weight:600',
    'background:#e4e4e7;color:#18181b;border-radius:0 4px 4px 0;padding:2px 6px',
  );
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}

window.MDVToast = window.MDVToast || { show: toast };
