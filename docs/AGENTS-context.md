# Project context: MD.Scribe 3.4.4 «Keystone»

Deep reference for an agent working on this repository. Read `AGENTS.md` first;
read the code before asserting anything. Everything here was verified against
the tree at 3.4.4 — when a line number disagrees with the file, the file wins
and the disagreement is a documentation defect worth reporting.

Platform facts that changed in the 3.4 line: **PHP floor is 8.5** (8.3 and 8.4 are
refused at bootstrap; verified on 8.5.12), the licence is **MIT**, and the web app
manifest is served by the application rather than as a static file.

---

## 1. What the product is

A self-hosted documentation platform: point it at a folder of `.md` files and it
serves a CommonMark/GFM viewer, a searchable library, a theme catalog, a tag
index and a full admin panel (file manager, split-pane editor, backups, trash,
revisions, self-updater, health checks, audit trail, static export).

- **No database, no framework, no server-side Node, no build step on the host.**
  Every document is a file; every cache is a JSON file under `storage/`.
- **Deployment shape:** PHP >= 8.5 with `mbstring`, `dom`, `libxml`, `json`
  (`src/bootstrap.php` refuses to boot otherwise). Docroot is either `public/`
  (recommended) or the project root, where root shims (`index.php`, `md.php`,
  `updater.php`) keep the v2 URL contract.
- **Consumers:** two kinds of installation — a single-tenant docs site behind
  the built-in admin, and applications that call the machine render API
  (`POST ?api=render`) to turn Markdown into HTML fragments server-side.
- **Zero-CDN / air-gapped:** Mermaid, KaTeX, fonts and icons are vendored under
  `public/assets`, the CSP is `default-src 'self'`.

## 2. Request lifecycle

| Step | Where |
|---|---|
| Public entry boots the kernel and hands off to one front controller | `public/index.php:29-31` |
| Kernel: config, logger, session, CSRF, template engine, CSP nonce, error handlers | `src/Core/App.php:44-80` |
| Lazy services (`repo`, `search`, `markdown`, `fileManager`, `themes`, `uploader`) | `src/Core/App.php:118-165` |
| Routing (query parameters, no rewrite rules) | `src/Http/FrontController.php:39-126` |
| Admin entry, separate script so the panel can be firewalled | `public/admin.php:12-22`, `src/Http/AdminKernel.php:44-115` |

Dispatch order for GET: `download` → `media` → `api` → `page` → `file` → home
document → browser (`src/Http/FrontController.php:75-126`). A request carrying
both `?page=` and `?file=` resolves as a page.

**JSON GET endpoints** live in one `match` (`src/Http/FrontController.php`):
`search`, `suggest`, `toc`, `config` and — since 3.4.4 — `manifest`. The manifest
is deliberately *not* a static file: `.webmanifest` is missing from most
`mime.types` tables, so a static file arrives as `application/octet-stream`, and
the usual server-side fix (a `types { }` block) replaces the whole MIME table and
turns every css/js on the host into octet-stream. `ApiController::manifest()` builds
the document through `Core\Manifest::build()` (name, scope and start_url from
`SITE_NAME` and the install prefix, so a sub-directory install gets `/docs/`) and
answers through `Response::manifest()`, which sets the media type itself.

**Adding a public page** is four edits: an arm in the `match` at
`src/Http/FrontController.php:103-111`, a method on
`src/Http/Controllers/PageController.php`, a `templates/pages/<slug>.php`
template, and a nav link in `templates/layout/app.php`.

**Adding an admin action** is one arm in the `match` at
`src/Http/AdminKernel.php:169-235` (naming: dotted `verb.noun`). CSRF, session
auth and the audit actor are already applied by the kernel
(`src/Http/AdminKernel.php:57-65`); do not add a bypass ahead of that line.
**Adding an admin page** additionally needs the slug in four places — the
allowlist (`:103-106`), the title map (`:404-416`), the data `match`
(`:417-434`) and `templates/admin/<slug>.php`; an unknown slug silently
degrades to the dashboard instead of erroring.

## 3. Markdown pipeline

`MarkdownEngine::render(string $source, array $options = []): RenderResult`
(`src/Markdown/MarkdownEngine.php:69`). Options: `docDir`, `docPath`,
`urlBuilder`, `mediaUrlBuilder`, `fileExists`, `plainCodeBlocks`, `embedMode`.
Anything else is ignored silently.

