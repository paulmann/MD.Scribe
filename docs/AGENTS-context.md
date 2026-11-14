# Project context: MD.Scribe 3.5.0 «Loom»

Deep reference for an agent working on this repository. Read `AGENTS.md` first;
read the code before asserting anything. Everything here was verified against
the tree at 3.5.0 — when a line number disagrees with the file, the file wins
and the disagreement is a documentation defect worth reporting.

Platform facts that changed in the 3.4 line: **PHP floor is 8.5** (anything below is
refused at bootstrap; verified on 8.5.0), the licence is **MIT**, and the web app
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

Work as a senior PHP engineer. The role is not generic PHP work; five things are loaded onto it:

1. **The rendering pipeline.** league/commonmark 2.7 plus hand-written inline parsers and node
   renderers, a DOM allowlist sanitizer that runs on every render, and a render cache keyed by
   version + feature fingerprint + corpus stamp. One change here is visible in the HTML, in the
   cache and in the anchor namespace at the same time.
2. **The content layer.** Every path goes through one validator, every write is atomic, every
   delete is a move to trash, every save can leave a revision. The failure to prevent is silent
   loss or overwrite of somebody's document.
3. **The HTTP surface.** Two entry points, query-parameter routing, an admin JSON action API, a
   machine render API, CSRF on every mutation, session auth and a per-request CSP nonce. A new
   endpoint fits those; it does not bypass them.
4. **The release contract.** One version in three carriers, a checksum manifest the updater trusts
   absolutely, and an update path that refuses everything on the first mismatch. `apply()` installs
   the release archive (which carries `vendor/`); `plan()` is the same read-only verification for a
   dry run.
5. **The install surface.** A PHP floor that is a gate, a licence, a PWA manifest served by the
   application, and a documentation set that the project's own checker reads. Anything a reader
   copies from the docs is code (`nginx` snippets included); a wrong snippet breaks a host, not a test.

What counts as failure here: a document lost; a path escaping the browse root; a rendered page that
silently loses an id, an attribute or a link; an index serving the past while claiming to be current;
a green run that examined nothing.

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
  `rename` (`src/Files/FileManager.php:370-387`). It supports optimistic
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
- **Releases:** `v3.5.0` (2026-11-14) is the current release, `v3.4.4`
  (2026-11-13) the previous one; both carry an archive asset. The archive's
  embedded `config/manifest.json` was verified against the archive contents
  before publishing. CI runs on branches and on the release commit.

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

CI runs on pushes to `main` and on pull requests. PHPStan analyses `src` + the five entry scripts
only — `bin/`, `tools/` and `templates/` are outside it. CI lints `src public templates tools tests`
and the three root scripts, but **not** `bin/md-scribe-render`. The doctrine checker also
enforces `declare(strict_types=1)` in every `src/` file and a PHPStan ratchet that
forbids new `ignoreErrors` entries (`phpstan.neon:33-34`).

