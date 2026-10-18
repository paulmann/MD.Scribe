<!--
 * MD.Scribe — Documentation
 * Version: 3.1.0
-->

# MD.Scribe 3

> A secure, self-hosted **PHP 8.3 Markdown documentation platform**: a CommonMark/GFM-complete viewer with full-text search, a command palette, a theme catalog, Mermaid diagrams, KaTeX math, glossary tooltips — plus a real **admin panel** with a content manager, editor, backups, self-updater, health checks and an audit trail. **Zero CDN dependencies. No database. No framework. No build step on the server.**

[![PHP](https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Tests](https://img.shields.io/badge/tests-126%20passed-brightgreen)](tests)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen)](phpstan.neon)
[![CDN](https://img.shields.io/badge/CDN%20requests-0-success)](#zero-cdn-policy)

---

## What is MD.Scribe?

Point it at a folder of `.md` files and you have a documentation site:

- **`public/index.php`** renders a document (`?file=path/doc.md`), a searchable
  **library** when no file is requested, plus **Themes**, **Settings** and
  **Search** pages, `sitemap.xml` and an RSS feed.
- **`public/admin.php`** is the admin panel: dashboard, file manager, split-pane
  editor with live preview, uploads, search-index control, backups & trash,
  self-updater, settings editor, logs, audit trail, security/health checks and
  tools (broken-link checker, cache control).
- **`md.php` / `updater.php`** at the project root are compatibility shims —
  every v2 URL contract (`?file=`, `&download=1`, `?update=true`,
  `?restore=latest`, the machine API) keeps working after an upgrade.

Everything is styled by a **shadcn/ui-token design system on a local Tailwind
CSS v4 build** with light/dark/system modes, 12 curated themes and a
neutral × accent mixer. Mermaid, KaTeX, Inter, JetBrains Mono and all icons
are vendored — the app works fully **offline / air-gapped**.

---

## Quick start

```bash
git clone https://github.com/paulmann/MD.Scribe.git /var/www/docs
cd /var/www/docs

# PHP dependencies are vendored in release archives; from a bare clone:
composer install --no-dev --optimize-autoloader

# put your documents in content/ (or set BROWSE_DIR in .md.ini)
cp ~/my-notes/*.md content/

# develop:
php -S localhost:8080 -t public public/router.php
# → http://localhost:8080        (viewer)
# → http://localhost:8080/admin.php   (admin — first-run setup)
```

Production deployments (docroot at `public/`, docroot at project root, Apache,
nginx, Caddy, Docker, shared hosting): **[docs/installation.md](docs/installation.md)**.

Requirements: **PHP ≥ 8.3** with `mbstring`, `dom`, `libxml`, `json`
(`curl` + `phar` + `zip` recommended for the updater and backup archives).

---

## Feature tour

### Reading experience

| Area | Features |
| --- | --- |
| Typography | Fluid type scale, text size / page width / line-height controls, serif & system font options, print stylesheet |
| Navigation | Auto TOC with scrollspy, heading numbers + hover anchors, `[TOC]` placeholder, breadcrumbs, prev/next pager, folder sidebar, skip links |
| Discovery | Server-side **full-text search** (inverted index, CJK-aware), **Ctrl+K command palette** (documents, headings, actions), sitemap.xml, RSS feed |
| Rich content | Server-side **syntax highlighting** (~190 languages), **Mermaid** diagrams with auto-repair + diagnostics, **KaTeX** math (incl. mhchem), GitHub-style **alerts**, task lists, footnotes, definition lists, `==marks==`, ^super^/~sub~script, 1,900+ emoji shortcodes, image **lightbox** with captions, reading-time estimates |
| Glossary | Any two-column table becomes hover/tap **tooltips** with alias expansion (`Term (ABBR)`), live DOM content — zero duplication |
| Themes | 12 presets + neutral × accent × radius × font mixer, light/dark/**system auto-detect**, per-visitor persistence, admin-managed **custom themes**, FOUC-free pre-paint bootstrap |

### Markdown support

CommonMark **0.31-complete** via `league/commonmark`, plus GFM (tables with
alignment, strikethrough, task lists, autolinks), footnotes (reference **and**
inline), definition lists, attribute syntax `{#id .class}`, smart punctuation
(opt-in), YAML frontmatter (`title`, `description`, `tags`, `author`, `date`,
`draft`, `toc`, `numbering`), relative `.md` link rewriting with dead-link
markers, and legacy MD.Viewer extras (universal pattern chains, glossary
tables, `md.md` naming rule). Full matrix:
**[docs/markdown-features.md](docs/markdown-features.md)**.

### Admin panel

| Page | What it does |
| --- | --- |
| **Dashboard** | Corpus stats, index freshness, health summary, quick actions, recent docs, activity feed |
| **Files** | Tree of all content, filter, create/rename/move/delete (soft-delete to **trash**), folders, multi-upload (.md + images with content sniffing) |
| **Editor** | Split-pane live preview (the *real* engine), ⌘S save with **sha256 conflict detection**, word/reading-time counter, image upload at cursor, deep links `?page=editor&file=…` |
| **Search index** | Stats, freshness, on-demand rebuild |
| **Backups & trash** | One-click content snapshots (+ `.md.ini`), restore with automatic pre-restore safety copy, zip download, prune-ready listing, deleted-file restore |
| **Updates** | GitHub release check (15-min cache), release notes, **verified apply** (tarball + per-file sha256 manifest, backup-first), machine-API docs |
| **Settings** | Every `.md.ini` key as a typed form (schema-driven), API-key regeneration, admin password change |
| **Custom themes** | Save brand accents (light+dark primaries) — instantly selectable in the public catalog |
| **Logs & audit** | Daily app logs + JSON audit trail (who did what, when, from where) |
| **Security & health** | 20+ checks: PHP version/extensions, writability, permissions, secrets posture, OPcache, CSP; hardening checklist |
| **Tools** | **Broken-link / dead-anchor / missing-media / orphan-document checker**, cache clearing, feed & sitemap shortcuts |

### Zero-CDN policy

Every asset ships locally: Tailwind output (built at development time,
committed), vendored `mermaid.min.js`, KaTeX (CSS+fonts+mhchem), Inter &
JetBrains Mono woff2 subsets (latin/latin-ext/cyrillic), lucide icons compiled
into a PHP sprite map, and the full gemoji database. The Content-Security-Policy
enforces it: `default-src 'self'`.

---

## Configuration — `.md.ini`

Created automatically next to the project root on first load. Installation-level
keys are appended with safe defaults on every load — upgrades never need manual
edits. Rendering-feature keys are deliberately **not** written: their defaults
live in `config/defaults.php`, and visitors may toggle them per-browser
(first-party cookies). Adding a feature key to `.md.ini` **hard-locks** it.

```ini
; General
SITE_NAME = MD.Scribe
HOME_DOC  = index.md
BROWSE_DIR =                     ; empty = bundled content/

; Security & admin
ADMIN_ENABLED = true
ADMIN_USERNAME = admin
ADMIN_PASSWORD =                 ; one-shot: hashed on next load, then cleared
ADMIN_PASSWORD_HASH =            ; managed by the panel — don't edit
ADMIN_RATE_LIMIT = 10            ; failed logins / 15 min / IP
API_KEY = mdv_…                  ; machine API credential (auto-generated)
ALLOW_UPDATE = false
ALLOW_RESTORE = false
ALLOW_RAW_HTML = false           ; escape author HTML (recommended)
ALLOW_IFRAMES = false
ALLOW_EXTERNAL_IMAGES = true
SECURITY_HEADERS = true
SECURITY_CSP = true

; Content
DISABLE_UPLOAD = true
DISABLE_CLIPBOARD = false
DISABLE_SAVE_CLIPBOARD_TO_FILE = true
MAX_UPLOAD_MB = 8

; Performance / feeds / appearance / diagnostics — see docs/configuration.md
```

Full key reference (every key, type, default, effect):
**[docs/configuration.md](docs/configuration.md)**.
Per-directory `.md.ini` layering (the v2 `DISABLE_*` mechanism) is preserved:
**[docs/configuration.md#per-directory](docs/configuration.md#per-directory-mdini)**.

---

## URL contract

| URL | Purpose |
| --- | --- |
| `index.php?file=docs/api.md` | render a document |
| `index.php?file=docs/api.md&download=1` | source download (octet-stream + nosniff) |
| `index.php?media=docs/img/pic.png` | content asset (images inline; SVG/zip as attachments) |
| `index.php?page=browser[&dir=docs]` | library / file browser |
| `index.php?page=search&q=…` | full-text search |
| `index.php?page=themes` / `page=settings` | theme catalog / settings |
| `index.php?page=sitemap` / `page=feed` | sitemap.xml / RSS |
| `index.php?api=search\|suggest\|toc\|config` | JSON GET endpoints |
| `index.php?api=render\|save\|upload` | JSON POST endpoints (CSRF) |
| `admin.php?page=…` / `admin.php?action=…` | admin UI / admin JSON API |
| `md.php?file=…` | **v2-compatible** viewer shim |
| `updater.php?update=true` · `?restore=latest` · `?api_key=…&action=…&format=json` | **v2-compatible** updater + machine API |

HTTP caching: documents and media answer with `ETag`/`Last-Modified` and
support `304 Not Modified`; rendered HTML is additionally cached server-side
keyed by source hash × feature fingerprint.

---

## Security

Defense in depth — details and the hardening checklist:
**[docs/security.md](docs/security.md)**.

- **Path validation**: 14 layers (length, NUL, control chars, URL-decode
  re-check, absolute paths, traversal, strict Unicode whitelist `\p{L}\p{M}\p{Pd}`,
  depth, extension, realpath containment, symlink-safe re-check) — one
  validator for viewer, downloads, media, uploads, per-directory ini and admin.
- **Output sanitization**: every rendered document passes a DOM-allowlist
  sanitizer (event handlers, dangerous schemes, forms, foreignObject/`<use>`
  all die); author raw HTML is escaped unless `ALLOW_RAW_HTML` is on — and
  sanitized even then.
- **Transport**: strict CSP with per-request nonces, nosniff, frame/r
  eferrer/permissions policies, COOP; hardened session cookies.
- **Admin**: password_hash login, per-IP rate limiting, CSRF on every
  mutation, session regeneration, audit trail, soft deletes, first-run setup
  gated by localhost-or-one-time-code.
- **Secrets**: `.md.ini` is never served (`.htaccess` + outside-docroot
  layouts); machine API answers JSON only.

---

## Development

```bash
composer install                 # dev deps: phpunit, phpstan
cd tools/frontend && npm install && node build.mjs && cd ../..

composer test                    # 126 PHPUnit tests
composer stan                    # PHPStan level 6
php -S localhost:8080 -t public public/router.php
```

The frontend pipeline (Tailwind v4 → `app.css`, esbuild → `app.js`/`admin.js`,
theme compiler → `themes.css`, icon/emoji generators) runs at **development**
time only — built assets are committed, so production servers need no Node.js.
Architecture, module map, release checklist: **[docs/development.md](docs/development.md)**.

### Project layout

```text
MD.Scribe/
├── public/              ← recommended docroot
│   ├── index.php        front controller (viewer, library, pages, JSON API)
│   ├── admin.php        admin panel + admin JSON API
│   ├── router.php       php -S dev router
│   └── assets/          css/ js/ vendor/{mermaid,katex,fonts} img/  (all local)
├── src/                 PSR-4 app code (MdViewer\)
│   ├── Core/            App, Config, FeatureFlags, Request/Response, Session,
│   │                    Csrf, Logger, AuditLog, HttpClient, Paths, Version
│   ├── Http/            FrontController, AdminKernel, Controllers/
│   ├── Markdown/        engine, sanitizer, glossary, mermaid repair, cache,
│   │                    Extensions/ (math, sub/sup, emoji, alerts, renderers)
│   ├── Files/           PathValidator, repository, search index, uploader,
│   │                    FileManager
│   ├── Admin/           auth, backups, updater, health, link checker
│   ├── Theme/           theme catalog (shadcn token model)
│   └── View/            template engine, generated icon sprite
├── templates/           layout/ viewer/ browser/ pages/ admin/
├── config/              defaults.php (schema) · themes.php · manifest.json
├── content/             ← your documents live here (default BROWSE_DIR)
├── storage/             cache/ backups/ logs/ index/ state/ themes/  (writable)
├── docs/                deep documentation
├── tests/               PHPUnit (Unit + Feature)
├── tools/               build pipeline (frontend/, build-manifest.php)
├── deploy/              nginx, Caddy, Docker, php.ini
├── md.php               v2-compatible viewer shim
├── updater.php          v2-compatible updater + machine API shim
└── index.php            project-root entry (docroot-at-root installs)
```

---

## Documentation

| Document | Contents |
| --- | --- |
| [docs/installation.md](docs/installation.md) | All deployment layouts, Apache/nginx/Caddy/Docker, shared hosting, permissions |
| [docs/configuration.md](docs/configuration.md) | Complete `.md.ini` reference, per-directory files, feature toggles |
| [docs/markdown-features.md](docs/markdown-features.md) | Full syntax matrix with examples |
| [docs/admin-guide.md](docs/admin-guide.md) | Every admin page, workflows, machine API |
| [docs/theming.md](docs/theming.md) | Theme system, tokens, custom themes, recipes |
| [docs/security.md](docs/security.md) | Threat model, hardening checklist, CSP notes |
| [docs/http-api.md](docs/http-api.md) | JSON endpoints (public + admin + machine API) |
| [docs/development.md](docs/development.md) | Architecture, build pipeline, testing, releases |
| [docs/troubleshooting.md](docs/troubleshooting.md) | Common problems and fixes |
| [docs/upgrade-from-v2.md](docs/upgrade-from-v2.md) | Migration mapping and steps |

---

## Upgrading from v2

Drop-in: deploy v3 beside your v2 files, move (or point `BROWSE_DIR` at) your
documents, keep the root `md.php` shim — every old bookmark and `?file=` link
works. Step-by-step: **[docs/upgrade-from-v2.md](docs/upgrade-from-v2.md)**.

## License

PolyForm Noncommercial 1.0.0 — see [LICENSE](LICENSE).
Markdown engine: [league/commonmark](https://commonmark.thephpleague.com/) (BSD-3).
Highlighting: [scrivo/highlight.php](https://github.com/scrivo/highlight.php) (BSD-3, highlight.js port).
Diagrams: [Mermaid](https://mermaid.js.org/) (MIT). Math: [KaTeX](https://katex.org/) (MIT).
Icons: [Lucide](https://lucide.dev/) (ISC). Emoji data: [gemoji](https://github.com/github/gemoji) (MIT).
Fonts: Inter & JetBrains Mono (SIL OFL).