Order is deliberate and documented at `src/Markdown/MarkdownEngine.php:8-20`:
normalise → frontmatter → source preprocessing (math/TOC tokens) → glossary
parse → league/commonmark → token restore → **sanitise (always)** → glossary
annotate → metadata → `RenderResult`.

- Extensions are gated by feature toggles in
  `src/Markdown/MarkdownEngine.php:236-257`; the aggregate
  `MdViewerExtension` registers parsers conditionally and renderers always
  (`src/Markdown/Extensions/MdViewerExtension.php:46-80`).
- Renderers are stateless: per-render state arrives through
  `ContextHolder`/`RenderContext` (`src/Markdown/RenderContext.php`), never
  through constructor captures — that is what makes the converter cacheable
  per feature fingerprint.
- **The render contract for a new inline extension:** a toggle with
  `'feature' => true` in `config/defaults.php` (otherwise it never enters the
  fingerprint), registration in `MdViewerExtension`, allowlisted output in
  `HtmlSanitizer` (tags `:41-51`, attributes `:66-91`, `data-*` `:102-109`,
  `id` `:94-99`), and a `Version` bump for the cache.
- `HtmlSanitizer` runs on every render, not only in raw-HTML mode
  (`src/Markdown/MarkdownEngine.php:124-130`). It is an allowlist: unknown tags
  are unwrapped, unknown attributes dropped, and `id` values must match
  `/^[\p{L}\p{N}][\p{L}\p{N}_:.\-]{0,96}$/u` — the Unicode branch was added in
  3.3.0 because the ASCII-only rule silently deleted every Cyrillic heading id
  and broke in-page anchors (`src/Markdown/HtmlSanitizer.php:252-266`).

### Render cache

`storage/cache`, one entry per
`Version | relative path | mtime | size | feature fingerprint | corpus stamp`
(`src/Markdown/RenderCache.php:39-42`, key built in
`src/Http/Controllers/ViewerController.php:99-112`).

- The **feature fingerprint** is `Version::VERSION` + `ALLOW_RAW_HTML` + the
  toggleable snapshot (`src/Markdown/MarkdownEngine.php:180-190`).
- The **corpus stamp** is a hash of every document's path, mtime and size
  (`src/Files/CorpusStamp.php`). It exists because wikilink existence depends on
  the set of documents: creating a missing page must invalidate the pages that
  link to it. Editing ordinary content does not change it.
- `ALLOW_IFRAMES` and `ALLOW_EXTERNAL_IMAGES` change sanitizer output but are
  **not** in the fingerprint — after toggling them, run `cache.clear`.

## 4. Content layer

| Concern | Class |
|---|---|
| Path rules (14 layers) | `src/Files/PathValidator.php` — `validateFile`, `validateNewPath`, `validateDir`, `validateMedia` |
| Scan, read, neighbours, stats | `src/Files/MarkdownRepository.php` |
| Writes, soft delete, trash, images | `src/Files/FileManager.php` |
| Reader uploads / clipboard save | `src/Files/Uploader.php` |
| Per-document history | `src/Files/RevisionService.php` |

- Every user-supplied path goes through `PathValidator`. There is no second
  implementation, and adding one is the defect this class exists to prevent
  (`src/Files/PathValidator.php:6-18`).
- `FileManager::write()` is atomic: temp file + `LOCK_EX` + `chmod 0644` +
  `rename` (`src/Files/FileManager.php:368-386`). It supports optimistic
  concurrency (`$expectedHash` → `409`), caps a document at 8 MB, and records
  `content.create` / `content.update` in the audit trail.
- Deletes are soft: the file is renamed into `storage/backups/trash/` with a
  `<timestamp>-<flattened path>` name; restore parses the original path back out
  (`src/Files/FileManager.php:192-234`). A path containing a literal `__` cannot
  be restored to its original name — known limitation.
- **Document templates** (`src/Files/DocumentTemplates.php`, 3.4.4) are five
  built-in skeletons (blank, ADR, meeting notes, how-to, reference) with
  `{{title}}` / `{{date}}` / `{{slug}}` placeholders. They are strings, not files,
  so nothing on disk can drift from them. `slug()` is Unicode-aware and capped at
  80 characters; a title of pure punctuation becomes `untitled`. The admin action
  `file.createFromTemplate` writes through `FileManager::write()` (so it is
  audited), refuses an existing path with `409`, and returns `editor_url`.
- **The editor toolbar** (`templates/admin/editor.php`) inserts Markdown at the
  cursor through a CSP-nonce script and dispatches an `input` event, so the dirty
  flag, word count and live preview update exactly as if the user had typed.
  Buttons use text glyphs where the generated icon map has no name (`bold`,
  `italic`, `link`, `table` do not exist in `src/View/Icons.php`).
