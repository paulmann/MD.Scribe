/**
 * MD.Viewer 3.0 — glossary tooltips (port of the v2.4 engine).
 *
 * Contract (unchanged from v2, so server markup stays compatible):
 *  - terms:   span.glossary-term[data-gterm="tbl-N:row:cols"]
 *  - content: read LIVE from the table cells  #gd-{slug}-{row}-{col},
 *             header from the table's <thead><th>, first-column cell
 *             (data-gc="1") becomes the tooltip header.
 *  - behaviour: hover (150 ms delay), touch (tap toggles, 2 s grace),
 *    keyboard focus, viewport-aware flipping, cached HTML per data-gterm.
 */

const tipCache = new Map();
let activeTip = null;
let activeTerm = null;
let hideTimer = null;
let showTimer = null;
let graceUntil = 0;

function ensureTip() {
  if (activeTip) return activeTip;
  const tip = document.createElement('div');
  tip.className = 'g-tooltip';
  tip.setAttribute('role', 'tooltip');
  document.body.appendChild(tip);
  activeTip = tip;
  return tip;
}

function buildTooltip(gterm) {
  if (tipCache.has(gterm)) return tipCache.get(gterm);
  const [slug, rowStr, colsStr] = gterm.split(':');
  const row = Number.parseInt(rowStr, 10);
  const cols = Number.parseInt(colsStr, 10);
  if (!slug || Number.isNaN(row) || Number.isNaN(cols) || cols < 2) return null;

  const tableEl = document.getElementById(slug);
  const tbl = tableEl && tableEl.tagName === 'TABLE' ? tableEl : tableEl?.closest('table');
  if (!tbl) return null;

  let html = '';

  // Header: thead th cells (skipping the term column)
  const headers = [...tbl.querySelectorAll('thead th')].slice(1);
  const termCell = document.getElementById(`gd-${slug}-${row}-1`);
  if (termCell) {
    html += `<div class="g-tooltip-header">${termCell.innerHTML}</div>`;
  }
  for (let c = 2; c <= cols; c += 1) {
    const cell = document.getElementById(`gd-${slug}-${row}-${c}`);
    if (!cell) continue;
    const label = headers[c - 2]?.innerHTML || `Col ${c}`;
    html += `<div class="g-tooltip-row"><div class="g-tooltip-label">${label}</div><div class="g-tooltip-val">${cell.innerHTML}</div></div>`;
  }
  if (html === '') return null;
  tipCache.set(gterm, html);
  return html;
}

function position(tip, term) {
  const r = term.getBoundingClientRect();
  tip.style.visibility = 'hidden';
  tip.classList.add('visible');
  const tw = tip.offsetWidth;
  const th = tip.offsetHeight;
  const margin = 10;

  let left = r.left + r.width / 2 - tw / 2;
  left = Math.max(margin, Math.min(left, window.innerWidth - tw - margin));

  let top = r.top - th - 8;
  if (top < margin) {
    top = r.bottom + 8;
    if (top + th > window.innerHeight - margin) {
      top = Math.max(margin, window.innerHeight - th - margin);
    }
  }
  tip.style.left = `${left + window.scrollX}px`;
  tip.style.top = `${top + window.scrollY}px`;
  tip.style.visibility = '';
}

function show(term) {
  const gterm = term.getAttribute('data-gterm');
  if (!gterm) return;
  const html = buildTooltip(gterm);
  if (!html) return;
  clearTimeout(hideTimer);
  clearTimeout(showTimer);
  const tip = ensureTip();
  tip.innerHTML = html;
  activeTerm = term;
  position(tip, term);
  requestAnimationFrame(() => tip.classList.add('visible'));
}

function hide(grace = false) {
  clearTimeout(showTimer);
  if (grace) {
    graceUntil = Date.now() + 2000;
  }
  hideTimer = setTimeout(() => {
    if (activeTip) {
      activeTip.classList.remove('visible');
      setTimeout(() => { if (activeTip && !activeTip.classList.contains('visible')) activeTip.innerHTML = ''; }, 200);
    }
    activeTerm = null;
  }, grace ? 0 : 140);
}

function schedule(term) {
  if (Date.now() < graceUntil) { show(term); return; }
  clearTimeout(showTimer);
  showTimer = setTimeout(() => show(term), 150);
}

export function initGlossary(root = document) {
  if (!root.querySelector('.glossary-term')) return;

  root.addEventListener('mouseover', (event) => {
    const term = event.target.closest?.('.glossary-term');
    if (term && term !== activeTerm) schedule(term);
  });
  root.addEventListener('mouseout', (event) => {
    const term = event.target.closest?.('.glossary-term');
    if (term && !term.contains(event.relatedTarget)) hide(true);
  });
  root.addEventListener('focusin', (event) => {
    const term = event.target.closest?.('.glossary-term');
    if (term) show(term);
  });
  root.addEventListener('focusout', () => hide(true));

  // Touch: tap toggles, tap elsewhere hides
  root.addEventListener('touchstart', (event) => {
    const term = event.target.closest?.('.glossary-term');
    if (term) {
      if (activeTerm === term && activeTip?.classList.contains('visible')) hide();
      else show(term);
      event.preventDefault();
    } else if (activeTerm) {
      hide();
    }
  }, { passive: false });

  window.addEventListener('scroll', () => { if (activeTerm) hide(); }, { passive: true });
  window.addEventListener('resize', () => { if (activeTerm) hide(); });

  // Make terms keyboard-reachable once
  root.querySelectorAll('.glossary-term').forEach((t) => { if (!t.hasAttribute('tabindex')) t.setAttribute('tabindex', '0'); });
}
