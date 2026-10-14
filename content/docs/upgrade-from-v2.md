---
title: "Upgrading from MD.Viewer v2"
tags: [upgrade, migration]
---

# Upgrading from MD.Viewer v2

MD.Scribe v3 is a full rewrite — same philosophy (self-hosted, no database,
no build step on the server), modern architecture. This page maps every v2
behaviour to its v3 counterpart.

## What stays exactly the same

| v2 contract | v3 status |
| ----------- | --------- |
| `md.php?file=path/doc.md` | ✅ identical (via the root `md.php` shim) |
| `md.php?file=…&download=1` | ✅ identical bytes-as-attachment semantics |
| `.md.ini` keys (`DISABLE_*`, `ALLOW_*`, `API_KEY`, `BROWSE_DIR`) | ✅ all honoured, same names, same strict parsing |
| Per-directory `.md.ini` layering (deeper wins, `DISABLE_*` only) | ✅ ported and unit-tested |
| Machine API `updater.php?api_key=…&action=status\|conflicts\|install&format=json` | ✅ same verbs, same envelope `{ok, action, version, steps, files, conflicts, errors}` |
| `updater.php?update=true` / `?restore=latest` | ✅ same URLs, gated by the same flags |
| Filename whitelist incl. Unicode letters/marks/dashes (v2.16/v2.18 rules) | ✅ ported verbatim into `PathValidator`, with tests |
| Glossary table contract (`tbl-N`, `gd-…-r-c`, `data-gc`, `data-gterm`) | ✅ unchanged — v2 tooltips keep working |
| Cookie feature toggles `mdv_feat_*` | ✅ same names, same precedence (ini > cookie > default) |
| `md.md` auto-home behaviour of `md.php` | ✅ the shim prefers `md.md` when present |

## What changes

| v2 | v3 |
| --- | --- |
| One 4,700-line `md.php` | Structured app: `src/` (PSR-4), `public/`, `templates/`, `config/` |
| Hand-rolled Markdown parser | CommonMark + GFM (league/commonmark) + MD.Scribe extensions |
| Tailwind **CDN**, Mermaid **CDN** | Everything local — zero CDN requests, works offline/air-gapped |
| `updater.php` landing UI | Full **admin panel** (`public/admin.php`): dashboard, file manager, editor with live preview, backups + trash, updater, settings editor, logs, audit trail, health, link checker |
| Row filter in the browser table | Real **full-text search** + **Ctrl+K command palette** |
| Dark mode toggle only | Theme catalog: 12 presets, neutral × accent mixer, radius, font, light/dark/system with auto-detection |
| Per-file ETag-less responses | ETag + Last-Modified + 304 on documents and media, render cache |
| — | RSS feed, sitemap.xml, frontmatter (title/description/tags/draft/toc/numbering) |
| — | Math (KaTeX), GitHub alerts, definition lists, `==mark==`, attributes |

## Migration steps

1. **Back up** your v2 directory (or just keep it — nothing below touches your
   `.md` files).
2. Deploy v3 next to it. Copy your documents into `content/` (or point
   `BROWSE_DIR` at your existing tree — absolute paths work).
3. Copy the three interface keys from your old `.md.ini` if you had
   non-default values (`DISABLE_UPLOAD`, `DISABLE_CLIPBOARD`,
   `DISABLE_SAVE_CLIPBOARD_TO_FILE`, `API_KEY`, `ALLOW_*`). v3 appends any
   missing keys with safe defaults on first load.
4. Drop the v2-compatible `md.php` shim at the project root — old bookmarks
   and `?file=` links keep working immediately.
5. Open `admin.php`, run first-time setup (admin password), and check
   **Security & health**.

## Notes

- v2's `index.php` hard-link management is obsolete: v3 ships a real root
  `index.php`. The flag still exists for compatibility but performs no
  destructive action.
- The v2 updater's per-file backup folders (`md.backup/<version>/`) are not
  read by v3; v3 keeps application backups in `storage/backups/app-*` and
  content backups in `storage/backups/content-*`.
- `FEATURE_*` cookie names are unchanged, so readers keep their preferences.

---

[← Getting started](../getting-started.md) · [Security model](../security.md)
