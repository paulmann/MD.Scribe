/**
 * MD.Scribe — rendering gate (browser).
 *
 *   node check-render.mjs [--base=http://127.0.0.1:8099] [--chrome=/path/to/chrome]
 *
 * Why this file exists: two defects reached main while every unit test was
 * green — a server-side Mermaid repair that never reached the renderer (the
 * badge announced it, the browser parsed the unrepaired source) and a vendor
 * rebuild that dropped the icon fallback (empty <svg>, no error anywhere).
 * Both are invisible to PHPUnit: they live in the browser half of the
 * pipeline. This gate loads the pages a reader loads and asserts what the
 * reader gets.
 *
 * It runs on a system Chrome through puppeteer-core on purpose: the checks are
 * structural (rendered diagrams, KaTeX errors, empty icons, CSP violations),
 * not pixel comparisons, so downloading a pinned browser buys nothing.
 *
 * Exit code 0 = every check passed. Any failure prints FAIL and exits 1.
 */

import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';

const here = dirname(fileURLToPath(import.meta.url));

const arg = (name, fallback = '') => {
  const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
};

// --root lets the gate face a tree other than this checkout: tools/verify-release.php
// points it at the extracted release archive, so the fixture lands there and the
// bundled-mermaid check reads that tree's own package.json.
const root = resolve(arg('root', resolve(here, '../..')));

const BASE = arg('base', 'http://127.0.0.1:8099').replace(/\/$/, '');
const isLocal = /^(127\.0\.0\.1|localhost|\[::1\])/i.test(new URL(BASE).hostname);

const CHROME_CANDIDATES = [
  arg('chrome'),
  process.env.CHROME_PATH ?? '',
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  '/usr/bin/google-chrome',
  '/usr/bin/google-chrome-stable',
  '/usr/bin/chromium',
  '/usr/bin/chromium-browser',
  '/snap/bin/chromium',
];

function findChrome() {
  for (const candidate of CHROME_CANDIDATES) {
    if (candidate && existsSync(candidate)) return candidate;
  }
  return null;
}

const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok, detail });
  const line = ok ? `PASS  ${name}` : `FAIL  ${name}${detail ? ' — ' + detail : ''}`;
  process.stdout.write(line + '\n');
}

/** Wait until every .md-mermaid block has rendered or failed. */
async function settled(page) {
  await page.waitForFunction(() => {
    const blocks = document.querySelectorAll('.md-mermaid');
    if (blocks.length === 0) return false;
    const done = document.querySelectorAll('.md-mermaid[data-rendered="1"], .md-mermaid[data-render-error]');
    return done.length >= blocks.length;
  }, { timeout: 45000 });
}

async function openPage(browser, path) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 1000 });
  page.__consoleErrors = [];
  page.__pageErrors = [];
  page.on('console', (msg) => {
    const text = msg.text();
    // The app logs a line about a Mermaid parse error on purpose; that is the
    // documented behaviour for a broken diagram, not a gate failure.
    if (msg.type() === 'error' && !/Failed to load resource/.test(text)) page.__consoleErrors.push(text);
  });
  page.on('pageerror', (err) => page.__pageErrors.push(String(err)));
  await page.evaluateOnNewDocument(() => {
    window.__cspViolations = [];
    document.addEventListener('securitypolicyviolation', (e) => {
      window.__cspViolations.push(`${e.violatedDirective} ${e.blockedURI}`);
    });
  });
  await page.goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded', timeout: 45000 });
  return page;
}

