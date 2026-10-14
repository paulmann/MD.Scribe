/**
 * MD.Viewer 3.0 — KaTeX loader.
 *
 * Local KaTeX (assets/vendor/katex) with mhchem. Loaded only when the page
 * carries [data-mdx-math] elements. The TeX source travels as escaped text
 * content — no inline scripts, CSP-safe.
 */

let katexPromise = null;

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

function injectStyle(href) {
  if (document.querySelector(`link[href="${href}"]`)) return;
  const el = document.createElement('link');
  el.rel = 'stylesheet';
  el.href = href;
  document.head.appendChild(el);
}

function vendorUrl(path) {
  const cfg = window.MDV_CONFIG || {};
  const base = (cfg.assetBase || '').replace(/\/$/, '');
  return `${base}/assets/vendor/${path}`;
}

function loadKatex() {
  if (!katexPromise) {
    injectStyle(vendorUrl('katex/katex.min.css'));
    katexPromise = injectScript(vendorUrl('katex/katex.min.js'))
      .then(() => injectScript(vendorUrl('katex/contrib/mhchem.min.js')))
      .then(() => window.katex)
      .catch((err) => { katexPromise = null; throw err; });
  }
  return katexPromise;
}

export async function initMath(root = document) {
  const nodes = [...root.querySelectorAll('[data-mdx-math]')];
  if (nodes.length === 0) return;

  let katex;
  try {
    katex = await loadKatex();
  } catch {
    nodes.forEach((node) => {
      node.classList.add('mdx-math-error');
      node.title = 'KaTeX could not be loaded — raw TeX is displayed.';
    });
    return;
  }

  for (const node of nodes) {
    if (node.dataset.mathRendered === '1') continue;
    const tex = node.textContent || '';
    const display = node.getAttribute('data-mdx-math') === 'block';
    try {
      katex.render(tex, node, {
        displayMode: display,
        throwOnError: false,
        errorColor: 'var(--destructive, #d33)',
        output: 'html',
        trust: false,
        strict: 'ignore',
      });
      node.dataset.mathRendered = '1';
    } catch (err) {
      node.classList.add('mdx-math-error');
      node.title = `TeX error: ${err?.message || 'unknown'}`;
    }
  }
}
