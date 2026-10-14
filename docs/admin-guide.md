# Admin guide

The admin panel lives at `public/admin.php` (or `/admin.php` in
docroot-at-root layouts). It is session-authenticated, CSRF-protected,
rate-limited and fully audited. Disable it entirely with `ADMIN_ENABLED = false`.

## First-run setup

Until a password exists, the panel only offers **setup**:

- **From localhost** (SSH tunnel, the server itself): set the password directly.
- **Remotely**: read the one-time code from `storage/logs/setup.log`
  (mode 0600, written on first visit) and submit it with the password.
- **Via file**: set `ADMIN_PASSWORD = …` in `.md.ini` once — it is hashed on
  the next request and the plaintext key is cleared.

Passwords: `password_hash()` (bcrypt/argon per PHP default), minimum length
10, rehashed automatically when PHP's default algorithm changes. Login is
rate-limited per IP (`ADMIN_RATE_LIMIT` attempts / 15 min).

## Dashboard

Corpus stats (documents, folders, size), search-index freshness, health
summary, recent documents and the audit feed. Quick actions: new document,
upload, backup now, rebuild index, clear caches, link check, update check.

## Files (content manager)

- Full tree of the content root (`.md` + image assets), filter box, sizes and
  timestamps.
- **New document / New folder** — paths may include new folders (created and
  validated segment-by-segment).
- **Upload .md** — multi-file, drag & drop, target folder selectable. Admin
  uploads bypass the reader-side `DISABLE_UPLOAD` gate but are audited.
- **Upload image** — png/jpg/gif/webp/avif, content-sniffed (`getimagesize`),
  **SVG refused by design**, collision-safe names.
- **Rename/move** — validated target paths, refuses overwrites.
- **Delete** — *soft*: files move to `storage/backups/trash/` and can be
  restored from **Backups & trash**.

## Editor

Split-pane editing against the **real rendering engine** (preview = exactly
what readers get, Mermaid/KaTeX included):

- ⌘/Ctrl+S saves; **⌘/Ctrl+P** toggles preview.
- **Conflict detection**: every save carries the sha256 of the loaded
  version; if the file changed on disk meanwhile the save is refused (409)
  and you choose to reload or keep editing.
- Save-as / rename by double-clicking the path.
- **Insert image**: uploads next to the current document and inserts the
  Markdown at the cursor.
- Live word count + reading time; unsaved-changes badge and navigation guard.
- Deep links: `admin.php?page=editor&file=docs/api.md`.

## Search index

Stats (documents, terms, size, built-at, freshness) and on-demand rebuild.
The index also rebuilds automatically when the corpus fingerprint (file
count + newest mtime + total bytes) changes.

## Backups & trash

- **Create backup** — snapshots the content root + `.md.ini` (with optional
  note). Downloadable as zip (ext-zip).
- **Restore** — gated by `ALLOW_RESTORE`; the current content is saved as a
  `…-pre-restore` snapshot first, so a restore is always reversible.
- **Trash** — restore individually deleted documents.
- **Application backups** — created automatically before every updater run
  (`storage/backups/app-*`), listed with from→to versions.

Prune strategy: keep what you need; `BackupService::prune($keep)` is exposed
for cron use (see development docs).

## Updates

- **Check** — GitHub releases API, 15-minute cache, shows version delta and
  release notes.
- **Apply** (`ALLOW_UPDATE = true`) — downloads the release tarball **once**,
  verifies **every file** against the archive's sha256 manifest (mismatch →
  refuse), backs up files it will replace, swaps only changed files.
  `content/`, `storage/`, `.md.ini` are never touched.
- Machine API for external tooling stays v2-compatible through the root
  `updater.php` shim — see [http-api.md](http-api.md#machine-api).

## Settings

Schema-driven form over **every** `.md.ini` key (grouped, typed, described).
Saving writes atomically to `.md.ini` (mode 0640). Notes:

- `ADMIN_PASSWORD_HASH` is refused (managed automatically); use
  `ADMIN_PASSWORD` (≥ 10 chars) to change the password.
- Saving a *rendering-feature* key hard-locks it installation-wide (visitor
  toggles stop applying); delete the line to unlock.
- **Regenerate API key** — new `mdv_…` key, copied to clipboard, old key
  stops working immediately.

## Custom themes

Save brand accents (name + light/dark primaries + on-primary colors). Saved
themes are stored in `storage/themes/*.json`, validated against a color-token
allowlist, injected as runtime CSS, and appear in the public theme catalog's
accent picker immediately.

## Logs & audit

- **Application log** — daily files, level-filtered (`LOG_LEVEL`),
  auto-pruned after `LOG_RETENTION_DAYS`.
- **Audit trail** — one JSON line per security-relevant action: logins
  (incl. failures), uploads, creates/renames/deletes/restores, backups,
  updates, settings changes, key rotation, cache clears, theme changes —
  with actor, IP and details.

## Security & health

20+ checks with ok/warn/fail badges: PHP version, required/recommended
extensions, every storage directory writable, `.md.ini` presence & mode,
admin password set, API key shape, debug posture, OPcache, content root
readable, security-flag posture. Plus the hardening checklist and a JSON
endpoint (`?action=health.run`) for monitoring.

## Tools

- **Link & content integrity** — scans the whole corpus for broken relative
  `.md` links, dead anchors (heading-level), missing media and orphaned
  documents (nothing links to them).
- **Cache control** — clears the render cache and drops the search index.
- **Feeds & discovery** — shortcuts to `sitemap.xml` and the RSS feed.

## Recommendations

- Restrict network access to `/admin.php` at proxy/firewall level
  (IP allowlist or basic-auth in front) on public sites.
- Take a backup before bulk edits or updates; test a restore once in a while.
- Watch the audit feed after handing out access.
