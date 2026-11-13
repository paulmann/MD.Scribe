# MD.Scribe — agent instructions

> Verified against repository state on 2026-11-13. Version covered: **3.3.0 "Lattice"**.
> This file has two parts: the developer contract (below) and the operator guide
> that shipped with 3.2.x, kept because it answers installation and failure-mode
> questions the developer sections do not repeat.
>
> GitHub state at the same date: tags `v3.1.0`, `v3.2.0`, `v3.2.1`; one release
> (`v3.1.0`); CI runs on pushes to `main` and on pull requests, on PHP 8.3 and 8.4.

## Role

Work as a **senior PHP engineer** on a framework-free, database-free application
that renders Markdown and manages the files it renders from. The role is not
generic "PHP work": this codebase loads four specific things onto it.

1. **The rendering pipeline.** league/commonmark 2.7 plus hand-written inline
   parsers and node renderers, a DOM allowlist sanitizer that runs on every
   render, and a render cache keyed by version + feature fingerprint + corpus
   stamp. One change here is visible in the HTML, in the cache and in the anchor
   namespace at the same time.
2. **The content layer.** Every path goes through one validator, every write is
   atomic, every delete is a move to trash, every save can leave a revision. The
   failure to prevent is silent loss or overwrite of somebody's document.
3. **The HTTP surface.** Two entry points, query-parameter routing, an admin JSON
   action API, a machine render API, CSRF on every mutation, session auth and a
   per-request CSP nonce. A new endpoint fits those, it does not bypass them.
4. **The release contract.** One version in three carriers, a checksum manifest
   the updater trusts absolutely, and an update path that refuses everything on
   the first mismatch.

What counts as failure here: a document lost; a path escaping the browse root; a
rendered page that silently loses an id, an attribute or a link; an index serving
the past while claiming to be current; a green run that examined nothing.

## Never break

1. **Path validation is central.** A request value reaches the filesystem only
   through `MdViewer\Files\PathValidator` (`src/Files/PathValidator.php:50,132,156,251`).
   No `realpath()`, no `basename()`, no hand-rolled traversal check.
2. **Writes are atomic.** temp file + `LOCK_EX` + `chmod 0644` + `rename`
   (`src/Files/FileManager.php:368-386`). Never rewrite a document in place.
3. **Escape on output.** Templates escape with `e()`, JSON goes through
   `Response::json`, inline scripts carry the CSP nonce
   (`src/View/Template.php:152`, `src/Core/App.php:168`).
4. **The sanitizer is an allowlist.** A new tag, attribute or `data-*` reaches the
   browser only after it is added to `HtmlSanitizer`
   (`src/Markdown/HtmlSanitizer.php:41,64,66,102`). `id` must match
   `/^[\p{L}\p{N}][\p{L}\p{N}_:.\-]{0,96}$/u` — Unicode on purpose, because
   heading anchors are slugified from the heading text.
5. **A rendering toggle is `'feature' => true`.** Only those enter the feature
   fingerprint, and only then does flipping the toggle invalidate cached HTML
   (`src/Core/FeatureFlags.php:38-48`, `src/Markdown/MarkdownEngine.php:180-190`).
6. **Cache correctness is correctness.** The key carries `Version::VERSION` and the
   corpus stamp (`src/Markdown/RenderCache.php:39-42`,
   `src/Http/Controllers/ViewerController.php:90-112`). A rendering change without
   a version bump serves yesterday's HTML.
7. **Admin mutations are POST + CSRF + authenticated,** applied centrally by the
   kernel (`src/Http/AdminKernel.php:57-65`). Do not add a route in front of it.
8. **`declare(strict_types=1)` in every `src/` file, PHPStan level 6 with no new
   `ignoreErrors` entries** (`phpstan.neon:29-32` forbids it in its own comment).
9. **Version = CHANGELOG = README.** All three carriers move in one commit;
   `tools/Invoke-ProjectDoctrine.ps1` compares them.
10. **`config/manifest.json` is generated, never hand-edited.** It is a release
    artefact of `tools/build-manifest.php`.

## Traps

