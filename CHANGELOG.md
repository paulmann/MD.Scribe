# Changelog

All notable changes to MD.Scribe. Format inspired by Keep a Changelog;
versions follow semver.

## 3.2.0 — “Borealis” (2026-10-22)

Embedding release: the rendering pipeline is now usable outside the viewer.

### Added

- **`MdViewer\Standalone\Renderer`** — framework-free renderer for any PHP
  caller. No front controller, no session, no output buffering:
  `(new Renderer())->renderFile($path)->html`. Defaults to **embed mode**:
  portable code blocks without toolbars, no automatic heading numbering, no
  auto-TOC — the markup a GitHub-README stylesheet expects.
- **`bin/md-scribe-render` CLI** — renders one file (or stdin) to an HTML
  fragment, a complete page (`--html`, `--title`, `--css`, `--lang`) or JSON
  (`--json`); `--base-url` rewrites relative links/media; `--viewer-mode`
  keeps the viewer chrome. Documented in
  [docs/standalone-renderer.md](docs/standalone-renderer.md).
- `MarkdownEngine::render()` option **`plainCodeBlocks`** and the matching
  `RenderContext::$plainCodeBlocks` flag: fenced code blocks can be emitted as
  plain `<pre><code class="hljs language-…">` for external stylesheets.
- PHPUnit coverage for the standalone renderer (embed markup, base-URL link
  rewriting, viewer-mode regression).

### Changed

- README: new "Embedding and CLI rendering" section.
- `AGENTS.md` and `docs/` reference version 3.2.0.

## 3.1.0 — “Aurora” (2026-10-14)

A rename release. The project is published as **MD.Scribe** — same code, same
contracts, new name and new home. Nothing in the application behaviour changes.

### Changed

- **Product name: MD.Viewer → MD.Scribe**, repository
  `paulmann/MD.Scribe`. The old name described only the reading half of what v3
  became: v3 is an editor, an admin panel, a publisher and a search index as
  well as a viewer. "Scribe" covers the whole.
- `Version::REPO`, `Version::RAW_BASE` and `Version::API_BASE` now point at
  `paulmann/MD.Scribe`; `Version::userAgent()` reports `MD.Scribe/<version>`.
- User-facing strings follow: window titles, the admin panel header and login
  page, the `<meta name="generator">` tag, the footer, the `X-Powered-By`
  header, the default `SITE_NAME`, the `console.info` banner and the messages
  the bootstrap prints on a fatal misconfiguration.
- Documentation, `composer.json` (now `mdscribe/md-scribe`), `LICENSE`,
  deployment samples (`deploy/Dockerfile`, `docker-compose.yml`, `nginx.conf`,
  `Caddyfile`, `php.ini`) and the `content/` pages.
- `composer.lock` `content-hash` recomputed for the renamed package; verified
  with `composer validate --check-lock` → *"composer.json is valid"*.
- `config/manifest.json` rebuilt (921 shipped files).

### Fixed

- **The self-updater no longer pins a product name when unpacking a release
  archive.** It located the extracted root with `glob('.../MD.Viewer-*')`, so a
  renamed repository — which GitHub names after the repository, e.g.
  `MD.Scribe-main/` — would have been rejected with *"does not look like a
  release"*. It now accepts any `<name>-<ref>` directory that contains
  `config/manifest.json`, with a fallback for a flat archive.

### Notes