Baseline at 3.5.0 on Windows (PHP 8.5.0): `php vendor\bin\phpunit --no-coverage`
reports `OK (218 tests, 689 assertions)`, exit 0; PHPStan level 6 is clean;
the doctrine checker reports `checks: 12, errors: 0, warnings: 0` (`PASS`). The three
checks added in 3.4.4 — the manifest media type, the table-layout rules (including
"both CSS copies are identical") and the ban on paste-ready `types { }` snippets in
the guides — were each proven by breaking the rule in a sandbox copy and watching
that check fail alone. Until 3.3.0 one assertion in `PathValidatorTest` compared a
native Windows path with a `/`-separated literal and failed on Windows only — a
test-portability defect that has since been fixed.

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
| Document tables at `width: max-content` with nowrap headers | `tools/frontend/src/css/typography.css` | A 736px column held an 896px table and the text was clipped mid-word. The rule is now `width: 100%` with wrapping cells; both CSS copies must stay identical (the build copies one onto the other) |
| A dry run that reads the local manifest examines the wrong object | `src/Admin/UpdateService.php` (`plan()` vs `status()`) | It answers "nothing to replace" whatever the release contains — a false PASS in product form |
| The updater installs the release archive, never a branch tarball | `src/Admin/UpdateService.php`, `docs/development.md` step 8 | A branch tarball lacks `vendor/`, so it can never satisfy the manifest and the update refuses itself |
| `vendor/composer/installed.php` records the build's git reference | `config/manifest.json` entry for that file | Rebuilding an old archive from `git archive` (no `.git`) changes one hash; build releases from a checkout |
| A manifest regenerated in the working tree lists dev vendor packages | `tools/build-manifest.php`, `composer.lock` | `--no-dev` and a dev install produce different `installed.php`; the release tree must be the one that is zipped |
| PHPUnit fails on risky tests and on output | `phpunit.xml:7-9` | An `echo` in a test fails the suite even when the assertion is right |
| `FileManager::write()` creates parent directories before validating the target | `src/Files/FileManager.php:131-137` | A rejected write can leave empty directories behind |
| Atomic writers `@`-suppress their filesystem errors | `src/Files/FileManager.php:375-379` | Permission or quota failures surface as a generic message |
| Root `.htaccess` only protects the docroot-at-root layout | `.htaccess:3-24`, `public/.htaccess:1-11` | With docroot at `public/`, `.md.ini` and `storage/` are safe only by being outside the docroot |

## 11. Open questions

| Question | How to settle it |
|---|---|
| Does the published `MD.Scribe-3.5.0.zip` still match the tree? | Compare its embedded `config/manifest.json` against one regenerated from the checkout |
| Is `bin/md-scribe-render` intentionally outside lint and PHPStan? | Decide, then either add it to `phpstan.neon` paths and the CI `find`, or document the exclusion |
| Does the `assets` CI job's drift warning ever fire in practice? | Run `node build.mjs` and compare `git diff --stat public/assets` |
| Should the updater ship a release archive instead of `main`? | Product decision; both failure modes are recorded in §8 |

## 12. Where to start reading

| Task | Read first |
|---|---|
| Rendering / Markdown behaviour | `src/Markdown/MarkdownEngine.php`, `src/Markdown/Extensions/MdViewerExtension.php`, `docs/markdown-features.md` |
| Adding an inline syntax or a renderer | `src/Markdown/Extensions/MdViewerExtension.php`, `src/Markdown/Extensions/WikilinkParser.php` (a complete example) |
| Viewer page, navigation, anchors | `src/Http/Controllers/ViewerController.php`, `templates/viewer/document.php` |
| Admin action or page | `src/Http/AdminKernel.php`, the matching `templates/admin/*.php` |
| Content write / delete / upload / revisions | `src/Files/FileManager.php`, `src/Files/PathValidator.php`, `src/Files/RevisionService.php` |
| Indexes (search, backlinks, tags) | `src/Files/SearchIndex.php`, `src/Files/BacklinkIndex.php`, `src/Files/TagService.php` |
| Search operators (`tag:`, `path:`) | `src/Files/SearchIndex.php` (`parseQuery`, `applyFilters`), `tests/Feature/SearchOperatorsTest.php` |
| Document templates, editor toolbar | `src/Files/DocumentTemplates.php`, `templates/admin/files.php`, `templates/admin/editor.php` |
| PWA, web manifest, offline reading | `src/Core/Manifest.php`, `src/Core/Response.php`, `public/sw.js`, `templates/layout/app.php` |
| Security review | `src/Core/App.php`, `src/Markdown/HtmlSanitizer.php`, `src/Core/Csrf.php`, `docs/security.md` |
| Release / updater | `src/Core/Version.php`, `tools/build-manifest.php`, `src/Admin/UpdateService.php`, `docs/development.md` |
| The project's own conventions | `tools/Invoke-ProjectDoctrine.ps1` (12 checks; every rule has a negative fixture) |
| Documentation to keep in sync | `README.md`, `CHANGELOG.md`, `docs/` |

## 13. Working agreement for changes

1. Read the section of this file (or the linked guide) for the layer you are touching; it names the
   file and line behind every invariant.