/** Diagrams, KaTeX and icons on a reader page. */
async function checkReaderPage(browser, path, expectedDiagrams) {
  const page = await openPage(browser, path);
  try {
    await settled(page);
    const state = await page.evaluate(() => {
      const blocks = [...document.querySelectorAll('.md-mermaid')];
      const visibleSvg = [...document.querySelectorAll('svg')].filter((s) => s.getClientRects().length > 0);
      return {
        diagrams: blocks.length,
        rendered: blocks.filter((b) => b.getAttribute('data-rendered') === '1').length,
        failed: blocks.filter((b) => b.getAttribute('data-render-error')).length,
        emptySvg: visibleSvg.filter((s) => s.children.length === 0).length,
        katex: document.querySelectorAll('.katex').length,
        katexErrors: document.querySelectorAll('.katex-error').length,
        katexDisplayFits: [...document.querySelectorAll('.katex-display')].every((k) => k.scrollWidth - k.clientWidth <= 1),
        mermaidVersion: window.mermaid ? window.mermaid.version : null,
        mermaidSrc: [...document.querySelectorAll('script[src*="mermaid"]')].map((s) => s.src)[0] ?? null,
        csp: window.__cspViolations ?? [],
        table: (() => {
          const t = document.querySelector('.md-body table');
          if (!t) return null;
          const wrap = t.closest('.md-table-wrap') ?? t.parentElement;
          const th = t.querySelector('thead th');
          return {
            tableW: Math.round(t.getBoundingClientRect().width),
            wrapW: Math.round(wrap.getBoundingClientRect().width),
            overflow: wrap.scrollWidth - wrap.clientWidth,
            clipped: [...t.querySelectorAll('td')].filter((c) => c.scrollWidth - c.clientWidth > 1).length,
            thWrap: th ? getComputedStyle(th).whiteSpace : null,
          };
        })(),
      };
    });

    check(`${path}: ${expectedDiagrams} diagram(s) rendered`, state.diagrams === expectedDiagrams && state.rendered === expectedDiagrams,
      `found ${state.diagrams}, rendered ${state.rendered}`);
    check(`${path}: no diagram failed to parse`, state.failed === 0, `${state.failed} failed`);
    check(`${path}: no empty icon`, state.emptySvg === 0, `${state.emptySvg} empty <svg>`);
    check(`${path}: KaTeX rendered`, state.katex > 0 && state.katexErrors === 0, `${state.katex} nodes, ${state.katexErrors} errors`);
    check(`${path}: display math fits the column`, state.katexDisplayFits);
    check(`${path}: no CSP violation`, state.csp.length === 0, state.csp.join('; '));
    check(`${path}: no console/page error`, page.__consoleErrors.length === 0 && page.__pageErrors.length === 0,
      [...page.__consoleErrors, ...page.__pageErrors].slice(0, 3).join(' | '));
    if (state.table) {
      check(`${path}: table fits and wraps`, state.table.tableW <= state.table.wrapW + 1 && state.table.clipped === 0 && state.table.thWrap === 'normal',
        `${state.table.tableW}px in ${state.table.wrapW}px, ${state.table.clipped} clipped, th ${state.table.thWrap}`);
    }
    return { page, state };
  } finally {
    if (path) await page.close().catch(() => {});
  }
}

/** The repair must reach the renderer, and a broken diagram must stay visible. */
async function checkRepairFixture(browser, fixturePath) {
  const page = await openPage(browser, `/?file=${encodeURIComponent(fixturePath)}`);
  try {
    await settled(page);
    const state = await page.evaluate(() => {
      const blocks = [...document.querySelectorAll('.md-mermaid')];
      const pre = (b) => b.querySelector('pre.mermaid');
      return {
        total: blocks.length,
        first: {
          fixes: Number(pre(blocks[0])?.getAttribute('data-mermaid-fixes') ?? 0),
          rendered: blocks[0].getAttribute('data-rendered'),
          source: pre(blocks[0])?.getAttribute('data-mermaid-source') ?? '',
          hasSvg: !!blocks[0].querySelector('svg'),
        },
        second: {
          error: blocks[1]?.getAttribute('data-render-error'),
          text: (blocks[1]?.textContent ?? '').trim(),
        },
      };
    });
    check('repair: the repaired source reaches the renderer',
      state.first.fixes > 0 && state.first.rendered === '1' && state.first.hasSvg,
      `fixes=${state.first.fixes} rendered=${state.first.rendered}`);
    check('repair: the author text is still the copy source',
      state.first.source.includes('Note (draft)'));
    check('broken diagram: the failure is reported, not hidden',
      state.second.error === 'parse' && state.second.text.length > 0,
      `error=${state.second.error} text=${state.second.text.slice(0, 40)}`);
  } finally {
    await page.close().catch(() => {});
  }
}

