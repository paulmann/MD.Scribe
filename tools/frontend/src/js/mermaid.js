/**
 * MD.Scribe 3.0 — Mermaid loader.
 *
 * Mermaid ships locally (assets/vendor/mermaid/mermaid.min.js — the full
 * IIFE build, no chunk imports, no CDN). Loaded only when the page actually
 * contains diagrams. Server-side auto-repair already ran; the client shows
 * repair diagnostics and handles parse/render errors gracefully. On mode
 * change (light ↔ dark) diagrams re-render with the matching Mermaid theme.
 */

import { toast } from './ui.js';

let mermaidPromise = null;
let renderedOnce = false;

function injectScript(src) {
  return new Promise((resolve, reject) => {
    const el = document.createElement('script');
    el.src = src;
    el.async = true;
    el.onload = resolve;
    el.onerror = () => reject(new Error(`Failed to load ${src}`));
    document.head.appendChild(el);
  });
}

function vendorUrl(path) {
  const cfg = window.MDV_CONFIG || {};
  const base = (cfg.assetBase || '').replace(/\/$/, '');
  return `${base}/assets/vendor/${path}`;
}

function loadMermaid() {
  if (!mermaidPromise) {
    mermaidPromise = injectScript(vendorUrl('mermaid/mermaid.min.js'))
      .then(() => window.mermaid)
      .catch((err) => { mermaidPromise = null; throw err; });
  }
  return mermaidPromise;
}

function currentTheme() {
  return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
}

export async function initMermaid(root = document) {
  const blocks = [...root.querySelectorAll('pre.mermaid')];
  if (blocks.length === 0) return;

  let mermaid;
  try {
    mermaid = await loadMermaid();
  } catch {
    blocks.forEach((block) => {
      const wrap = block.closest('.md-mermaid');
      wrap?.setAttribute('data-render-error', 'load');
    });
    toast('Mermaid could not be loaded — diagram sources are shown instead', 'error', 6000);
    return;
  }

  mermaid.initialize({
    startOnLoad: false,
    theme: currentTheme(),
    securityLevel: 'strict',
    suppressErrorRendering: true,
    fontFamily: 'var(--md-font-sans), sans-serif',
    flowchart: { useMaxWidth: true, htmlLabels: true, curve: 'basis' },
    sequence: { useMaxWidth: true },
  });

  for (const [index, block] of blocks.entries()) {
    const wrap = block.closest('.md-mermaid') || block.parentElement;
    const source = block.getAttribute('data-mermaid-source') || block.textContent || '';
    const fixesCount = Number(block.getAttribute('data-mermaid-fixes') || 0);

    // Repair diagnostics badge
    if (fixesCount > 0 && wrap && !wrap.querySelector('.md-mermaid-fixes')) {
      const badge = document.createElement('span');
      badge.className = 'md-mermaid-fixes';
      let fixes = [];
      try { fixes = JSON.parse(block.getAttribute('data-mermaid-fixes-json') || '[]'); } catch { /* noop */ }
      const details = fixes.map((f) => `L${f.line} [${f.rule}] ${f.detail}`).join('\n');
      badge.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>`;
      badge.append(`${fixesCount} auto-repair${fixesCount === 1 ? '' : 's'}`);
      badge.title = `Server-side auto-repairs applied (repair engine v${block.getAttribute('data-mermaid-repair-version') || '?'}):\n${details}`;
      wrap.querySelector('.md-mermaid-bar')?.appendChild(badge);
    }

    try {
      const parseResult = await mermaid.parse(source, { suppressErrors: true });
      if (parseResult === false) throw new Error('mermaid.parse() rejected the diagram');
      const id = `mdv-mermaid-${Date.now().toString(36)}-${index}`;
      const { svg } = await mermaid.render(id, source);
      block.innerHTML = svg;
      wrap?.setAttribute('data-rendered', '1');
    } catch (err) {
      wrap?.setAttribute('data-render-error', 'parse');
      wrap?.classList.add('md-mermaid-error');
      const msg = err?.message ? String(err.message).split('\n')[0] : 'unknown error';
      const bar = wrap?.querySelector('.md-mermaid-bar');
      if (bar && !bar.querySelector('.md-mermaid-err')) {
        const span = document.createElement('span');
        span.className = 'md-mermaid-fixes md-mermaid-err';
        span.style.color = 'oklch(0.72 0.19 22)';
        span.textContent = `⚠ ${msg.slice(0, 160)}`;
        span.title = 'The diagram source is kept below so it can be fixed and copied.';
        bar.appendChild(span);
      }
      // Keep the source visible for repair
      block.textContent = source;
      console.info('[MD.Scribe] Mermaid parse error:', msg);
    }
  }
  renderedOnce = true;
}

/** Re-render on light/dark switch so diagram themes follow. */
export function watchModeForMermaid() {
  if (!document.querySelector('pre.mermaid')) return;
  let lastMode = currentTheme();
  const observer = new MutationObserver(() => {
    const mode = currentTheme();
    if (mode === lastMode) return;
    lastMode = mode;
    if (!renderedOnce) return;
    document.querySelectorAll('.md-mermaid[data-rendered="1"]').forEach((wrap) => {
      const source = wrap.querySelector('[data-mermaid-source]')?.getAttribute('data-mermaid-source');
      if (!source) return;
      wrap.removeAttribute('data-rendered');
      const pre = wrap.querySelector('pre.mermaid');
      if (pre) {
        pre.textContent = source;
        pre.removeAttribute('data-processed');
      }
    });
    initMermaid(document);
  });
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}
