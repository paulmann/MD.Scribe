# Security model

MD.Scribe treats every document as **untrusted input** and every request as
potentially hostile. This page is the reference; the checklist at the bottom
is what to actually do.

## Threat model

| Threat | Mitigation |
| --- | --- |
| Path traversal to read arbitrary files | 14-layer validator (below) on **every** path input |
| Stored XSS via document content | HTML escaping by default + DOM-allowlist sanitizer on all output |
| XSS via uploads/filenames | `.md`-only, whitelist charset, nosniff, opaque attachment downloads |
| CSRF on mutating endpoints | Session tokens (`hash_equals`) on every POST, `SameSite=Lax` |
| Admin brute force | `password_hash` + per-IP rate limiting + session regeneration |
| First-run takeover | Setup gated by localhost **or** one-time code from `storage/logs/setup.log` (0600) |
| Secret leakage | `.md.ini` outside docroot (Layout A) / denied via `.htaccess` (Layout B); API key & hash never rendered; JSON-only machine API |
| Clickjacking / MIME sniffing | `X-Frame-Options`, CSP `frame-ancestors`, `nosniff` |
| Dependency attacks on update | Release tarball verified **per file** against its sha256 manifest; mismatch refuses the whole update |
| Data loss on delete/update | Soft deletes to trash; pre-update app backups; pre-restore content snapshots |
| SVG-borne script | Reader/admin image uploads refuse SVG; served SVG (content files) is attachment-only |
| ReDoS in glossary matching | Term cap (400) + single anchored alternation pattern |

## The 14-layer path validator

One class (`MdViewer\Files\PathValidator`), used by viewer, downloads, media,
uploads, per-directory ini resolution and the admin file manager:

1. length ≤ 255 · 2. NUL byte (checked **before** trim) · 3. control
characters · 4. URL-decode then re-check 2–3 · 5. absolute paths (Unix,
Windows, UNC) · 6. `..` traversal in any position · 7. strict whitelist
`[A-Za-z0-9._-/ space] + \p{L}\p{M}\p{Pd}` (Unicode letters, marks, dashes —
the v2.16/v2.18 rules, byte-identical) · 8. depth limit · 9. `.md` extension ·
10. base dir resolves · 11. target resolves · 12. **realpath containment**
inside base · 13. regular file (no devices/dirs) · 14. extension re-checked
**after** symlink resolution.

Media adds an extension allowlist (images, pdf, text-ish, zip, av). Every
rule has a unit test, including the historic v2 regressions (Cyrillic names,
em-dashes, `%2e%2e%2f`, doubled slashes).

## Output pipeline (why documents can't script)

1. Author raw HTML is **escaped** unless `ALLOW_RAW_HTML = true`
   (`html_input: escape`).
2. Unsafe link schemes are refused at parse time (`allow_unsafe_links: false`
   + renderer-level scheme checks).
3. **Every** rendered document passes the DOM-allowlist `HtmlSanitizer`:
   tag allowlist, attribute allowlists per tag, `on*` always dropped,
   scheme-checked `href/src`, `style` dropped (or scrubbed) unless raw mode,
   `id` pattern-checked, forms/inputs (except task checkboxes)/script/style/
   object/embed removed, SVG restricted to inert graphics (no `script`,
   `foreignObject`, `use`, `image`, no `href`), external images policy-
   enforced, buttons limited to the `md-` control namespace.
4. Client-side libraries are loaded from same-origin files only; CSP blocks
   everything else.

## Content-Security-Policy

```
default-src 'self';
script-src  'self' 'nonce-<per-request>';
style-src   'self' 'unsafe-inline';
img-src     'self' blob: data: [https: data: when ALLOW_EXTERNAL_IMAGES];
font-src    'self' data:;  connect-src 'self';
object-src  'none'; frame-ancestors 'self'; base-uri 'self'; form-action 'self';
```

- Scripts: **no inline scripts** except the two nonce'd bootstraps (theme +
  `MDV_CONFIG`). Third-party JS is impossible.
- Styles: `'unsafe-inline'` is a documented trade-off — Mermaid injects
  presentation styles into generated SVG as attributes/CSSOM. Style injection
  cannot execute code under `script-src 'self' 'nonce-…'`.
- `SECURITY_CSP = false` disables the header (e.g. behind a proxy that
  manages its own).

## Sessions & cookies

| Cookie | Purpose | Flags |
| --- | --- | --- |
| `MDVSESSION` | admin auth + CSRF + API session | HttpOnly, SameSite=Lax, Secure (auto on HTTPS), strict mode, idle timeout 1 h, regenerated on login |
| `mdv_feat_*` | visitor feature toggles | first-party, 1 y, SameSite=Lax; only after the visitor keeps “preference storage” on (opt-out switch on Settings) |
| `localStorage mdv.theme` / `mdv.prefs` | appearance | not cookies; no network effect |

No third-party requests exist anywhere in the app — verifiable via CSP and
the Network tab.

## Admin surface hardening

- Rate-limited login (default 10 attempts / 15 min / IP, files in
  `storage/state/`).
- Every mutation audited (`storage/logs/audit.log`, JSON lines).
- `X-Robots-Tag: noindex` on all admin responses; panel 404s when
  `ADMIN_ENABLED = false`.
- Editor saves use optimistic concurrency (sha256) — two admins can't
  silently clobber each other.
- Backups restore is double-gated (`ALLOW_RESTORE` + auth) and always takes a
  pre-restore snapshot first.

## Update integrity

`update.apply` → single tarball download over HTTPS → extract to a private
temp dir → **every** file's sha256 verified against the archive's
`config/manifest.json` → any mismatch refuses the entire update → current
files copied to `storage/backups/app-<ts>/` → atomic-ish per-file replace.
The v2 machine API (`updater.php?api_key=…`) keeps its key-as-credential
semantics; keys are 40-hex-char random and rotatable from the panel.

## Hardening checklist

1. Docroot at `public/` (Layout A). Failing that, verify `.htaccess` denials
   actually apply (`curl -I https://site/src/Core/App.php` → 403/404).
2. HTTPS everywhere; HSTS at the proxy.
3. `.md.ini` mode `640`, owned by the PHP user.
4. `storage/` writable by PHP, **not** by the world.
5. `DISABLE_UPLOAD = true` unless readers must upload.
6. `ALLOW_RAW_HTML = false` unless authors are fully trusted.
7. `ALLOW_UPDATE`/`ALLOW_RESTORE` off until needed; firewall `/admin.php`.
8. `APP_ENV = production`, `APP_DEBUG = false`, `display_errors = Off`.
9. OPcache on (`validate_timestamps = 0` + explicit reset on deploy).
10. Backups scheduled (cron + `BackupService`), restore tested once.
11. Rotate `API_KEY` after any suspected leak; watch the audit log.
12. Keep PHP patched; re-run **Admin → Security & health** after changes.