/** Light/dark switch must re-render the diagrams, not leave the light ones. */
async function checkThemeSwitch(browser) {
  const page = await openPage(browser, '/?file=index.md');
  try {
    await settled(page);
    const before = await page.evaluate(() => {
      const svg = document.querySelector('.md-mermaid svg');
      const rect = svg?.querySelector('g.node rect, g.node path');
      return { rendered: document.querySelectorAll('.md-mermaid[data-rendered="1"]').length, fill: rect ? getComputedStyle(rect).fill : null };
    });
    await page.evaluate(() => {
      const el = document.documentElement;
      el.classList.toggle('dark');
    });
    await page.waitForFunction((count) =>
      document.querySelectorAll('.md-mermaid[data-rendered="1"]').length >= count, { timeout: 45000 }, before.rendered);
    await new Promise((r) => setTimeout(r, 600));
    const after = await page.evaluate(() => {
      const svg = document.querySelector('.md-mermaid svg');
      const rect = svg?.querySelector('g.node rect, g.node path');
      return { rendered: document.querySelectorAll('.md-mermaid[data-rendered="1"]').length, fill: rect ? getComputedStyle(rect).fill : null };
    });
    check('theme switch re-renders the diagrams with the other theme',
      after.rendered === before.rendered && before.fill !== after.fill,
      `${before.fill} -> ${after.fill}`);
  } finally {
    await page.close().catch(() => {});
  }
}

const FIXTURE = 'zz-render-check.md';
const FIXTURE_BODY = `---
title: Render gate fixture
toc: false
numbering: false
---

# Render gate fixture

\`\`\`mermaid
flowchart LR
    X[Note (draft)] --> Y[Ship]
\`\`\`

\`\`\`mermaid
flowchart TD
    A[unclosed label --> B
\`\`\`
`;

const chrome = findChrome();
if (!chrome) {
  process.stdout.write('FAIL  no Chrome/Chromium found — set CHROME_PATH or pass --chrome=\n');
  process.exit(1);
}
process.stdout.write(`browser: ${chrome}\nbranch:  ${root}\nbase:    ${BASE}\n`);

const fixtureDir = join(root, 'content');
const fixtureFile = join(fixtureDir, FIXTURE);
let wroteFixture = false;

const browser = await puppeteer.launch({
  executablePath: chrome,
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--hide-scrollbars'],
});

try {
  const index = await checkReaderPage(browser, '/?file=index.md', 2);
  // Mermaid 12's UMD build carries no `.version` on the global, and the bundle
  // contains several version literals, so the shipped artefact is identified by
  // the major that tools/frontend/package.json asks for.
  const pkg = JSON.parse(readFileSync(join(root, 'tools/frontend/package.json'), 'utf8'));
  const wantedMajor = String(pkg.dependencies?.mermaid ?? '').replace(/^[^\d]*/, '').split('.')[0];
  const shipped = index.state.mermaidSrc ? await fetch(index.state.mermaidSrc).then((r) => r.text()) : '';
  const versions = [...shipped.matchAll(/version"?\s*:\s*"(\d+\.\d+\.\d+)"/g)].map((m) => m[1]);
  const match = versions.find((v) => v.startsWith(`${wantedMajor}.`)) ?? null;
  check(`mermaid bundle matches tools/frontend (major ${wantedMajor})`, match !== null,
    match ? `bundle carries ${match}` : `bundle versions: ${versions.slice(0, 4).join(', ') || 'none'}`);

  await checkReaderPage(browser, '/?file=docs/markdown-reference.md', 1);
  await checkThemeSwitch(browser);

  if (isLocal) {
    mkdirSync(fixtureDir, { recursive: true });
    writeFileSync(fixtureFile, FIXTURE_BODY, 'utf8');
    wroteFixture = true;
    await checkRepairFixture(browser, FIXTURE);
  } else {
    process.stdout.write(`SKIP  repair fixture — ${BASE} is not a local server, refusing to write into its content root\n`);
  }
} finally {
  if (wroteFixture) rmSync(fixtureFile, { force: true });
  await browser.close().catch(() => {});
}

const failed = results.filter((r) => !r.ok);
process.stdout.write(`\nchecks: ${results.length}, failed: ${failed.length}\n`);
process.exit(failed.length === 0 ? 0 : 1);