2. Find the closest existing precedent in the code and extend it. A syntax extension is parser +
   renderer + node; derived data is a service with a lazy index; admin UI is an action + template.
3. Keep the diff minimal: no drive-by reformatting, no renaming for taste.
4. Name the trust boundary the change touches (path, output, auth, CSP) and the failure mode it must
   survive.
5. Update the three version carriers and any document the change makes stale.
6. Verify with the commands in §9; say plainly which ones you could not run.
7. Report unrelated defects separately instead of fixing them silently.

## 14. Operator guide (kept from AGENTS.md)

The sections below were written for the 3.2.x line and are kept because they answer installation and
failure-mode questions the developer sections do not repeat. Version references were updated to 3.5.0.

### 14.1 What it is

MD.Scribe is a self-hosted documentation platform: point it at a folder of `.md` files and it serves
a CommonMark/GFM viewer, a searchable library, themes, and an admin panel with an editor, backups
and a self-updater. It is a plain PHP application — no database, no framework, no server-side
Node.js, no build step — deployed by placing the tree on a PHP 8.5+ host. It is not a hosted service
and not a database-backed CMS: every document is a file on disk.

| | |
|---|---|
| Runtime / platform | PHP >= 8.5 (verified on 8.5.0; older versions are refused at bootstrap) with `mbstring`, `dom`, `libxml`, `json` (`curl`, `zip`, `opcache` recommended); any web server (Apache, nginx, Caddy, LiteSpeed, `php -S`); no database; licence MIT |
| Entry points | `public/index.php` (viewer), `public/admin.php` (admin panel and admin JSON API), `public/updater.php` (bridge so `/updater.php` answers on a docroot-`public` install); root `index.php`, `md.php`, `updater.php` for docroot-at-root installs and the v2 URL contract |
| Writes to | `.md.ini` at the project root (created on first load); `storage/` subdirectories `cache`, `backups`, `logs`, `index`, `state`, `themes`; documents and media under the content root when the editor or upload path is used; tracked app files only when an update is explicitly enabled and applied |
| Network access | All application assets are local (zero-CDN; CSP `default-src 'self'`). The only server-side outbound traffic is the updater checking or applying a GitHub release of `paulmann/MD.Scribe`; documents load external images only when `ALLOW_EXTERNAL_IMAGES` is true (the default) |

### 14.2 Preconditions

| Requirement | Why | Check |
|---|---|---|
| PHP >= 8.5 | `src/bootstrap.php` answers HTTP 500 below 8.5 | `php -v` |
| Extensions `mbstring`, `json`, `dom`, `libxml` | the bootstrap refuses to run without them | `php -m` |
| `vendor/autoload.php` present | the bootstrap exits with "vendor/autoload.php is missing" | `test -f vendor/autoload.php` |
| `storage/` writable by the PHP user | cache, search index, logs, backups, rate-limit state | `test -w storage` (first load creates the subdirectories) |
| Content root readable, writable only for editor/uploads | documents live in `content/` unless `BROWSE_DIR` points elsewhere | `test -r content` |
| Web server with a docroot | Layout A: `public/`; Layout B: project root | `curl -I http://host/` |

If a check fails, stop and report; do not work around it silently.

### 14.3 Install

#### 14.3.1 From a release (preferred)

Tag `v3.5.0`, asset `MD.Scribe-3.5.0.zip`; the archive bundles `vendor/`, so Composer is not needed.
Commands from the release notes:

```bash
unzip MD.Scribe-3.4.4.zip -d /var/www/docs
chmod -R u+w /var/www/docs/storage
```

Then set the web server docroot to `/var/www/docs/public` (recommended) or to `/var/www/docs` for
the shared-hosting layout. Ready-made samples ship in `deploy/` (`nginx.conf`, `Caddyfile`,
`Dockerfile`, `docker-compose.yml`, `php.ini`).

#### 14.3.2 From source

```bash
git clone https://github.com/paulmann/MD.Scribe.git /var/www/docs
cd /var/www/docs
composer install --no-dev --optimize-autoloader
```

There is no server-side build step: frontend assets are committed under `public/assets/`.