- The PHP namespace `MdViewer\` is unchanged. It is an internal identifier
  namespace, shared by every class in `src/`, and renaming it would touch every
  file for no user-visible gain. Third-party code that type-hints these classes
  keeps working.
- Historical references to **v2** still read `MD.Viewer v2 …` where they
  describe the previous product (upgrade guide, changelog, compatibility
  notes). That is deliberate: v2 *was* MD.Viewer.
- Upgrading an existing v3.0.x installation in place works: the tracked file
  list and the machine-API envelope are unchanged, and `.md.ini` keys are the
  same (`SITE_NAME` keeps whatever value the installation already has).

## 3.0.1 — “Aurora” (2026-10-14)

Bug-fix release. Two defects of the same class — a path comparison that assumed
`/` as the directory separator — both found by serving the application rather
than by reading it.

### Fixed

- **`public/router.php` — `/admin.php` was served by the viewer.** The router
  compared `realpath()` results against strings joined with `/`
  (`$real === $publicDir . '/admin.php'`). Where the separator is `\`, that
  comparison can never hold, so every `/admin.php` request fell through to
  `index.php`: the admin panel was unreachable under the built-in dev server,
  and the request still looked healthy — HTTP 200 with the viewer page. The two
  front controllers are now addressed by `realpath()` on both sides, with
  `DIRECTORY_SEPARATOR` used for the prefixes.
- **`tools/build-manifest.php` — exclusions silently did not apply** when the
  manifest was built on Windows. Directory and file prefixes are written with
  `/`, the iterator yields native paths, and the two were compared directly, so
  `content/`, `storage/` and `tests/` were listed as shipped files: a locally
  built manifest disagreed with the archive the updater verifies against
  (940 entries against the correct 921). Both sides are normalised before
  comparing, and `config/manifest.json` was rebuilt.

### Notes

- Behaviour on Linux and macOS is unchanged by both fixes — there the separator
  is `/`, so the old comparisons already held. Confirmed by serving the same
  tree on Debian (PHP 8.5) and on Windows.
- `REPAIR_VERSION` in `MermaidNormalizer` stays at `3.0.0`: it versions the
  mermaid repair rules, which this release does not touch.

## 3.0.0 — “Aurora” (2026-10-10)

A complete professional refactor of MD.Viewer v2 (the 4,700-line `md.php`
monolith) into a structured PHP 8.3 application — plus a full admin panel,
a 2026 design system and zero CDN dependencies.

### Architecture

- **REWRITE**: monolith → PSR-4 application (`src/Core`, `Http`, `Markdown`,
  `Files`, `Admin`, `Theme`, `View`) with strict types everywhere.
- **NEW**: Markdown engine on `league/commonmark` (CommonMark 0.31 + GFM)
  with MD.Scribe extensions; server-side syntax highlighting via
  `scrivo/highlight.php` (~190 languages, no client JS).
- **NEW**: DOM-allowlist HTML sanitizer applied to every rendered document.
- **NEW**: render cache (source × feature fingerprint), ETag/Last-Modified
  + 304 responses, server-side full-text search index (CJK-aware),
  sitemap.xml, RSS feed.
- **NEW**: template engine with layouts/sections/partials; generated icon
  sprite (lucide) and full gemoji emoji map.
- **KEEP**: every v2 URL/config contract via shims (`md.php`,
  `updater.php`, machine API envelope, `mdv_feat_*` cookies, per-directory
  `.md.ini` layering, glossary DOM contract, 14-layer path validation with
  the v2.16/v2.18 Unicode rules).

### Design & frontend

- **NEW**: shadcn/ui-token design system on a **local Tailwind CSS v4
  build** — hand-built vanilla-JS components (dialogs, sheets, dropdowns,
  switches, toasts, command palette) with keyboard/a11y support.
- **NEW**: theme system — 12 curated presets, neutral × accent × radius ×
  font mixer, light/dark/**system** with auto-detection, FOUC-free
  pre-paint bootstrap, per-visitor persistence, admin-managed custom
  accents, dedicated Themes catalog page.
- **NEW**: Ctrl+K command palette (documents, headings, actions), reading
  progress bar, scrollspy TOC, image lightbox, prev/next pager,
  breadcrumbs, print stylesheet, copy controls (code/quote/source/mermaid),
  word-wrap toggle, reading-preference steppers.
- **CHANGE**: zero CDN — Mermaid, KaTeX (+mhchem), Inter, JetBrains Mono
  and all icons are vendored; CSP enforces `default-src 'self'`.

### Markdown

- **NEW**: math ($…$ / $$…$$) via local KaTeX; GitHub-style alerts
  (`> [!NOTE]` …); definition lists; `{#id .class}` attributes; smart
  punctuation (opt-in); YAML frontmatter (title/description/tags/author/
  date/**draft**/toc/numbering); `==mark==` (was v2-extra); inline
  footnotes; `[TOC]` placeholder; relative `.md` link rewriting with
  dead-link markers; media route for relative images; 1,913 emoji aliases
  (was 16).
- **KEEP & PORT**: glossary tooltips with alias expansion, universal
  pattern chains, Mermaid auto-repair engine (extended: balance-aware
  quoting of round node labels), heading numbering with manual-detection,
  colon-split hero titles, DOUBLE_LINE_BREAKS soft-break behaviour.
- **FIX (inherited)**: the v2.16 `<path>` infinite-loop input is a
  regression test; fence-length nesting (v2.2.8) is native to CommonMark.

### Admin panel (new)

- Session login (password_hash, per-IP rate limiting, first-run setup gated
  by localhost-or-one-time-code), CSRF on every mutation, full audit trail.
- Dashboard · file manager (tree, CRUD, soft-delete + trash, folders,
  multi-upload) · split-pane editor (live engine preview, sha256 conflict
  detection, image insertion, deep links) · search-index control · content
  backups (create/list/restore/download/delete, pre-restore safety
  snapshots) · updater (GitHub release check, sha256-verified apply,
  app backups/restore) · schema-driven settings editor · custom themes ·
  logs & audit viewer · security/health checks (20+) · tools (broken-link /
  dead-anchor / missing-media / orphan checker, cache control).

### Security

- **NEW**: CSP with per-request nonces; hardened session cookies; COOP /
  referrer / permissions policies; `X-Robots-Tag: noindex` on admin;
  SVG uploads refused; image uploads content-sniffed; admin editor
  optimistic concurrency; secrets never rendered; `.md.ini` write mode 0640.
- **KEEP**: 14-layer path validator (ported verbatim + tested), opaque
  octet-stream downloads, per-directory flag layering, machine-API key
  semantics, three-level feature gating (ini / server / UI).

### Quality & operations

- **NEW**: 126 PHPUnit tests (333 assertions) incl. security regression
  suites; PHPStan level 6 clean; GitHub Actions CI; Docker + nginx + Caddy +
  Apache configs; `php -S` dev router; release manifest builder;
  `.gitignore`; MIT LICENSE retained.
- **DOCS**: README rewritten; `docs/` — installation, configuration
  reference, markdown matrix, admin guide, theming, security model, HTTP &
  machine API, development & release process, troubleshooting, upgrade
  guide.

## 2.x (legacy)

See the v2 repository history — `md.php` up to v2.18.0 and `updater.php`
up to v3.14.0, with the changelog embedded in the file headers.