| Trap | Evidence | Consequence |
|---|---|---|
| The render cache key uses the version string, not a code hash | `src/Markdown/RenderCache.php:39-42` | A renderer edit without a version bump is invisible to readers and to the cache |
| Wikilink existence depends on the whole corpus | `src/Http/Controllers/ViewerController.php:90-100` | Drop the corpus stamp and a newly created page keeps the "not created yet" marker |
| The sanitizer silently deletes what it does not recognise | `src/Markdown/HtmlSanitizer.php:41-109` | Unit tests pass, the browser path loses the attribute |
| Cyrillic and CJK heading ids were dropped until 3.3.0 | `src/Markdown/HtmlSanitizer.php:252-266` | In-page anchors and TOC links pointed at nothing; the fix widened `id` to Unicode |
| `ALLOW_IFRAMES` and `ALLOW_EXTERNAL_IMAGES` are outside the fingerprint | `src/Markdown/MarkdownEngine.php:185` | Toggle them, then clear the cache, or old HTML keeps the old policy |
| An admin page slug is registered in four places | `src/Http/AdminKernel.php:103,404,417,436` | A missed one silently renders the dashboard instead of an error |
| `Icons::svg()` falls back to `circle-alert` | `src/View/Icons.php:326` | A mistyped icon name looks like a deliberate warning; only the names in that map exist |
| The corpus stamp is path + mtime + size | `src/Files/CorpusStamp.php:20-32`, `src/Files/SearchIndex.php:286-299` | A same-size edit inside the same second is not indexed; rebuild from Tools |
| Templates are not strict and extract with `EXTR_SKIP` | `src/View/Template.php:94-98` | A data key colliding with a local is dropped without warning |
| PHPUnit fails on risky tests and on any output | `phpunit.xml:7-9` | An `echo` in a test fails the suite |
| The committed manifest is 3.1.0 while the code is 3.3.0 | `config/manifest.json:3` | `update.apply` refuses everything; regenerate before a release |
| PHPStan does not see `bin/`, `tools/` or `templates/` | `phpstan.neon:3-9` | A syntax error there passes analysis (CI still lints templates and tools; `bin/` is linted by neither) |

## Where to look

| Task area | Read first |
|---|---|
| Product behaviour, the rendering pipeline | `docs/AGENTS-context.md`, `src/Markdown/MarkdownEngine.php` |
| Adding an inline syntax or a renderer | `src/Markdown/Extensions/MdViewerExtension.php`, `src/Markdown/Extensions/WikilinkParser.php` (a complete 3.3 example) |
| Viewer page, navigation, anchors | `src/Http/Controllers/ViewerController.php`, `templates/viewer/document.php` |
| Admin page or JSON action | `src/Http/AdminKernel.php`, `templates/admin/tools.php` |
| Content writes, trash, uploads, revisions | `src/Files/FileManager.php`, `src/Files/RevisionService.php` |
| Indexes (search, backlinks, tags) | `src/Files/SearchIndex.php`, `src/Files/BacklinkIndex.php`, `src/Files/TagService.php` |
| Security review | `src/Core/App.php`, `src/Markdown/HtmlSanitizer.php`, `docs/security.md` |
| Release and updater | `src/Core/Version.php`, `tools/build-manifest.php`, `src/Admin/UpdateService.php`, `docs/development.md` |
| Documentation to keep in sync | `README.md`, `CHANGELOG.md`, `docs/` |

## Working agreement

1. Read `docs/AGENTS-context.md` for the layer you are touching; it names the file
   and line behind every invariant.
2. Find the closest existing precedent in the code and extend it. The 3.3.0
   features are the reference shapes: parser + renderer + node for syntax, a
   service with a lazy index for derived data, an admin action + template for UI.
3. Keep the diff minimal. No drive-by reformatting, no renaming for taste.
4. Name the trust boundary the change touches (path, output, auth, CSP) and the
   failure mode it must survive.
5. Update the three version carriers and any document the change makes stale.
6. Verify with the commands below; say plainly which ones you could not run.
7. Report unrelated defects separately instead of fixing them silently.

## Commands

```powershell
# dependencies (COMPOSER_HOME is required when APPDATA is unset)
$env:COMPOSER_HOME = Join-Path ([System.IO.Path]::GetTempPath()) 'composer-home'
php C:\tools\composer\composer.phar install

# the gates CI runs, plus the doctrine checker
php vendor\bin\phpstan analyse --memory-limit=1G --no-progress
vendor\bin\phpunit --no-coverage
pwsh -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File .\tools\Invoke-ProjectDoctrine.ps1

# HTTP smoke (the routes CI checks)
php -S 127.0.0.1:8099 -t public public/router.php
```

Baseline recorded on 2026-11-13 on PHP 8.5.12 / Windows: `tools/Invoke-ProjectDoctrine.ps1`
prints **0 ошибок, 0 предупреждений** (the manifest was regenerated for 3.4.2; before
that step the same run warned that the manifest was behind the code),
PHPStan is clean and PHPUnit runs 188 tests with no failures.

## Product and operations guide

The rest of this file is the operator guide that shipped with 3.2.x: how to
install, configure and run MD.Scribe, and what each failure looks like. Version
references inside it were updated to 3.3.0.

### 1. What it is

MD.Scribe is a self-hosted documentation platform: point it at a folder of `.md` files and it
serves a CommonMark/GFM viewer, a searchable library, themes, and an admin panel with an editor,
backups and a self-updater. It is a plain PHP application - no database, no framework, no
server-side Node.js, no build step - deployed by placing the tree on a PHP 8.3+ host. It is not a
hosted service and not a database-backed CMS: every document is a file on disk.