#### 14.3.3 Verify

Development server (also the CI smoke target):

```bash
php -S localhost:8080 -t public public/router.php
curl -fsS "http://127.0.0.1:8080/" -o /dev/null
curl -fsS "http://127.0.0.1:8080/index.php?page=browser" -o /dev/null  # example
curl -fsS "http://127.0.0.1:8080/index.php?api=search&q=markdown" | grep -q '"ok":true'  # example
curl -fsS -o /dev/null -w '%{http_code}' "http://127.0.0.1:8080/index.php?file=nope.md" | grep -q 404  # example
# machine render API (key from .md.ini, generated on the first request)
curl -fsS -H "X-Api-Key: $API_KEY" -H 'Content-Type: application/json' \
  -d '{"content":"# t\n\n- a\n","format":"auto"}' \
  "http://127.0.0.1:8080/index.php?api=render" | grep -q '"ok":true'  # example
```

Expected: the three requests return 200; the search body starts with `{"ok":true,...}`; a missing
document returns 404; `.md.ini` appears at the project root on the first request. The same checks
are in `.github/workflows/ci.yml` (port 8099, PHP 8.5).

### 14.4 Configure

- Installation configuration is `.md.ini` in the project root (the directory that contains
  `public/`); it is created on first load and missing keys get safe defaults on every load.
- Minimal working file (keys from the README sample):

```ini
SITE_NAME = MD.Scribe
HOME_DOC  = index.md
BROWSE_DIR =
ADMIN_ENABLED = true
ADMIN_USERNAME = admin
ADMIN_PASSWORD =
ALLOW_UPDATE = false
ALLOW_RESTORE = false
DISABLE_UPLOAD = true
```

- `ADMIN_PASSWORD` is one-shot: on the next request it is hashed into `ADMIN_PASSWORD_HASH` and the
  plaintext key is cleared. `API_KEY` (format `mdv_` plus 40 hex characters) is generated on first
  load. `ALLOW_UPDATE` and `ALLOW_RESTORE` are off by default.
- Permissions (from `docs/installation.md`):

```bash
chown -R www-data:www-data storage
chmod -R ug+rwX storage
chmod 640 .md.ini
```

- PHP settings: `deploy/php.ini` is the recommended production posture (`display_errors = Off`,
  `session.cookie_httponly = 1`, `opcache.enable = 1`, `opcache.validate_timestamps = 0`).
- A `.md.ini` inside the browse root is a per-directory override that honours only
  `DISABLE_UPLOAD`, `DISABLE_CLIPBOARD`, `DISABLE_SAVE_CLIPBOARD_TO_FILE`; the deeper file wins.
- Every key, type and default: `docs/configuration.md`.

### 14.5 Use

1. Render a document: `GET /index.php?file=…` (the v2 contract also answers at
   `/md.php?file=...`); append `&download=1` for a source download.
2. Library and search: `GET /index.php?page=browser`, `GET /index.php?page=search&q=...`; JSON at
   `GET /index.php?api=search&q=...` (also `api=suggest`, `api=toc`, `api=config`, and `api=manifest`
   since 3.4.4).
3. First admin run: open `/admin.php`. Until a password exists the panel offers setup only — from
   localhost the new password alone is enough; remotely enter the one-time code from
   `storage/logs/setup.log` (mode 0600). The password must be at least 10 characters.
4. Health check: after login, `GET /admin.php?action=health.run` returns
   `{ok, version, checks[]}`; the panel shows the same checks on "Security & health". Every check
   should be green on a completed install.
5. Read-only update state (machine API, no network I/O):
   `GET /updater.php?api_key=$API_KEY&action=status&format=json` (also `action=conflicts`), where
   `$API_KEY` is the `API_KEY` value from `.md.ini`. The plain `updater.php` landing page does
   perform a release check over the network.