- Revisions (3.3.0) are written **after** a successful save
  (`src/Files/FileManager.php:155-156`), one directory per document keyed by
  `sha256(relative path)`, newest `REVISIONS_MAX` kept
  (`src/Files/RevisionService.php:60-118`). Restoring goes through
  `FileManager::write()`, so it is audited and itself creates a revision.

## 5. Indexes and their freshness model

| Index | File | Freshness |
|---|---|---|
| Full text | `storage/index/` (SearchIndex) | corpus fingerprint: file count + newest mtime + total bytes (`src/Files/SearchIndex.php:286-308`) |
| Backlinks | `backlinks.json` in the index directory | corpus stamp compared on read (`src/Files/BacklinkIndex.php:120-140`) |
| Tags | `tags.json` in the index directory | same corpus stamp (`src/Files/TagService.php:150-175`) |

All three are **lazily refreshed**: a request compares the stored stamp with the
live one and rebuilds on a difference, so a document added by git, FTP or the
editor is picked up without a manual step. The shared blind spot is that the
stamp only sees path, mtime and size — a same-size edit inside the same second
is invisible; **Tools → Rebuild** (actions `index.rebuild`, `backlinks.rebuild`,
`tags.rebuild`) is the reliable escape hatch after a bulk import.

Backlinks are built from `WikilinkResolver::extract()`, which strips fenced
blocks and inline code first (`src/Files/WikilinkResolver.php:150-200`), so a
`[[example]]` in a code sample never creates a phantom backlink.

### Search operators (3.4.4)

`SearchIndex::parseQuery()` splits a query into free-text terms and filters:
`tag:ops`, `tag:"release notes"` (normalised through
`TagService::normalizeTag()`), `path:runbooks/` (case-insensitive prefix). The index
records each document's frontmatter tags (`INDEX_VERSION = 2`), so a tag filter
needs no second lookup; `applyFilters()` drops non-matching documents from the
score map, and a filter-only query starts from every document. An unknown `word:`
stays prose — a colon never silently drops a word. The response echoes the parsed
filters (`"filters":{"tags":[…],"path":…}`) so a client can tell "nothing matched"
from "the filter was not understood". Regressions live in
`tests/Feature/SearchOperatorsTest.php`.

## 6. Security model

- **Output:** every template escapes with `e()` (ENT_QUOTES + ENT_SUBSTITUTE);
  rendered documents pass the DOM allowlist sanitizer; JSON goes through
  `Response::json` with `nosniff` (`src/View/Template.php:152-155`,
  `src/Core/Response.php:35-46`).
- **CSRF:** session token, field `_token`, header `X-CSRF-Token`, compared with
  `hash_equals` (`src/Core/Csrf.php:16-48`). `validate()` returns **true for
  every non-POST request** — a mutating GET is unprotected by construction, so
  every state change must be POST.
- **Admin auth:** session + configured password hash, per-IP rate limit
  (900 s window, floor of 3), first-run setup from localhost or with the
  one-time code in `storage/logs/setup.log` (`src/Admin/AdminAuth.php:28,51-56,
  110-156,196-207`).
- **Machine API:** `ApiKeyAuth::isValid()` — constant-time, empty key never
  authenticates (`src/Api/ApiKeyAuth.php:17-24`). The render API is taken only
  when a key is present; without one the request keeps the session+CSRF
  contract (`src/Http/FrontController.php:52-61`).
- **CSP:** emitted centrally at boot with a per-request nonce
  (`src/Core/App.php:144-181`). Inline `<script>`/`<style>` needs
  `nonce="<?= e($app->nonce()) ?>"` or it silently does not run.
- **Media:** SVG and ZIP are always attachments; text-ish types are served as
  `text/plain` with `nosniff` (`src/Http/Controllers/MediaController.php:36-41,
  70-100`).

## 7. Data on disk

| Path | Contents | In git |
|---|---|---|
| `.md.ini` | installation config; created on first load, missing keys appended with defaults | no |
| `content/` | the bundled corpus (overridable with `BROWSE_DIR`) | yes |
| `storage/cache` | render cache | no |
| `storage/index` | search, backlink and tag indexes | no |
| `storage/revisions` | per-document revision history (3.3.0) | no |
| `storage/exports` | generated static-site archives (3.3.0) | no |
| `storage/backups` | content snapshots, trash, app backups | no |
| `storage/logs`, `storage/state` | app logs, setup code, update-check cache, rate-limit state | no |
| `storage/themes` | admin-made custom themes | no |