| | |
|---|---|
| Runtime / platform | PHP >= 8.3 with `mbstring`, `dom`, `libxml`, `json` (`curl`, `phar`, `zip`, `opcache` recommended); any web server (Apache, nginx, Caddy, LiteSpeed, `php -S`); no database |
| Entry points | `public/index.php` (viewer), `public/admin.php` (admin panel and admin JSON API); root `index.php`, `md.php`, `updater.php` for docroot-at-root installs and the v2 URL contract |
| Writes to | `.md.ini` at the project root (created on first load); `storage/` subdirectories `cache`, `backups`, `logs`, `index`, `state`, `themes`; documents and media under the content root when the editor or upload path is used; tracked app files only when an update is explicitly enabled and applied |
| Network access | All application assets are local (zero-CDN; CSP `default-src 'self'`). The only server-side outbound traffic is the updater checking or applying a GitHub release of `paulmann/MD.Scribe`; documents load external images only when `ALLOW_EXTERNAL_IMAGES` is true (the default) |

### 2. Preconditions

| Requirement | Why | Check |
|---|---|---|
| PHP >= 8.3 | `src/bootstrap.php` answers HTTP 500 below 8.3 | `php -v` |
| Extensions `mbstring`, `json`, `dom`, `libxml` | the bootstrap refuses to run without them | `php -m` |
| `vendor/autoload.php` present | the bootstrap exits with "vendor/autoload.php is missing" | `test -f vendor/autoload.php` |
| `storage/` writable by the PHP user | cache, search index, logs, backups, rate-limit state | `test -w storage` (first load creates the subdirectories) |
| Content root readable, writable only for editor/uploads | documents live in `content/` unless `BROWSE_DIR` points elsewhere | `test -r content` |
| Web server with a docroot | Layout A: `public/`; Layout B: project root | `curl -I http://host/` |

If a check fails, stop and report; do not work around it silently.

### 3. Install

#### 3.1 From a release (preferred)

Tag `v3.1.0`, asset `MD.Scribe-3.1.0.zip`; the archive bundles `vendor/`, so Composer is not
needed. Commands from the release notes:

```bash
unzip MD.Scribe-3.1.0.zip -d /var/www/docs
chmod -R u+w /var/www/docs/storage
```

Then set the web server docroot to `/var/www/docs/public` (recommended) or to `/var/www/docs` for
the shared-hosting layout. Ready-made samples ship in `deploy/` (`nginx.conf`, `Caddyfile`,
`Dockerfile`, `docker-compose.yml`, `php.ini`).

#### 3.2 From source

```bash
git clone https://github.com/paulmann/MD.Scribe.git /var/www/docs
cd /var/www/docs
composer install --no-dev --optimize-autoloader
```

There is no server-side build step: frontend assets are committed under `public/assets/`.

#### 3.3 Verify

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
are in `.github/workflows/ci.yml` (port 8099, PHP 8.3 and 8.4).

### 4. Configure

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

### 5. Use

1. Render a document: `GET /index.php?file=…` (the v2 contract also answers at
   `/md.php?file=...`); append `&download=1` for a source download.
2. Library and search: `GET /index.php?page=browser`, `GET /index.php?page=search&q=...`; JSON at
   `GET /index.php?api=search&q=...` (also `api=suggest`, `api=toc`, `api=config`).
3. First admin run: open `/admin.php`. Until a password exists the panel offers setup only - from
   localhost the new password alone is enough; remotely enter the one-time code from
   `storage/logs/setup.log` (mode 0600). The password must be at least 10 characters.
4. Health check: after login, `GET /admin.php?action=health.run` returns
   `{ok, version, checks[]}`; the panel shows the same checks on "Security & health". Every check
   should be green on a completed install.
5. Read-only update state (machine API, no network I/O): `GET /updater.php?api_key=$API_KEY&action=status&format=json`
   (also `action=conflicts`), where `$API_KEY` is the `API_KEY` value from `.md.ini`. The plain
   `updater.php` landing page does perform a release check over the network.
6. Machine render API (since 3.2.2, stateless): `POST /index.php?api=render` with `X-Api-Key: $API_KEY`
   (or `?api_key=$API_KEY`) and JSON `{"content": "# …", "format": "auto|markdown|html|text"}` →
   `{ok: true, data: {format, html, bytes, elapsed_ms, renderer}}`, where `html` is an HTML
   **fragment** (Markdown → standalone renderer in embed mode; html → sanitizer; text → wrapped `<div class="md-text">`).
   Statuses: `401 bad_key`, `422 empty_content`, `422 bad_format`, `413 too_large` (> 256 KiB).
   A `POST ?api=render` **without** a key keeps the legacy session+CSRF clipboard contract.