6. Machine render API (since 3.2.2, stateless): `POST /index.php?api=render` with
   `X-Api-Key: $API_KEY` (or `?api_key=$API_KEY`) and JSON
   `{"content": "# …", "format": "auto|markdown|html|text"}` →
   `{ok: true, data: {format, html, bytes, elapsed_ms, renderer}}`, where `html` is an HTML
   **fragment** (Markdown → standalone renderer in embed mode; html → sanitizer; text → wrapped
   `<div class="md-text">`). Statuses: `401 bad_key`, `422 empty_content`, `422 bad_format`,
   `413 too_large` (> 256 KiB). A `POST ?api=render` **without** a key keeps the legacy session+CSRF
   clipboard contract.
7. Backup before changing content: admin action `backup.create` (session and CSRF token; snapshot
   of the content root plus `.md.ini`), listed and downloadable in "Backups & trash". Restores
   need `ALLOW_RESTORE = true` and always write a `...-pre-restore` snapshot first.

**Output / exit codes:** HTML pages use HTTP status only. The machine API always answers JSON with
the envelope `{ok, action, version, steps, files, conflicts, errors}` and uses `403` for a bad or
missing `api_key`, `400` for an unknown action, `405` for install without POST. The machine render
API answers `{ok, data}` / `{ok: false, error: {code, message}}`. Admin JSON statuses:
`401` unauthorized, `403` CSRF token or flag, `404`, `409` conflict, `413` too large, `400` bad.

### 14.6 Safety and rollback

- Changes: writes `.md.ini` and the `storage/` tree on first load; content files and media when an
  admin edits or uploads (deletes are soft — files move to `storage/backups/trash/`); tracked app
  files only when `ALLOW_UPDATE = true` is set and an update is applied, after a backup to
  `storage/backups/app-*`; with an absolute `BROWSE_DIR`, editor and upload writes land there.
- Never does: create or migrate a database, install services or cron jobs, change the firewall,
  DNS, mail system or PHP configuration, or write outside the project tree and the configured
  content root.
- Undo: upgrades replace code directories while keeping `content/`, `storage/` and `.md.ini`. No
  uninstall procedure is documented; installation only adds the project tree plus runtime files, so
  the reverse is: keep a backup if needed, remove `.md.ini` and `storage/` (runtime state), and
  remove or restore the code directory. Keep `content/`.
- On a production host the first-run order is mandatory:
  1. read-only: verify the entry points in 14.3.3 and the health check in 14.5;
  2. dry run: `POST` the machine API `install` action with `dry_run=1` — it reports what an update
     would replace and writes nothing;
  3. one object: take a backup (`backup.create`), then create or edit a single document;
  4. everything: bulk edits, then an update apply with `ALLOW_UPDATE = true` — only after 1-3 pass.
- Destructive steps, explicit: `GET /updater.php?update=true` replaces tracked app files (needs
  `ALLOW_UPDATE`); `GET /updater.php?restore=latest` or `?restore=app-YYYYmmdd-HHMMSS` rolls app
  files back (needs `ALLOW_RESTORE`); an admin restore overwrites content and `.md.ini`; deleting
  trash entries or backups is permanent. Never run these to see what happens; take a backup first.

### 14.7 Failure modes

| Symptom | Likely cause | Action |
|---|---|---|
| HTTP 500 "requires PHP >= 8.5.0" | older PHP | upgrade PHP; do not patch the gate |
| HTTP 500 "requires the PHP extension 'X'" | extension not loaded | enable it in php.ini or the FPM pool |
| HTTP 500 "vendor/autoload.php is missing" | bare clone without dependencies | `composer install --no-dev --optimize-autoloader`, or use the release archive |
| "Something went wrong" page | unhandled error | read `storage/logs/app-YYYY-MM-DD.log`; set `APP_DEBUG = true` only temporarily and never on a public host |
| Styles or icons 404 | not reached through an entry script, or rewrite/docroot mismatch | browse `/index.php`; keep `SCRIPT_NAME` intact behind a proxy; Layout B needs the root `.htaccess` |
| `.md.ini` downloadable | server ignores `.htaccess` | switch to Layout A (docroot `public/`) immediately |
| "Too many attempts" or setup loops | per-IP rate limit (default 10 per 15 min) or first-run code | wait out the window, or read `storage/logs/setup.log` (0600) |
| Actions return 403 `csrf` | stale tab | reload the page to fetch a fresh token |
| Upload 413 | `MAX_UPLOAD_MB` or PHP `upload_max_filesize` / `post_max_size` | raise both; SVG uploads are refused by design |
| "Integrity check failed for X" | release archive does not match its manifest | refused by design - retry later or copy the files manually |
| Styles wrong or no syntax colours after a code change | OPcache with `validate_timestamps = 0` | restart or reload PHP-FPM |
| Whole site unstyled — huge logo on a black page | a `types { }` block replaced the MIME table, so css/js are `application/octet-stream` | remove the block; map the single extension instead (`docs/installation.md`) |
| `/updater.php` answers 404 | docroot is `public/` on a tree older than 3.4.1 | use the shipped `public/updater.php` bridge or the admin panel |

