# Troubleshooting

## The page shows “Something went wrong”

- Check `storage/logs/app-YYYY-MM-DD.log` — every unhandled error is logged
  with file/line.
- Set `APP_DEBUG = true` in `.md.ini` temporarily for on-page stack traces
  (disable again on public servers).

## “vendor/autoload.php is missing”

The release archive bundles `vendor/`. From a bare git clone run
`composer install --no-dev --optimize-autoloader` in the project root.

## Assets 404 (no styles / no icons)

- Confirm you browse through an entry script (`/index.php`, `/md.php`,
  `/public/index.php`) — the app computes the asset prefix from its own URL.
- Docroot at project root? The root `.htaccess` must not block `/public/`
  (it only denies `src/`, `config/`, `storage/`, …).
- Behind a reverse proxy that rewrites paths: keep `SCRIPT_NAME` intact
  (fastcgi `SCRIPT_FILENAME`/`SCRIPT_NAME` params).

## 403/404 on `.md.ini` — good!

`.md.ini` must never be served. If it **is** downloadable, your server
ignores `.htaccess` — switch to the docroot-at-`public/` layout immediately.

## Admin panel

| Symptom | Fix |
| --- | --- |
| Login loops | Session cookie blocked (proxy stripping `Set-Cookie`, or `SameSite` mismatch behind cross-site framing) — serve same-site over HTTPS |
| “Too many attempts” | Wait out the 15-min window; raise `ADMIN_RATE_LIMIT` if a shared NAT office locks itself out |
| Forgot password | Set `ADMIN_PASSWORD = new-secret` in `.md.ini` — re-hashed on next load |
| Setup page loops | `storage/logs/setup.log` holds the one-time code; or run setup from localhost |
| Actions return 403 `csrf` | Stale tab — reload the page to fetch a fresh token |

## Rendering surprises

| Symptom | Cause / fix |
| --- | --- |
| My HTML shows as text | Default: raw HTML is escaped. `ALLOW_RAW_HTML = true` opts in (still sanitized) |
| Feature toggle does nothing | The key is hard-locked in `.md.ini` (shows `locked` in Settings). Remove the line to unlock |
| Mermaid diagram shows source + warning | Hover the amber “auto-repairs” badge for what was fixed; the parse error is shown in the bar. Copy the source, fix, paste back |
| Math shows raw TeX | `FEATURE_MATH` off, or KaTeX assets blocked (check CSP/404s for `/assets/vendor/katex/`) |
| No syntax colors | Highlighting is server-side; a cached pre-3.0 render? Clear caches (Admin → Tools) |
| Chinese/Japanese search misses | Index is CJK-bigram based — ensure the index rebuilt after adding docs (Admin → Search index) |
| Whole site unstyled — huge logo on a black page | A `types { }` block replaced the inherited MIME table, so every `.css`/`.js` is served as `application/octet-stream`. Remove the `types` block and map the one extension with `location ~ \.webmanifest$ { default_type application/manifest+json; }` — see `docs/installation.md` |
| Manifest served as `application/octet-stream` | `.webmanifest` is not in `mime.types`; use the per-location `default_type` above, never a `types` block |
| Wide table cut off at the column edge | Stylesheets before 3.4.4 drew tables at `width: max-content` with `nowrap` headers; upgrade — tables now fit the reading column and wrap |
| Anchor links to non-Latin headings do nothing | Before 3.3.0 the sanitiser dropped ids starting with a non-ASCII letter; upgrade |

## Uploads

- Reader uploads need `DISABLE_UPLOAD = false` (base **or** per-directory
  `.md.ini`), and land in the directory being viewed.
- `413` — file exceeds `MAX_UPLOAD_MB` **or** PHP's `upload_max_filesize` /
  `post_max_size` (both must allow it).
- SVG uploads are refused everywhere by design.

## Performance

- Enable OPcache; set `opcache.validate_timestamps = 0` in production.
- Large libraries: keep `MAX_SCAN_DEPTH`/`MAX_FILES_SCAN` sane; the search
  index makes search independent of corpus size at query time.
- Images: the media route streams whole files (no range requests) — keep
  content images reasonable, or serve big media from a CDN in front.

## Updating

| Symptom | Fix |
| --- | --- |
| “The PHP zip extension is required” | `php -m | grep zip`; install `php8.5-zip` (Debian/Ubuntu) or the matching package for your build. Backups and the self-updater both use it |
| “Integrity check failed for X” | Release archive did not match its manifest — refused by design; retry later or update from a fresh archive manually |
| Update button missing | `ALLOW_UPDATE = false` → enable in `.md.ini` |
| After a bad update | `updater.php?restore=latest` (needs `ALLOW_RESTORE = true`) or restore an `app-*` backup from Admin → Backups |
| Update applied, but the footer still shows the old version | opcache, not the files — see below |
| After updating, every file shows as “modified” | The manifest did not travel with the update. Fixed in this release: `apply()` now installs the archive's `config/manifest.json`. On an older tree, run `php tools/build-manifest.php` **in the release tree**, or re-apply the update |

### “The page still shows the old version after a deploy”

This is the failure mode that cost real time on 14 Nov 2026: the release was in
place, `HTTP 200` came back, and the page still named the previous version.

The cause is that **opcache keys a script by the path it was asked for, not by the
target of the symlink**. With a layout like

```
public/mdscribe -> ../mdscribe/public      # unchanged across releases
mdscribe        -> mdscribe.releases/3.6.2 # this is what moves
```

…the request still asks for `public/mdscribe/index.php`, so the cached entry keeps
its old key (with `ts=0`, which means opcache has no timestamp to compare) and the
old bytecode keeps running. `systemctl reload php8.5-fpm` sends `SIGUSR2` and does
**not** clear the shared segment.

Measure it before changing anything:

```bash
php /usr/local/lib/mdscribe/verify-tree.php <site-path> --fingerprint   # what is on disk
php /usr/local/lib/mdscribe/page-fingerprint.php https://host/path/     # what is served
readlink -f <site-path>                                                 # where the symlink points
curl -sI https://host/path/ | grep -i x-powered-by                      # what PHP reports
```

If the tree fingerprint matches the release and `x-powered-by` disagrees with it,
the code on disk is right and the bytecode is stale. `mdscribe-deploy` clears
opcache through a one-shot token-scoped endpoint after every switch, so a deploy
or rollback never leaves this state. On a hand-made install, call
`opcache_reset()` once (a temporary page that runs it, then is deleted) or restart
the pool — a reload is not enough.

## Getting help

Open an issue with: PHP version (`php -v`), web server, deployment layout
(A/B/C), the relevant `storage/logs/app-*.log` lines, and steps to reproduce.
Redact `.md.ini` values (API key, hash) before posting.
