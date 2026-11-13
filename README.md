<!--
 * MD.Scribe — Documentation
 * Version: 3.4.4
-->

# MD.Scribe 3

MD.Scribe is a self-hosted Markdown documentation platform in PHP 8.5: a CommonMark/GFM-complete
viewer with full-text search, a command palette, a theme catalog, Mermaid diagrams and KaTeX math,
plus an admin panel with a content manager, editor, backups, a verified self-updater and an audit
trail. It needs no database, no framework and no build step on the server, and every asset ships
locally, so the whole application works offline.

<!-- md-i18n:switcher -->
**Read this in:** English · [Deutsch](README.de.md) · [Español](README.es.md) · [Français](README.fr.md) · [हिन्दी](README.hi.md) · [中文](README.zh.md) · [Русский](README.ru.md)

[![CI](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml/badge.svg)](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/paulmann/MD.Scribe?label=release&sort=semver)](https://github.com/paulmann/MD.Scribe/releases)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Tests](https://img.shields.io/badge/tests-214%20passed-brightgreen)](tests)
[![CDN requests](https://img.shields.io/badge/CDN%20requests-0-success)](#zero-cdn-policy)

<img src="https://github.com/paulmann/MD.Scribe/raw/main/assets/social-preview.png"
     alt="MD.Scribe — self-hosted PHP 8.5 Markdown documentation platform with a viewer and an admin panel"
     width="100%">

[**Quick start**](#quick-start) · [**Feature tour**](#feature-tour) · [**Configuration**](#configuration--mdini) · [**Documentation**](#documentation) · [**Wiki**](https://github.com/paulmann/MD.Scribe/wiki) · [**AGENTS.md**](AGENTS.md) · [**Changelog**](CHANGELOG.md) · [**Security**](SECURITY.md) · [**Contributing**](CONTRIBUTING.md)

> **Documentation status.** The version and every number in this README were measured on
> 2026-11-14 against the `main` tree of 3.4.4, with the command named where the number appears.
> Task-oriented guides live in the [wiki](https://github.com/paulmann/MD.Scribe/wiki/Home);
> reference documentation lives in [`docs/`](docs/installation.md).

## Contents

- [What is MD.Scribe?](#what-is-mdscribe)
- [Quick start](#quick-start)
- [Feature tour](#feature-tour)
- [Configuration — `.md.ini`](#configuration--mdini)
- [URL contract](#url-contract)
- [Embedding and CLI rendering](#embedding-and-cli-rendering)
- [Security](#security)
- [In numbers](#in-numbers)
- [Development](#development)
- [Documentation](#documentation)
- [Languages of the documentation](#languages-of-the-documentation)
- [Upgrading from v2](#upgrading-from-v2)
- [Contributing](#contributing)
- [License](#license)
- [Help the project: evidence, not engagement](#help-the-project-evidence-not-engagement)

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

**New here?** Start with the [wiki](https://github.com/paulmann/MD.Scribe/wiki/Getting-Started)
([Home](https://github.com/paulmann/MD.Scribe/wiki/Home)) — it takes you from the release
archive to a verified install in four commands.

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

Requirements: **PHP ≥ 8.5** with `mbstring`, `dom`, `libxml`, `json`
(`curl` + `phar` + `zip` recommended for the updater and backup archives).

---

## Feature tour

### Reading experience

| Area | Features |
| --- | --- |
| Typography | Fluid type scale, text size / page width / line-height controls, serif & system font options, print stylesheet |
| Navigation | Auto TOC with scrollspy, heading numbers + hover anchors, `[TOC]` placeholder, breadcrumbs, prev/next pager, folder sidebar, skip links |
| Discovery | Server-side **full-text search** (inverted index, CJK-aware) with **operators** (`tag:`, `path:`, quoted values), **Ctrl+K command palette** (documents, headings, actions), **tag index** (`?page=tags`), sitemap.xml, RSS feed |
| Rich content | Server-side **syntax highlighting** (~190 languages), **Mermaid** diagrams with auto-repair + diagnostics, **KaTeX** math (incl. mhchem), GitHub-style **alerts**, task lists, footnotes, definition lists, `==marks==`, ^super^/~sub~script, 1,900+ emoji shortcodes, image **lightbox** with captions, reading-time estimates |
| Knowledge base | **Wikilinks** (`[[Page]]`, `[[Page\|Alias]]`, `[[Page#Heading]]`) with missing-page markers, **backlinks** (“Linked from”), frontmatter **tags** with a public index, per-document **revision history** with restore, **static site export** to a self-contained ZIP |
| Glossary | Any two-column table becomes hover/tap **tooltips** with alias expansion (`Term (ABBR)`), live DOM content — zero duplication |
| Offline | Installable **PWA**: web manifest + service worker — assets cache-first, documents network-first with the last read copy as fallback, JSON APIs never cached (`PWA_ENABLED`) |
| Themes | 12 presets + neutral × accent × radius × font mixer, light/dark/**system auto-detect**, per-visitor persistence, admin-managed **custom themes**, FOUC-free pre-paint bootstrap |

### Markdown support

CommonMark **0.31-complete** via `league/commonmark`, plus GFM (tables with
alignment, strikethrough, task lists, autolinks), footnotes (reference **and**
inline), definition lists, attribute syntax `{#id .class}`, smart punctuation
(opt-in), YAML frontmatter (`title`, `description`, `tags`, `author`, `date`,
`draft`, `toc`, `numbering`), relative `.md` link rewriting with dead-link
markers, `[[wikilinks]]` with alias/anchor resolution, and legacy MD.Viewer
extras (universal pattern chains, glossary tables, `md.md` naming rule). Full
matrix:
**[docs/markdown-features.md](docs/markdown-features.md)**.

### Admin panel

| Page | What it does |
| --- | --- |
| **Dashboard** | Corpus stats, index freshness, health summary, quick actions, recent docs, activity feed |
| **Files** | Tree of all content, filter, create/rename/move/delete (soft-delete to **trash**), folders, multi-upload (.md + images with content sniffing), **new from template** |
| **Editor** | Split-pane live preview (the *real* engine), **Markdown toolbar** (bold, italic, code, link, wikilink, list, alert, table), ⌘S save with **sha256 conflict detection**, word/reading-time counter, image upload at cursor, deep links `?page=editor&file=…` |
| **Search index** | Stats, freshness, on-demand rebuild |
| **Backups & trash** | One-click content snapshots (+ `.md.ini`), restore with automatic pre-restore safety copy, zip download, prune-ready listing, deleted-file restore |
| **Revisions** | Per-document save history (newest `REVISIONS_MAX`, default 20), read-only preview, restore that keeps the current content as a `(pre-restore)` revision |
| **Updates** | GitHub release check (15-min cache), release notes, **verified apply** (tarball + per-file sha256 manifest, backup-first), machine-API docs |
| **Settings** | Every `.md.ini` key as a typed form (schema-driven), API-key regeneration, admin password change |
| **Custom themes** | Save brand accents (light+dark primaries) — instantly selectable in the public catalog |
| **Logs & audit** | Daily app logs + JSON audit trail (who did what, when, from where) |
| **Security & health** | 20+ checks: PHP version/extensions, writability, permissions, secrets posture, OPcache, CSP; hardening checklist |
| **Tools** | **Broken-link / dead-anchor / missing-media / orphan-document checker**, backlink and tag index rebuild, **static site export** (ZIP of self-contained HTML), cache clearing, feed & sitemap shortcuts |

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

## Embedding and CLI rendering

The Markdown pipeline is usable without the viewer: render a README, a release
note or a CMS block from any PHP caller or shell script, with the same
CommonMark/GFM support, extensions, highlighting and sanitiser.

```php
require '/path/to/MD.Scribe/src/bootstrap.php';

$renderer = new \MdViewer\Standalone\Renderer();
$result = $renderer->renderFile('/srv/repos/ragraf/README.md');
echo $result->html;                 // embed mode: portable code blocks, no numbering
```

```bash
bin/md-scribe-render README.md --html \
  --base-url="https://ragraf.example.com/" \
  --css="https://example.com/readme.css" > readme.html
```

Embed mode emits `<pre><code class="hljs language-…">` and plain headings so a
GitHub-README stylesheet (`github-markdown-css` + a highlight.js theme) can
style the page. Full option reference and integration recipes:
**[docs/standalone-renderer.md](docs/standalone-renderer.md)**.

Since 3.2.2 the same pipeline is also a stateless HTTP endpoint for other
applications — Markdown in, HTML fragment out, key-authenticated, no session:

```bash
curl -H "X-Api-Key: $API_KEY" -H 'Content-Type: application/json' \
  -d '{"content": "# Title\n\n- one\n- two", "format": "auto"}' \
  https://docs.example.com/index.php?api=render
```

`format` accepts `auto` (default), `markdown`, `html` (sanitised) and `text`
(wrapped verbatim text in `<div class="md-text">`). Contract, limits and statuses: **[docs/http-api.md](docs/http-api.md)**.

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

Reporting a vulnerability: **[SECURITY.md](SECURITY.md)**.

---

## In numbers

Measured on 2026-11-14 against the `main` tree of 3.4.4:

| Surface | Value | Measurement source |
| --- | ---: | --- |
| PHP source files | 116 | `Get-ChildItem src,public,templates -Recurse -Filter *.php` |
| Test files | 22 | `Get-ChildItem tests -Recurse -Filter *.php` |
| PHPUnit tests | 214 tests, 678 assertions, 1 skipped | `composer test` |
| Documentation pages | 12 | `Get-ChildItem docs -Filter *.md` |
| Runtime Composer packages | 2 (`league/commonmark`, `scrivo/highlight.php`) | `composer.json` → `require` |
| Curated themes | 12 | `config/themes.php` |
| CDN requests at runtime | 0 | `default-src 'self'` in the enforced CSP |

---

## Development

```bash
composer install                 # dev deps: phpunit, phpstan
cd tools/frontend && npm install && node build.mjs && cd ../..

composer test                    # 214 PHPUnit tests, 678 assertions
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

Two layers: the **[wiki](https://github.com/paulmann/MD.Scribe/wiki)** is the task-oriented
guide (get it running, configure it, operate it), while the files in `docs/` are the
reference you check against the tag you run.

| Document | Contents |
| --- | --- |
| **[Wiki](https://github.com/paulmann/MD.Scribe/wiki)** | Getting started, installation, configuration, Markdown features, search, admin guide, HTTP API, updates, troubleshooting, development, changelog — start at [Home](https://github.com/paulmann/MD.Scribe/wiki/Home) |
| [docs/installation.md](docs/installation.md) | All deployment layouts, Apache/nginx/Caddy/Docker, shared hosting, permissions |
| [docs/configuration.md](docs/configuration.md) | Complete `.md.ini` reference, per-directory files, feature toggles |
| [docs/markdown-features.md](docs/markdown-features.md) | Full syntax matrix with examples |
| [docs/admin-guide.md](docs/admin-guide.md) | Every admin page, workflows, machine API |
| [docs/theming.md](docs/theming.md) | Theme system, tokens, custom themes, recipes |
| [docs/security.md](docs/security.md) | Threat model, hardening checklist, CSP notes |
| [docs/http-api.md](docs/http-api.md) | JSON endpoints (public + admin + machine API) |
| [docs/standalone-renderer.md](docs/standalone-renderer.md) | Embedding the Markdown pipeline, CLI and HTTP render API |
| [docs/development.md](docs/development.md) | Architecture, build pipeline, testing, releases |
| [docs/troubleshooting.md](docs/troubleshooting.md) | Common problems and fixes |
| [docs/upgrade-from-v2.md](docs/upgrade-from-v2.md) | Migration mapping and steps |
| [docs/AGENTS-context.md](docs/AGENTS-context.md) | Deep project context for coding agents |
| [CONTRIBUTING.md](CONTRIBUTING.md) | How to propose a change and what must pass first |
| [SECURITY.md](SECURITY.md) | Supported versions and private vulnerability reporting |
| [CHANGELOG.md](CHANGELOG.md) | Every release, Keep a Changelog format |

---

## Languages of the documentation

The English README is canonical. A translated README mirrors this file section for section, and
is updated in the same commit whenever a heading, a command or a number changes. The wiki and
`docs/` remain English.

| Language | File | Notes |
| --- | --- | --- |
| English | [README.md](README.md) | Canonical |
| Russian | [README.ru.md](README.ru.md) | Translation of this file |
| German | [README.de.md](README.de.md) | Translation of this file |
| Chinese (Simplified) | [README.zh.md](README.zh.md) | Translation of this file |
| Hindi | [README.hi.md](README.hi.md) | Translation of this file |
| Spanish | [README.es.md](README.es.md) | Translation of this file |
| French | [README.fr.md](README.fr.md) | Translation of this file |

A translation that falls behind is a defect: report it with the language and the section, or open
a pull request that fixes it.

---

## Upgrading from v2

Drop-in: deploy v3 beside your v2 files, move (or point `BROWSE_DIR` at) your
documents, keep the root `md.php` shim — every old bookmark and `?file=` link
works. Step-by-step: **[docs/upgrade-from-v2.md](docs/upgrade-from-v2.md)**.

---

## Contributing

One logical change per pull request, a test for new behaviour, and no new runtime dependency
without a discussion first. The gates are `composer test` (214 tests) and `composer stan`
(PHPStan level 6); both must exit `0`. Conventions, commit style, the documentation language
policy and the bug-report checklist: **[CONTRIBUTING.md](CONTRIBUTING.md)**. This project follows
the [Contributor Covenant 2.1](CODE_OF_CONDUCT.md); help with a question or a triage is
described in [SUPPORT.md](SUPPORT.md).

## License

MIT — see [LICENSE](LICENSE). Free for commercial and private use, modification
and redistribution, with the copyright notice kept. Earlier versions (up to
3.3.0) shipped under PolyForm Noncommercial 1.0.0.
Markdown engine: [league/commonmark](https://commonmark.thephpleague.com/) (BSD-3).
Highlighting: [scrivo/highlight.php](https://github.com/scrivo/highlight.php) (BSD-3, highlight.js port).
Diagrams: [Mermaid](https://mermaid.js.org/) (MIT). Math: [KaTeX](https://katex.org/) (MIT).
Icons: [Lucide](https://lucide.dev/) (ISC). Emoji data: [gemoji](https://github.com/github/gemoji) (MIT).
Fonts: Inter & JetBrains Mono (SIL OFL).

## Help the project: evidence, not engagement

What actually helps, in order of value:

- a **compatibility report**: PHP version (`php -v`), web-server and version, deployment layout
  (docroot `public/`, docroot at root, shared hosting), and the exact command with its output —
  one report per unique combination, after searching the existing issues;
- a **documentation fix** as a pull request that names the file and the line;
- a **sanitised case study** published with its data owner's permission;
- a **dependency or security note** for a fork or a wrapper, disclosed in the same place.

Never automate stars, forks, watchers, comments, issues, pull requests or bulk messages to the
maintainer or to third parties. An agent may draft a report; a human publishes it after review.