### 14.8 Limits

- No database, no framework, no server-side Node.js and no build step; documents are files.
- PHP below 8.5 is refused at bootstrap. `composer test` and `composer stan` need dev dependencies,
  which a release (`--no-dev`) install lacks: use the CI workflow or a development clone.
- Reader uploads are disabled by default (`DISABLE_UPLOAD = true`); SVG uploads are refused.
- The media route streams whole files without range requests — serve large media separately.
- No formal support matrix beyond "PHP 8.5+, any web server" is documented; the deployment verified
  for the current release ran on PHP 8.5.

### 14.9 Sources of truth

- The wiki (`https://github.com/paulmann/MD.Scribe/wiki`) is the task guide; `docs/` is the reference.
- `README.md` — version 3.4.4, requirements, project layout, `.md.ini` sample, URL contract,
  zero-CDN policy, embedding/CLI rendering.
- `docs/installation.md` — layouts A/B/C, first run, permissions, upgrade, verification checklist.
- `docs/configuration.md` — every `.md.ini` key and default, per-directory layering, PHP settings.
- `docs/admin-guide.md` — first-run setup, files and editor, backups and trash, updates, health.
- `docs/http-api.md` — public, admin and machine API surfaces, the machine render API
  (`POST ?api=render`), `dry_run=1`, status codes, envelope.
- `docs/troubleshooting.md` — the symptoms and fixes in 14.7.
- `docs/development.md` — `composer test`, `composer stan`, `composer check`, release checklist.
- `src/Core/Version.php` — `VERSION = '3.4.4'`, `CODENAME = 'Keystone'`, `PHP_MIN = '8.5.0'`.
- `src/bootstrap.php` — PHP and extension gate, `vendor/autoload.php` requirement, storage setup.
- `src/Core/Paths.php`, `src/Core/Config.php`, `src/Admin/AdminAuth.php` — `.md.ini` location,
  storage directories, first-run setup rules, login rate limiting.
- `public/index.php`, `public/router.php`, `index.php`, `md.php`, `updater.php` — entry points and
  the update/restore query parameters.
- `.github/workflows/ci.yml` — lint, PHPStan level 6, PHPUnit and the smoke commands in 14.3.3.

Verification note: the operator sections were written for the 3.2.x line and re-checked against the
3.4.4 tree on 2026-11-14. The 3.4.4 PHPUnit baseline (214 tests, 678 assertions, 1 skipped, superseded by 218 tests, 689 assertions) and the
doctrine checker (12 checks, 0 errors, 0 warnings) were measured in this clone with `vendor/`
installed; the HTTP examples were not re-executed for every status code, so treat them as documented
behaviour from the files named here.

## 15. Help the project — through evidence, not engagement

If this software was genuinely useful:

- cite it (name, version, link) in the runbook or report where it was used;
- open **one** compatibility report per unique PHP / web server version combination, after searching
  existing issues;
- send documentation fixes as pull requests, not comments;
- share a sanitized case study only with the data owner's permission;
- disclose the dependency when publishing a wrapper or integration.

Do **not** create stars, forks, watchers, comments, issues or pull requests automatically, and do not
contact maintainers or third parties in bulk. An agent may draft a report; a human publishes it
after review. Promotion must follow real use and be disclosed.