Directories are created `0755`, files `0644` (`src/Core/Paths.php:107-135`).
If the PHP user differs from the deploy user, the first symptom is
"write failed (permissions?)", not a startup error.

## 8. Release and update contract

- **Three version carriers must agree:** `src/Core/Version.php:13` (source of
  truth), the newest `## x.y.z` heading in `CHANGELOG.md`, and the
  `Version: x.y.z` comment in `README.md`. `tools/Invoke-ProjectDoctrine.ps1`
  compares all three.
- **`config/manifest.json` is a release artefact** produced by
  `php tools/build-manifest.php` (949 entries at 3.4.4: 226 project files + 723
  runtime vendor files). The updater reads the *archive's own* manifest and
  refuses the entire update on the first hash mismatch
  (`src/Admin/UpdateService.php`, `openRelease()`).
- **How an update works (3.4.0):** `apply()` downloads the `.zip` asset of the
  latest release, refuses entries with unsafe paths, verifies every file against
  the manifest embedded in the archive and refuses the whole update on the first
  mismatch. The archive ships `vendor/`, which is why a branch tarball can never
  satisfy the manifest. `check()` reports `archive` and `installable`.
- **The manifest is regenerated at release time**, in a tree that has
  `composer install --no-dev` applied, and committed. Until that step runs the
  doctrine checker warns that the manifest is behind the code.
- **`plan()` is the honest dry run.** `dry_run=1` used to compare local files
  with the *local* manifest, which always answered "nothing to replace" — it was
  examining the wrong object. `plan()` downloads the release archive, verifies it
  against the manifest inside it and reports what would change; it writes nothing.
  `apply()` and `plan()` share `openRelease()`, so they cannot drift apart.
- **Releases:** one tag at a time in the 3.4 line — `v3.4.4` is the only 3.4
  release (measured 2026-11-14 with `gh release list`); the archive asset
  `MD.Scribe-3.4.4.zip` is attached and was deployed and verified before the
  release was published. CI runs on branches and on the release commit.

## 9. Build and test toolchain

| Command | Notes |
|---|---|
| `composer install` | needs `COMPOSER_HOME` when `APPDATA` is unset (sandboxed run) |
| `composer test` | `phpunit --colors=always`; suites `unit`, `feature`, `standalone` |
| `composer stan` | `phpstan analyse --memory-limit=1G`, level 6 |
| `composer check` | stan, then test |
| `php -S 127.0.0.1:8099 -t public public/router.php` | the smoke target CI uses |
| `node build.mjs` (in `tools/frontend`) | rebuilds `public/assets`; outputs are committed and CI warns (non-blocking) on drift |
| `pwsh -File tools/Invoke-ProjectDoctrine.ps1` | 12 convention checks; read-only, no PHP executed; `-ProjectRoot` lets you run it against a sandbox copy |

PHPStan analyses `src` + the five entry scripts only — `bin/`, `tools/` and
`templates/` are outside it. CI lints `src public templates tools tests` and the
three root scripts, but **not** `bin/md-scribe-render`.

Baseline at 3.4.4 on Windows (PHP 8.5.12): PHPStan level 6 clean; PHPUnit 214 tests /
679 assertions, all green; the doctrine checker 12 checks, 0 errors, 0 warnings. Until 3.3.0 one assertion in `PathValidatorTest` compared a native Windows
path with a `/`-separated literal and failed on Windows only — a test-portability
defect that has since been fixed.

## 10. Traps

