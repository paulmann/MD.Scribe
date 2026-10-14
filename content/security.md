---
title: "Security model: defense in depth"
tags: [security]
---

# Security model: defense in depth

MD.Scribe treats every document as *untrusted input* and every request as
potentially hostile. Layers, from the outside in:

## 1. Path validation (14 layers)

Every `?file=`, `?dir=`, `?media=`, upload and admin path passes the same
validator — the hardened v2.18 lineage, unit-tested in v3:

1. length cap · 2. NUL byte · 3. control characters · 4. URL-decode + recheck
5. absolute paths · 6. traversal sequences (`..`) · 7. strict Unicode
whitelist (letters, marks, dashes — `\p{L}\p{M}\p{Pd}`) · 8. depth limit
9. `.md` extension · 10–12. realpath resolution + containment · 13. regular
file · 14. post-symlink extension recheck.

## 2. Output sanitization

Rendered HTML always passes a DOM-allowlist sanitizer:

- tags/attributes not on the allowlist are dropped (`on*` handlers die here);
- `href`/`src` schemes limited to http(s), mailto, tel, fragments, relatives
  and raster `data:` images;
- raw author HTML is **escaped by default** (`ALLOW_RAW_HTML` opts in — and
  even then it is sanitized);
- `<iframe>`/`<video>` require `ALLOW_IFRAMES`; SVG is inert-graphics-only;
- uploads: `.md`-only for readers, image types are content-sniffed, SVG is
  refused, and every served asset carries `X-Content-Type-Options: nosniff`.

## 3. Transport & headers

- Strict **CSP** with per-request nonces for inline scripts (`script-src
  'self' 'nonce-…'`), `object-src 'none'`, `frame-ancestors 'self'`;
- `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP;
- hardened session cookies (`HttpOnly`, `SameSite=Lax`, `Secure` auto);
- CSRF tokens on every mutating request.

## 4. Admin surface

- password login with `password_hash`, rate-limited per IP;
- audit log for every mutation (who / what / when / from where);
- `.md.ini` secrets never leave the server; the machine API requires
  `API_KEY` and answers JSON only;
- deletes are *soft* — files move to `storage/backups/trash/` first.

> [!CAUTION]
> No software replaces a maintained server. Keep PHP current, serve over
> HTTPS, and run **Admin → Security → Health** after every change.

---

[← Getting started](getting-started.md) · [Markdown reference →](docs/markdown-reference.md)