7. Backup before changing content: admin action `backup.create` (session and CSRF token; snapshot
   of the content root plus `.md.ini`), listed and downloadable in "Backups & trash". Restores
   need `ALLOW_RESTORE = true` and always write a `...-pre-restore` snapshot first.

**Output / exit codes:** HTML pages use HTTP status only. The machine API always answers JSON with
the envelope `{ok, action, version, steps, files, conflicts, errors}` and uses `403` for a bad or
missing `api_key`, `400` for an unknown action, `405` for install without POST. The machine render
API answers `{ok, data}` / `{ok: false, error: {code, message}}`. Admin JSON statuses:
`401` unauthorized, `403` CSRF token or flag, `404`, `409` conflict, `413` too large, `400` bad.

### 6. Safety and rollback

- Changes: writes `.md.ini` and the `storage/` tree on first load; content files and media when an
  admin edits or uploads (deletes are soft - files move to `storage/backups/trash/`); tracked app
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
  1. read-only: verify the entry points in 3.3 and the health check in 5;
  2. dry run: `POST` the machine API `install` action with `dry_run=1` - it reports what an update
     would replace and writes nothing;
  3. one object: take a backup (`backup.create`), then create or edit a single document;
  4. everything: bulk edits, then an update apply with `ALLOW_UPDATE = true` - only after 1-3 pass.
- Destructive steps, explicit: `GET /updater.php?update=true` replaces tracked app files (needs
  `ALLOW_UPDATE`); `GET /updater.php?restore=latest` or `?restore=app-YYYYmmdd-HHMMSS` rolls app
  files back (needs `ALLOW_RESTORE`); an admin restore overwrites content and `.md.ini`; deleting
  trash entries or backups is permanent. Never run these to see what happens; take a backup first.

### 7. Failure modes

| Symptom | Likely cause | Action |
|---|---|---|
| HTTP 500 "requires PHP >= 8.3.0" | older PHP | upgrade PHP; do not patch the gate |
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

### 8. Limits

- No database, no framework, no server-side Node.js and no build step; documents are files.
- PHP below 8.3 is refused at bootstrap. `composer test` and `composer stan` need dev dependencies,
  which a release (`--no-dev`) install lacks: use the CI workflow or a development clone.
- Reader uploads are disabled by default (`DISABLE_UPLOAD = true`); SVG uploads are refused.
- The media route streams whole files without range requests - serve large media separately.
- No formal support matrix beyond "PHP 8.3+, any web server" is documented; the deployment verified
  in the release notes was PHP 8.5.0.

### 9. Sources of truth

- `README.md` - version 3.3.0, requirements, project layout, `.md.ini` sample, URL contract,
  zero-CDN policy, embedding/CLI rendering.
- `docs/installation.md` - layouts A/B/C, first run, permissions, upgrade, verification checklist.
- `docs/configuration.md` - every `.md.ini` key and default, per-directory layering, PHP settings.
- `docs/admin-guide.md` - first-run setup, files and editor, backups and trash, updates, health.
- `docs/http-api.md` - public, admin and machine API surfaces, the machine render API
  (`POST ?api=render`), `dry_run=1`, status codes, envelope.
- `docs/troubleshooting.md` - the symptoms and fixes in section 7.
- `docs/development.md` - `composer test`, `composer stan`, `composer check`, release checklist.
- `src/Core/Version.php` - `VERSION = '3.3.0'`, `CODENAME = 'Lattice'`, `PHP_MIN = '8.3.0'`.
- `src/bootstrap.php` - PHP and extension gate, `vendor/autoload.php` requirement, storage setup.
- `src/Core/Paths.php`, `src/Core/Config.php`, `src/Admin/AdminAuth.php` - `.md.ini` location,
  storage directories, first-run setup rules, login rate limiting.
- `public/index.php`, `public/router.php`, `index.php`, `md.php`, `updater.php` - entry points and
  the update/restore query parameters.
- `.github/workflows/ci.yml` - lint, PHPStan level 6, PHPUnit and the smoke commands in 3.3.

Verification note: this clone has no `vendor/` directory (it is gitignored) and installing
dependencies was out of scope, so the application was not executed while writing this guide; the
commands, defaults and output shapes above come from the repository files listed here.

### Help the project — through evidence, not engagement

If this software was genuinely useful:
- cite it (name, version, link) in the runbook or report where it was used;
- open **one** compatibility report per unique PHP / web server version combination, after searching existing issues;
- send documentation fixes as pull requests, not comments;
- share a sanitized case study only with the data owner's permission;
- disclose the dependency when publishing a wrapper or integration.

Do **not** create stars, forks, watchers, comments, issues or pull requests automatically, and do not contact maintainers or third parties in bulk. An agent may draft a report; a human publishes it after review. Promotion must follow real use and be disclosed.
