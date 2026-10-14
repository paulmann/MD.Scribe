# Upgrade guide: v2 → v3

MD.Viewer 3 is a ground-up rewrite of the v2 monolith (`md.php` 4,700 lines,
`updater.php` 2,800 lines) into a structured PHP 8.3 application — with the
**same deployment spirit** (copy files, no database) and the **same URL and
config contracts** where they existed.

## TL;DR migration

1. Deploy v3 into a new directory (or replace files in place after a backup).
2. Move your `.md` tree into `content/`, or set `BROWSE_DIR = /path/to/docs`
   in `.md.ini` (absolute, DOCUMENT_ROOT-relative or project-relative — the
   v2 resolution order is preserved).
3. Carry over from your old `.md.ini`: `API_KEY` (keep it to preserve
   integrations), `BROWSE_DIR`, any `DISABLE_*`/`ALLOW_*` values, and any
   hard-locked feature keys.
4. Keep old links alive: the root `md.php` shim answers `md.php?file=…` and
   `&download=1` exactly like v2, and prefers `md.md` as home when present.
5. Open `admin.php`, run first-run setup, review **Security & health**.

## Contract-by-contract mapping

| v2 | v3 |
| --- | --- |
| `md.php` (everything) | `public/index.php` + root `md.php` shim |
| `updater.php` HTML UI | `public/admin.php?page=updates` (+ `updater.php` shim for `?update=true`, `?restore=…`, machine API) |
| `.md.ini` next to `md.php` | `.md.ini` at the project root (same file format, same strictness, same auto-append for installation keys) |
| Per-directory `.md.ini` (`DISABLE_*` layering) | identical semantics, unit-tested |
| `mdv_feat_*` cookie toggles | identical names & precedence |
| Settings panel (overlay) | full Settings **page** + admin Settings editor |
| `md.backup/<version>/` | `storage/backups/app-*` (updates) and `storage/backups/content-*` (content snapshots) |
| `uploads.md/` fallback dir | `BROWSE_DIR`/`content/` + per-directory targeting |
| Tailwind CDN / Mermaid CDN | local builds & vendored assets (zero CDN) |
| Hand-rolled parser (universal patterns, cypher) | league/commonmark + ported extensions (pattern chains kept) |
| Glossary tooltips (`tbl-N`, `gd-…`, `data-gterm`) | identical DOM contract — same JS behaviour |
| Machine API envelope `{ok, action, version, steps, files, conflicts, errors}` | identical envelope and verbs |
| `index.php` hard-link management | obsolete (a real root `index.php` ships); flag retained, no destructive action |

## Behaviour changes to know about

- **Raw HTML** is escaped by default now (`ALLOW_RAW_HTML` to opt in). v2
  allowed a curated subset inline; v3 sanitizes *everything* — stricter and
  more predictable.
- **Search** is real full-text (indexed) rather than a table filter.
- **Downloads** keep v2 semantics (opaque `application/octet-stream` +
  `nosniff` + RFC 5987 filenames).
- **Sorting** defaults stay `modified desc`, but every column is sortable
  client-side, and the choice persists per browser.
- **404s**: refused/missing files answer a styled 404 page (v2 answered the
  browser page with status 200 for some refusals — fixed).
- **Sessions**: a first-party session cookie now backs CSRF protection for
  the clipboard/upload APIs and the admin login. It carries no tracking and
  is HttpOnly/SameSite=Lax.

## If something breaks

- Old bookmark dead? Check the path still resolves under `BROWSE_DIR`
  (`admin.php?page=files` shows the tree the validator sees).
- Machine API integration? Same URL/verbs — if you get `403 unauthorized`,
  the key was regenerated (Admin → Settings → Secrets).
- Rollback: v3 never writes to your v2 directory; point the web server back.

## Deprecations

| Removed | Replacement |
| --- | --- |
| `js/md/*.js`, `css/md/*.css` (v2 assets) | `public/assets/{css,js}` (built, local) |
| `tools/*.ps1` (PowerShell tests) | PHPUnit suite (`composer test`) |
| v2 changelog-in-docblock style | `CHANGELOG.md` + docs/ |
