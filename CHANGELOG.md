# Changelog

All notable changes to MD.Viewer. Format inspired by Keep a Changelog;
versions follow semver.

## 3.0.0 — “Aurora” (2026-10-10)

A complete professional refactor of MD.Viewer v2 (the 4,700-line `md.php`
monolith) into a structured PHP 8.3 application — plus a full admin panel,
a 2026 design system and zero CDN dependencies.

### Architecture

- **REWRITE**: monolith → PSR-4 application (`src/Core`, `Http`, `Markdown`,
  `Files`, `Admin`, `Theme`, `View`) with strict types everywhere.
- **NEW**: Markdown engine on `league/commonmark` (CommonMark 0.31 + GFM)
  with MD.Viewer extensions; server-side syntax highlighting via
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