| Trap | Evidence | What it costs |
|---|---|---|
| Cache key embeds `Version::VERSION`, not a code hash | `src/Markdown/RenderCache.php:39-42` | Editing a renderer without a version bump serves the old HTML for every unchanged file |
| Corpus stamp must stay in the key | `src/Http/Controllers/ViewerController.php:90-100` | Remove it and new documents keep the "missing" wikilink marker |
| Sanitizer drops `id`/attributes it does not recognise | `src/Markdown/HtmlSanitizer.php:41-109,252-266` | A new marker class or anchor silently disappears in the browser path only |
| `ALLOW_IFRAMES`/`ALLOW_EXTERNAL_IMAGES` are outside the fingerprint | `src/Markdown/MarkdownEngine.php:185` | Toggling them leaves stale HTML until `cache.clear` |
| Admin page slug lives in four places | `src/Http/AdminKernel.php:103,404,417,436` | A missed registration renders the dashboard instead of an error |
| `Icons::svg()` falls back to `circle-alert` for unknown names | `src/View/Icons.php:326` | A typo looks like a deliberate warning icon; only ~58 names exist |
| Corpus stamp is path+mtime+size | `src/Files/CorpusStamp.php:20-32`, `src/Files/SearchIndex.php:286-299` | Same-size edit in the same second stays unindexed; rebuild from Tools |
| Templates run without `strict_types` and `extract(..., EXTR_SKIP)` | `src/View/Template.php:94-98` | A data key colliding with a local is dropped without warning |
| A `types { }` block in nginx replaces the whole MIME table | `docs/installation.md`, measured on nginx 1.24 at the deployment host | Every `.css`/`.js`/`.svg` becomes `application/octet-stream`; the site renders unstyled. The project shipped this advice once — see the 3.4.4 changelog entry |
| Document tables at `width: max-content` with nowrap headers | `tools/frontend/src/css/typography.css` | A 736px column held an 896px table and the text was clipped mid-word; both CSS copies must stay identical (the build copies one onto the other) |
| A dry run that reads the local manifest examines the wrong object | `src/Admin/UpdateService.php` (`plan()` vs `status()`) | It answers "nothing to replace" whatever the release contains — a false PASS in product form |
| The updater installs the release archive, never a branch tarball | `src/Admin/UpdateService.php`, `docs/development.md` step 8 | A branch tarball lacks `vendor/`, so it can never satisfy the manifest and the update refuses itself |
| `vendor/composer/installed.php` records the build's git reference | `config/manifest.json` entry for that file | Rebuilding an old archive from `git archive` (no `.git`) changes one hash; build releases from a checkout |
| A manifest regenerated in the working tree lists dev vendor packages | `tools/build-manifest.php`, `composer.lock` | `--no-dev` and a dev install produce different `installed.php`; the release tree must be the one that is zipped |
| PHPUnit fails on risky tests and on output | `phpunit.xml:7-9` | An `echo` in a test fails the suite even when the assertion is right |
| `FileManager::write()` creates parent directories before validating the target | `src/Files/FileManager.php:131-137` | A rejected write can leave empty directories behind |
| Atomic writers `@`-suppress their filesystem errors | `src/Files/FileManager.php:375-379` | Permission or quota failures surface as a generic message |
| Root `.htaccess` only protects the docroot-at-root layout | `.htaccess:3-24`, `public/.htaccess:1-11` | With docroot at `public/`, `.md.ini` and `storage/` are safe only by being outside the docroot |
| `config/manifest.json` is stale (3.1.0) | `config/manifest.json:3` | Every update is refused; see §8 |

## 11. Open questions

| Question | How to settle it |
|---|---|
| Does the vendored release archive (`MD.Scribe-3.1.0.zip`) still match `main`? | Compare `config/manifest.json` against the tree after regenerating it |
| Is `bin/md-scribe-render` intentionally outside lint and PHPStan? | Decide, then either add it to `phpstan.neon` paths and the CI `find`, or document the exclusion |
| Does the `assets` CI job's drift warning ever fire in practice? | Run `node build.mjs` and compare `git diff --stat public/assets` |
| Which PHP version is the real deployment target? | `Version::PHP_MIN` says 8.3.0; CI covers 8.3 and 8.4; the local toolchain is 8.5.0 |
| Should the updater ship a release archive instead of `main`? | Product decision; both failure modes are recorded in §8 |

## 12. Where to start reading

| Task | Read first |
|---|---|
| Rendering / Markdown behaviour | `src/Markdown/MarkdownEngine.php`, `src/Markdown/Extensions/MdViewerExtension.php`, `docs/markdown-features.md` |
| Viewer page / navigation | `src/Http/Controllers/ViewerController.php`, `templates/viewer/document.php` |
| Admin action or page | `src/Http/AdminKernel.php`, the matching `templates/admin/*.php` |
| Content write / delete / upload | `src/Files/FileManager.php`, `src/Files/PathValidator.php` |
| Indexes (search, backlinks, tags) | `src/Files/SearchIndex.php`, `src/Files/BacklinkIndex.php`, `src/Files/TagService.php` |
| Security review | `src/Core/App.php`, `src/Markdown/HtmlSanitizer.php`, `src/Core/Csrf.php`, `docs/security.md` |
| Release / updater | `src/Core/Version.php`, `tools/build-manifest.php`, `src/Admin/UpdateService.php`, `docs/development.md` |
