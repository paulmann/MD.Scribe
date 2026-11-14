# Changelog

All notable changes to this project are documented here. The format is
[Keep a Changelog](https://keepachangelog.com/en/2.0.0/) 2.0.0, and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- README translations for Russian, German, Chinese, Hindi, Spanish and French
  (`README.ru.md`, `README.de.md`, `README.zh.md`, `README.hi.md`,
  `README.es.md`, `README.fr.md`) with a language switcher in every language.
- Community and compliance files — `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`,
  `SUPPORT.md`, `SECURITY.md`, `CITATION.cff`, `.editorconfig`,
  `.gitattributes`, the pull-request template, issue forms, `CODEOWNERS`,
  Dependabot configuration — and `assets/social-preview.png` + `.svg` for the
  README banner.
- `.ghagent.json`, which records the repository's documentation contract for
  the steward (`ghagent audit`, `doc`, `changelog`, `assets`, `privacy`); the
  sample corpus under `content/` is excluded because it uses the application's
  own query routes and a deliberate dead-link fixture.

### Changed

- README rebuilt to the repository documentation contract: one-paragraph
  summary, table of contents, banner image, measured numbers and their source,
  "For agents" link, language switcher.
- CHANGELOG normalized to Keep a Changelog (ISO-dated version headings,
  standard change types); repository metadata (description, topics) refreshed
  to PHP 8.5.
- `AGENTS.md` reduced to a 118-line map (commands, invariants, traps, machine
  APIs, verification, prohibitions); the deep reference it used to carry moved
  to `docs/AGENTS-context.md`, and stale PHP 8.3 / 3.3.0 facts were corrected.
- Dependabot version updates moved from a weekly to a monthly cadence with an
  `open-pull-requests-limit` of two per ecosystem: the seven first-run pull
  requests showed that a bump only reaches a user when the owner regenerates
  `public/assets/vendor/` or the release archive, so the weekly stream asked for
  reviews without shipping anything.
- Three major bumps are deferred in `dependabot.yml` by version, not by update
  type: mermaid `12.x` (flow edge-ID syntax changed; the bundle is copied by
  `node build.mjs --vendor`), lucide-static `1.x` (icon names can change and
  `src/View/Icons.php` falls back to `circle-alert` without a word) and
  phpunit `13.x` (`require-dev` pins `^11.5`). An `update-types: semver-major`
  rule was rejected on purpose: `ignore` also silences security updates, so it
  would hide a future advisory along with the noise.
- `docs/development.md` gained a dependency policy section: which packages are
  installed at release time, which files travel inside the archive, what the
  frontend pipeline copies into the repository, and that the only outbound
  request the application makes is the updater's release check.

### Fixed

- Dead anchor in `docs/admin-guide.md`, a placeholder link in
  `docs/http-api.md`, and code fences without a language tag in `docs/`.
- Sample content still claimed PHP 8.3 (`content/index.md`,
  `content/getting-started.md`, including an nginx snippet); both now state the
  8.5 floor.
- **The vendor rebuild dropped the icon fallback.** `node build.mjs --vendor` and
  `node generate-icons.mjs` both write `src/View/Icons.php`, but only the second
  added the `circle-alert` entry that `Icons::svg()` falls back to. After the
  lucide-static refresh the class raised `Undefined array key "circle-alert"`
  and drew an empty `<svg>` for any name not in `icons.json`, which is what the
  admin editor badge and the updater notice use. Both generators now apply the
  same rule, and `tests/Unit/IconsTest.php` fails if the fallback goes missing.

## 3.4.4 - 2026-11-13

### Changed

- **The web app manifest is served by the application** (`?api=manifest`) with
  `Content-Type: application/manifest+json`, and the layout links to that instead
  of the static `public/manifest.webmanifest`. The media type no longer depends on
  the host knowing the extension, so no server configuration is needed at all. The
  static file still ships as a fallback.

### Fixed

- **Wide tables were clipped at the column edge.** `.md-body table` was drawn at
  `width: max-content` with `white-space: nowrap` in the header row, so a table
  wider than the reading column ran past it and its text was cut off mid-word —
  with no visible hint that it scrolls. Tables now take the column width and wrap
  their cells (`overflow-wrap: break-word`), so nothing is clipped; genuinely wide
  tables still scroll inside `.md-table-wrap`. Measured on a real document before
  and after with a 1440px window: 896px table in a 736px column, then 736px.
- **The documented MIME fix could break a whole vhost.** `docs/installation.md`
  advised `types { application/manifest+json webmanifest; }`, but a `types` block
  *replaces* the inherited MIME table (and Debian's `mime.types` is itself wrapped
  in one, so re-including it there is a syntax error). Applying that advice on
  nginx 1.24 served every `.css`, `.js` and `.svg` as `application/octet-stream`
  and the site rendered unstyled. The guide now maps only the extension
  (`location ~ \.webmanifest$ { default_type … }`), explains why `types` must not
  be used, and adds a four-command check of the whole MIME table rather than of the
  one new entry.
- `docs/troubleshooting.md` gained the three symptoms that were found this way:
  an unstyled vhost after a MIME change, a manifest served as octet-stream, and a
  clipped wide table.

## 3.4.3 - 2026-11-13

### Changed

- **Static MIME type for the PWA manifest.** nginx and Apache do not know
  `.webmanifest`, so `public/manifest.webmanifest` was served as
  `application/octet-stream` on a real host (measured on nginx 1.24: browsers
  accept it, devtools reports a bad type). `docs/installation.md` now carries the
  one-line `types` / `AddType` fix, next to a note that `sw.js` needs nothing.
- `docs/installation.md` gained a **Verified installations** table: layouts that
  were actually exercised end to end, with the PHP version and the checks that ran.

## 3.4.2 - 2026-11-13

### Fixed

- **A dry run lied about what an update would do.** `dry_run=1` compared the
  local files with the *local* manifest, so an install that matched its own
  manifest reported "nothing to replace" no matter what the release contained —
  a green answer that examined the wrong object. It now downloads the release
  archive, verifies every entry against the manifest embedded in it and reports
  the files that would really be replaced (`to`, `asset`, `verified`,
  `unchanged`, `steps`); it still writes nothing, and it fails loudly when the
  release has no archive instead of reporting success.
- The admin Updates panel now shows whether the release carries an installable
  archive and disables **Apply** when it does not, instead of offering a button
  that fails at the last step.
- Download and verification moved into one `openRelease()` path shared by
  `apply()` and `plan()`, so a dry run and a real update cannot drift apart.

## 3.4.1 - 2026-11-13

### Fixed

- **The updater was unreachable on the recommended layout.** With docroot set to
  `public/`, `updater.php` sits above the document root, so `GET /updater.php`
  answered 404 and the machine API (`action=status|conflicts|install`) could not
  be used at all. `public/updater.php` is now a one-line bridge to the root file;
  the root file computes its paths from its own `__DIR__`, so both layouts behave
  identically.
- The updater landing page linked to `public/admin.php` unconditionally; on a
  docroot-at-root install that path does not exist. It now points at whichever of
  `admin.php` / `public/admin.php` is actually present.

## 3.4.0 - 2026-11-13

PHP 8.3 and 8.4 are dropped; the platform is **PHP 8.5** (verified on 8.5.12).
The updater becomes installable again, the editor gains a toolbar and templates,
search learns operators, and the viewer can be installed as an offline app.

### Added

- **Search operators**: `tag:`, `path:` and quoted values (`tag:"release notes"`),
  combinable with free text (`deploy tag:ops path:runbooks/`). Documents carry
  their tags in the search index, so a filter needs no second lookup; an unknown
  `word:` stays prose instead of silently dropping the word.
- **Document templates**: five built-in skeletons (blank, ADR, meeting notes,
  how-to, reference) with a **New from template** panel on the admin Files page.
  A title becomes a path-safe slug, an existing document is refused with `409`,
  and the new file opens in the editor.
- **Editor toolbar**: bold, italic, inline code, link, wikilink, list, alert and
  table insertions at the cursor (Ctrl/⌘+B and +I too), wired through a CSP-nonce
  script so nothing has to be rebuilt.
- **Offline reading (PWA)**: `public/manifest.webmanifest` plus `public/sw.js` —
  assets are cache-first, documents are network-first with the last read copy as
  the fallback, and JSON APIs are never cached. Toggle: `PWA_ENABLED`.
- Admin action `file.createFromTemplate`; the toolbar buttons are text glyphs
  because the generated icon map has no `bold`, `italic`, `link` or `table`.

### Changed

- **PHP requirement raised to 8.5.** The bootstrap gate, `composer.json` and the
  CI matrix no longer accept 8.3 or 8.4. Any 8.5.x patch release is accepted so a
  host on the 8.5 line is not bricked by a patch-level floor; 8.5.12 is the
  version the project is verified on.
- **Licence is MIT** (was PolyForm Noncommercial 1.0.0). Commercial and private
  use, modification and redistribution are allowed with the notice kept.
- **The self-updater installs the release archive, not a branch tarball.**
  `apply()` downloads the `MD.Scribe-<version>.zip` asset of the latest release,
  refuses archives with unsafe paths, verifies every file against the manifest
  embedded in the archive, backs up what it replaces and refuses the whole update
  on the first mismatch. A branch tarball can never satisfy the manifest because
  it does not contain `vendor/` — that was the reason every update failed.
  `check()` now reports `archive` and `installable`, and the panel disables the
  apply button when the release carries no archive.
- `config/manifest.json` is regenerated for the release (it had been frozen at
  3.1.0, so `status()` reported every later file as `modified`).

### Fixed

- Admin and editor buttons referenced icon names that do not exist in the
  generated map (`file-plus`, `folder-plus`, `image-plus`), so they rendered as
  the fallback warning triangle.
- `docs/development.md` now documents why `bin/md-scribe-render` sits outside
  PHPStan and the CI lint step, and what that costs.

## 3.3.0 - 2026-11-13

A knowledge-base release: documents can link to each other by name, the
library knows who links where, frontmatter tags get a real index, every save
keeps history, and the whole site can be exported as static HTML.

### Added

- **Wikilinks** — `[[Page]]`, `[[Page|Alias]]`, `[[Page#Heading]]` and
  `[[#Heading]]` render as links to documents in the library. Resolution is
  relative to the source document first, then the browse root; `.md` is
  optional. A link whose document does not exist is still clickable but
  carries `md-wikilink-missing`; absolute paths, `..` segments and URL schemes
  are refused (`md-wikilink-refused`). Inline code and fenced blocks are never
  rewritten. Toggle: `FEATURE_WIKILINKS` (default on).
- **Backlink index** (`MdViewer\Files\BacklinkIndex`) — target → sources map
  in `storage/index/backlinks.json`, refreshed lazily from a corpus stamp and
  rebuildable from Tools. The viewer renders a **“Linked from”** panel under
  every document. Toggle: `BACKLINKS_ENABLED` (default on).
- **Tag index** (`MdViewer\Files\TagService`) — reads `tags:` from frontmatter
  (inline or block list), normalises them and serves the public
  `?page=tags` page with a tag cloud and per-tag document lists. Toggle:
  `TAGS_ENABLED` (default on).
- **Document revisions** (`MdViewer\Files\RevisionService`) — every successful
  editor save keeps the content under `storage/revisions/`, newest
  `REVISIONS_MAX` (default 20) per document, identical content skipped. New
  admin page **Revisions**: history per document, read-only preview and
  restore. Restoring first stores the current content as a `(pre-restore)`
  revision, so it is itself undoable. Toggle: `REVISIONS_ENABLED`.
- **Static site export** (`MdViewer\Admin\StaticExportService`) — renders every
  document to a self-contained HTML file with one embedded stylesheet, copies
  content assets (bounded at 200 MB / 5000 files) and packs everything into
  `storage/exports/site-<timestamp>.zip`. Admin action `export.static` plus an
  authenticated `export.download` route; the archive opens from `file://`.
  Mermaid and math stay as source: rendering them needs JavaScript.
- Admin JSON actions `backlinks.rebuild`, `tags.rebuild`, `revision.list`,
  `revision.read`, `revision.restore`, `export.static`.
- Tests: wikilink parser/renderer/resolver, backlink and tag indexes, revision
  retention and restore inputs, static export archive (184 tests total).

### Changed

- The render-cache key and the document ETag now include a **corpus stamp**
  (paths + mtime + size of every document), so creating, deleting or renaming a
  document invalidates cached wikilink states without invalidating every page
  for an ordinary content edit.
- `FileManager` accepts an optional `RevisionService` and stores a revision
  after every successful write.
- `RenderCache::key()` takes an optional corpus stamp (backwards compatible).
- `config/manifest.json` is still the 3.1.0 release manifest; regenerate it
  with `php tools/build-manifest.php` when cutting a release (see
  `docs/development.md`).

### Fixed

- **In-page anchors for non-Latin headings.** The sanitizer kept an `id` only
  when it matched `^[A-Za-z]…`, so every Cyrillic or CJK heading id was silently
  deleted and the TOC, the heading permalink and any `#fragment` link pointed at
  a fragment that no longer existed. `id` now accepts Unicode letters and digits
  (still bounded, still without spaces, quotes or angle brackets), and heading
  anchors such as `#что-сначала-попробовал` survive sanitising.
- `PathValidatorTest::testValidateNewPath` compared a native Windows path with a
  `/`-separated literal and failed on Windows only; the assertion now normalises
  the separator.

## 3.2.3 - 2026-10-29

### Changed

- **`format=text` now wraps.** Plain text is emitted as
  `<div class="md-text" style="white-space:pre-wrap;overflow-wrap:anywhere">`
  instead of `<pre>`: verbatim text was right, but `<pre>` does not break
  long lines, so a stored answer turned the portal's «Ответ AI» window
  into a horizontal scrollbar. The inline style keeps the wrapping in any
  consumer (portal modal, HTML e-mail) without a stylesheet.
- Tests and `docs/http-api.md` follow the new fragment shape.

## 3.2.2 - 2026-10-29

Machine rendering over HTTP. The standalone renderer already worked from PHP
and the shell; an application that stores Markdown answers needed it as a
service — no session, no CSRF token, no browser.

### Added

- **Machine render endpoint** — `POST /index.php?api=render`, authenticated
  with the `API_KEY` from `.md.ini` in the `X-Api-Key` header (or `?api_key=`).
  JSON body `{"content": "…", "format": "auto"}`; answer
  `{ok: true, data: {format, html, bytes, elapsed_ms, renderer}}`.
  - `format=markdown` → `MdViewer\Standalone\Renderer` in embed mode: a bare
    HTML fragment (no `<html>`/`<head>`/`<body>`, no viewer chrome).
  - `format=html` → the DOM allowlist `HtmlSanitizer` — `<script>`, `on*`
    handlers and `javascript:` URLs are removed.
  - `format=text` → `<pre>` with `htmlspecialchars()`.
  - `format=auto` (default) → HTML when the payload starts with a block-level
    tag from the allowlist, Markdown when any explicit Markdown construct is
    present (ties go to Markdown), plain text otherwise.
  - Statuses: `401 bad_key`, `422 empty_content`, `422 bad_format`,
    `413 too_large` (content above 256 KiB).
  - Stateless: no session, no CSRF, nothing written to disk. The legacy
    session+CSRF `POST ?api=render` (clipboard preview) is unchanged and still
    handles every request that carries no key.
- **`MdViewer\Api\{RenderService, RenderedFragment, RenderError, ApiKeyAuth}`**
  — the validation and rendering half of the endpoint, usable without HTTP.
- PHPUnit coverage for the three formats, sanitisation, auto-detection and the
  status codes.

### Changed

- `docs/http-api.md` — new section documenting the machine render API.
- `AGENTS.md`, `README`-adjacent docs reference version 3.2.2.

## 3.2.1 - 2026-10-22

### Added

- **`MdViewer\Standalone\BadgeStripper`** and the `stripBadges` renderer option:
  remove a README badge plate (img.shields.io, badgen, codecov, CI badges…) in
  memory before rendering. The source file on disk is never modified.
- `bin/md-scribe-render --strip-badges` exposes the same behaviour for CLI
  embedding (documented in `docs/standalone-renderer.md`).

### Fixed

- Standalone HTML pages now use the neutral `← Files` back label.

## 3.2.0 - 2026-10-22

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
- **`RenderContext::$embedMode`** (`embedMode` render option): suppresses
  viewer-only chrome in embedded output — external-link icons, quote copy
  buttons and glossary tooltips.
- PHPUnit coverage for the standalone renderer (embed markup, base-URL link
  rewriting, viewer-mode regression).

### Changed

- README: new "Embedding and CLI rendering" section.
- `AGENTS.md` and `docs/` reference version 3.2.0.

## 3.1.0 - 2026-10-14

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

### Fixed

- **The self-updater no longer pins a product name when unpacking a release
  archive.** It located the extracted root with `glob('.../MD.Viewer-*')`, so a
  renamed repository — which GitHub names after the repository, e.g.
  `MD.Scribe-main/` — would have been rejected with *"does not look like a
  release"*. It now accepts any `<name>-<ref>` directory that contains
  `config/manifest.json`, with a fallback for a flat archive.

## 3.0.1 - 2026-10-14

Bug-fix release. Two defects of the same class — a path comparison that assumed
`/` as the directory separator — both found by serving the application rather
than by reading it.

### Changed

- Behaviour on Linux and macOS is unchanged by both fixes — there the separator
  is `/`, so the old comparisons already held. Confirmed by serving the same
  tree on Debian (PHP 8.5) and on Windows.
- `REPAIR_VERSION` in `MermaidNormalizer` stays at `3.0.0`: it versions the
  mermaid repair rules, which this release does not touch.

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

## 3.0.0 - 2026-10-10

A complete professional refactor of MD.Viewer v2 (the 4,700-line `md.php`
monolith) into a structured PHP 8.3 application — plus a full admin panel,
a 2026 design system and zero CDN dependencies.

### Added

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

### Changed

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
- **NEW**: 126 PHPUnit tests (333 assertions) incl. security regression
  suites; PHPStan level 6 clean; GitHub Actions CI; Docker + nginx + Caddy +
  Apache configs; `php -S` dev router; release manifest builder;
  `.gitignore`; MIT LICENSE retained.
- **DOCS**: README rewritten; `docs/` — installation, configuration
  reference, markdown matrix, admin guide, theming, security model, HTTP &
  machine API, development & release process, troubleshooting, upgrade
  guide.

### Security

- **NEW**: CSP with per-request nonces; hardened session cookies; COOP /
  referrer / permissions policies; `X-Robots-Tag: noindex` on admin;
  SVG uploads refused; image uploads content-sniffed; admin editor
  optimistic concurrency; secrets never rendered; `.md.ini` write mode 0640.
- **KEEP**: 14-layer path validator (ported verbatim + tested), opaque
  octet-stream downloads, per-directory flag layering, machine-API key
  semantics, three-level feature gating (ini / server / UI).

**2.x (legacy).** See the v2 repository history — `md.php` up to v2.18.0 and
`updater.php` up to v3.14.0, with the changelog embedded in the file headers.
