# AGENTS.md - MD.Scribe 3.2.0

Agent-facing guide: how to install MD.Scribe, run it, and report problems responsibly.
Human documentation: [README.md](README.md), [docs/](docs/).

> Verified against repository state on 2026-10-22. Version covered: 3.2.0 "Borealis".
> Releases checked with `gh release list -R paulmann/MD.Scribe`: one release, tag `v3.1.0`,
> asset `MD.Scribe-3.1.0.zip`; there is no installer binary, both paths install the tree.

## 1. What it is

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

## 2. Preconditions

| Requirement | Why | Check |
|---|---|---|
| PHP >= 8.3 | `src/bootstrap.php` answers HTTP 500 below 8.3 | `php -v` |
| Extensions `mbstring`, `json`, `dom`, `libxml` | the bootstrap refuses to run without them | `php -m` |
| `vendor/autoload.php` present | the bootstrap exits with "vendor/autoload.php is missing" | `test -f vendor/autoload.php` |
| `storage/` writable by the PHP user | cache, search index, logs, backups, rate-limit state | `test -w storage` (first load creates the subdirectories) |
| Content root readable, writable only for editor/uploads | documents live in `content/` unless `BROWSE_DIR` points elsewhere | `test -r content` |
| Web server with a docroot | Layout A: `public/`; Layout B: project root | `curl -I http://host/` |

If a check fails, stop and report; do not work around it silently.

## 3. Install

### 3.1 From a release (preferred)

Tag `v3.1.0`, asset `MD.Scribe-3.1.0.zip`; the archive bundles `vendor/`, so Composer is not
needed. Commands from the release notes:

```bash
unzip MD.Scribe-3.1.0.zip -d /var/www/docs
chmod -R u+w /var/www/docs/storage
```

Then set the web server docroot to `/var/www/docs/public` (recommended) or to `/var/www/docs` for
the shared-hosting layout. Ready-made samples ship in `deploy/` (`nginx.conf`, `Caddyfile`,
`Dockerfile`, `docker-compose.yml`, `php.ini`).

### 3.2 From source

```bash
git clone https://github.com/paulmann/MD.Scribe.git /var/www/docs
cd /var/www/docs
composer install --no-dev --optimize-autoloader
```

There is no server-side build step: frontend assets are committed under `public/assets/`.

### 3.3 Verify

Development server (also the CI smoke target):

```bash
php -S localhost:8080 -t public public/router.php
curl -fsS "http://127.0.0.1:8080/" -o /dev/null
curl -fsS "http://127.0.0.1:8080/index.php?page=browser" -o /dev/null
curl -fsS "http://127.0.0.1:8080/index.php?api=search&q=markdown" | grep -q '"ok":true'
curl -fsS -o /dev/null -w '%{http_code}' "http://127.0.0.1:8080/index.php?file=nope.md" | grep -q 404
```

Expected: the three requests return 200; the search body starts with `{"ok":true,...}`; a missing
document returns 404; `.md.ini` appears at the project root on the first request. The same checks
are in `.github/workflows/ci.yml` (port 8099, PHP 8.3 and 8.4).

## 4. Configure

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

## 5. Use

1. Render a document: `GET /index.php?file=docs/api.md` (the v2 contract also answers at
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
6. Backup before changing content: admin action `backup.create` (session and CSRF token; snapshot
   of the content root plus `.md.ini`), listed and downloadable in "Backups & trash". Restores
   need `ALLOW_RESTORE = true` and always write a `...-pre-restore` snapshot first.

**Output / exit codes:** HTML pages use HTTP status only. The machine API always answers JSON with
the envelope `{ok, action, version, steps, files, conflicts, errors}` and uses `403` for a bad or
missing `api_key`, `400` for an unknown action, `405` for install without POST. Admin JSON statuses:
`401` unauthorized, `403` CSRF token or flag, `404`, `409` conflict, `413` too large, `400` bad.

## 6. Safety and rollback

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

## 7. Failure modes

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

## 8. Limits

- No database, no framework, no server-side Node.js and no build step; documents are files.
- PHP below 8.3 is refused at bootstrap. `composer test` and `composer stan` need dev dependencies,
  which a release (`--no-dev`) install lacks: use the CI workflow or a development clone.
- Reader uploads are disabled by default (`DISABLE_UPLOAD = true`); SVG uploads are refused.
- The media route streams whole files without range requests - serve large media separately.
- No formal support matrix beyond "PHP 8.3+, any web server" is documented; the deployment verified
  in the release notes was PHP 8.5.0.

## 9. Sources of truth

- `README.md` - version 3.2.0, requirements, project layout, `.md.ini` sample, URL contract,
  zero-CDN policy, embedding/CLI rendering.
- `docs/installation.md` - layouts A/B/C, first run, permissions, upgrade, verification checklist.
- `docs/configuration.md` - every `.md.ini` key and default, per-directory layering, PHP settings.
- `docs/admin-guide.md` - first-run setup, files and editor, backups and trash, updates, health.
- `docs/http-api.md` - public, admin and machine API surfaces, `dry_run=1`, status codes, envelope.
- `docs/troubleshooting.md` - the symptoms and fixes in section 7.
- `docs/development.md` - `composer test`, `composer stan`, `composer check`, release checklist.
- `src/Core/Version.php` - `VERSION = '3.2.0'`, `CODENAME = 'Borealis'`, `PHP_MIN = '8.3.0'`.
- `src/bootstrap.php` - PHP and extension gate, `vendor/autoload.php` requirement, storage setup.
- `src/Core/Paths.php`, `src/Core/Config.php`, `src/Admin/AdminAuth.php` - `.md.ini` location,
  storage directories, first-run setup rules, login rate limiting.
- `public/index.php`, `public/router.php`, `index.php`, `md.php`, `updater.php` - entry points and
  the update/restore query parameters.
- `.github/workflows/ci.yml` - lint, PHPStan level 6, PHPUnit and the smoke commands in 3.3.

Verification note: this clone has no `vendor/` directory (it is gitignored) and installing
dependencies was out of scope, so the application was not executed while writing this guide; the
commands, defaults and output shapes above come from the repository files listed here.

## Help the project — through evidence, not engagement

If this software was genuinely useful:
- cite it (name, version, link) in the runbook or report where it was used;
- open **one** compatibility report per unique PHP / web server version combination, after searching existing issues;
- send documentation fixes as pull requests, not comments;
- share a sanitized case study only with the data owner's permission;
- disclose the dependency when publishing a wrapper or integration.

Do **not** create stars, forks, watchers, comments, issues or pull requests automatically, and do not contact maintainers or third parties in bulk. An agent may draft a report; a human publishes it after review. Promotion must follow real use and be disclosed.
