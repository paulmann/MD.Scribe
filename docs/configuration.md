# Configuration reference — `.md.ini`

The installation-level configuration file lives **next to the project root**
(the directory containing `public/`). It is created automatically on first
load; missing installation-level keys are appended with safe defaults on
every load, so upgrading never requires manual edits.

Values are parsed strictly: trimmed, optional paired quotes stripped, and any
value containing a NUL byte, a newline, or more than 255 bytes is discarded
(with an error-log entry) rather than trusted.

Precedence for rendering features: **`.md.ini` (hard lock) → visitor cookie
(`mdv_feat_*`) → schema default**. Security and installation keys can never be
changed from the browser.

## General

| Key | Type | Default | Effect |
| --- | --- | --- | --- |
| `SITE_NAME` | string | `MD.Viewer` | Header, `<title>`, feeds |
| `HOME_DOC` | string | `index.md` | Document rendered at the site root (relative to browse root). Missing → library page |
| `BROWSE_DIR` | string | *(empty)* | Where documents live. Empty = bundled `content/`. Accepts absolute paths, `DOCUMENT_ROOT`-relative, or project-root-relative |

## Rendering features

These keys are **not written** to `.md.ini` automatically — defaults come from
`config/defaults.php` and visitors may toggle them per-browser (first-party
cookies, public Settings page). Writing one into `.md.ini` hard-locks it for
everyone (shown as <kbd>locked</kbd> in the UI).

| Key | Default | Effect |
| --- | --- | --- |
| `AUTO_TOC` | true | TOC sidebar / `[TOC]` generation |
| `AUTO_NUMBERING` | true | Hierarchical heading numbers (auto-off when the document numbers itself) |
| `AUTO_FOOTNOTES_LINKS` | true | Footnote backlinks |
| `SPLIT_TITLE_BY_COLON` | true | `Topic: Title` hero splitting |
| `PARAGRAPH_BREAK_STYLE` | `double-br` | `double-br` \| `paragraph` — spacing model |
| `DOUBLE_LINE_BREAKS` | true | Single newlines become `<br>` (legacy MD.Viewer behaviour) |
| `FEATURE_IMAGES` | true | Images (lazy, captions, lightbox hooks) |
| `FEATURE_REF_LINKS` | true | Reference-style links |
| `FEATURE_TASK_LISTS` | true | `- [ ]` checkboxes |
| `FEATURE_FOOTNOTES` | true | `[^1]` and inline `^[…]` footnotes |
| `FEATURE_SUBSUP` | true | `^sup^` / `~sub~` |
| `FEATURE_EMOJI` | true | `:shortcodes:` (gemoji, 1900+) |
| `FEATURE_HIGHLIGHT_MARK` | true | `==mark==` |
| `FEATURE_MATH` | true | `$…$` / `$$…$$` via local KaTeX (+mhchem) |
| `FEATURE_MERMAID` | true | ```mermaid blocks via local Mermaid |
| `FEATURE_ALERTS` | true | `> [!NOTE]` callouts |
| `FEATURE_DEFINITION_LISTS` | true | `Term` / `: definition` |
| `FEATURE_ATTRIBUTES` | true | `{#id .class key=val}` |
| `FEATURE_SMART_PUNCT` | false | Curly quotes, en-dashes, ellipses |
| `GLOSSARY_TOOLTIPS` | true | Glossary-table tooltips |
| `UNIVERSAL_PATTERNS` | true | v2 pattern-chain code spans |

## Interface

| Key | Default | Effect |
| --- | --- | --- |
| `FEATURE_DOWNLOAD_ICON` | true | Per-row download in the library |
| `FEATURE_DOWNLOAD_BUTTON` | true | Toolbar download |
| `FEATURE_COPY_BUTTON` | true | Toolbar “copy source” |
| `FEATURE_SEARCH` | true | Search page + APIs |
| `FEATURE_COMMAND_PALETTE` | true | Ctrl+K palette |
| `FEATURE_LIGHTBOX` | true | Click-to-zoom images |
| `FEATURE_READING_PROGRESS` | true | Header progress bar |

## Content & uploads

| Key | Default | Effect |
| --- | --- | --- |
| `DISABLE_UPLOAD` | **true** | Reader-side “Upload .md” (admin uploads are separate) |
| `DISABLE_CLIPBOARD` | false | Clipboard preview |
| `DISABLE_SAVE_CLIPBOARD_TO_FILE` | **true** | Saving pasted content as a file |
| `MAX_UPLOAD_MB` | 8 | Cap for reader/admin uploads (also bounded by PHP ini) |

## Security & admin

| Key | Default | Effect |
| --- | --- | --- |
| `ADMIN_ENABLED` | true | Master switch for `/admin.php` (404 when false) |
| `ADMIN_USERNAME` | `admin` | Login name |
| `ADMIN_PASSWORD` | *(empty)* | One-shot bootstrap: hashed into `ADMIN_PASSWORD_HASH` on next load, then cleared |
| `ADMIN_PASSWORD_HASH` | *(empty)* | `password_hash()` digest — managed by the panel |
| `ADMIN_RATE_LIMIT` | 10 | Failed logins per IP per 15 minutes before lockout |
| `API_KEY` | auto | Machine-API credential (`mdv_` + 40 hex chars) |
| `ALLOW_UPDATE` | false | Apply updates (`?update=true`, admin panel) |
| `ALLOW_RESTORE` | false | Restore backups |
| `ALLOW_CREATE_INDEX_PHP_LINK` | true | v2 compatibility flag (no destructive action in v3) |
| `ALLOW_RAW_HTML` | false | Keep sanitized author HTML (default: escaped) |
| `ALLOW_IFRAMES` | false | Permit `iframe/video/audio/embed` |
| `ALLOW_EXTERNAL_IMAGES` | true | Permit `http(s)` image sources |
| `SECURITY_HEADERS` | true | nosniff / frame / referrer / permissions / COOP headers |
| `SECURITY_CSP` | true | Content-Security-Policy with per-request nonces |
| `TRUSTED_PROXIES` | *(empty)* | CSV of proxy IPs allowed to set `X-Forwarded-*` |

## Performance

| Key | Default | Effect |
| --- | --- | --- |
| `CACHE_ENABLED` | true | Server-side render cache (`storage/cache`) |
| `CACHE_TTL` | 3600 | `Cache-Control: max-age` for rendered docs |
| `MAX_SCAN_DEPTH` | 8 | Scanner/validator depth limit |
| `MAX_FILES_SCAN` | 10000 | Scanner file cap |
| `SEARCH_MAX_RESULTS` | 50 | Search page cap |

## Feeds & appearance

| Key | Default | Effect |
| --- | --- | --- |
| `SITEMAP_ENABLED` | true | `?page=sitemap` |
| `RSS_ENABLED` | true | `?page=feed` (30 newest) |
| `THEME_DEFAULT` | `zinc` | First-visit preset (catalog ids in `config/themes.php`) |
| `MODE_DEFAULT` | `system` | First-visit color mode: `light` \| `dark` \| `system` |
| `SORT_DEFAULT_KEY` | `modified` | Library default sort: `name` \| `size` \| `modified` |
| `SORT_DEFAULT_DIR` | `desc` | `asc` \| `desc` |

## Diagnostics

| Key | Default | Effect |
| --- | --- | --- |
| `APP_ENV` | `production` | `development` shows detailed error pages |
| `APP_DEBUG` | false | Debug logging + stack traces (never in production) |
| `LOG_LEVEL` | `warning` | `debug` \| `info` \| `warning` \| `error` |
| `LOG_RETENTION_DAYS` | 14 | Daily log pruning |

## Per-directory `.md.ini`

Any directory inside the browse root may carry its own `.md.ini`. The flags
that apply to a document are the base file with every `.md.ini` from the
browse root down to the document's directory applied on top — **deeper wins**:

```text
content/.md.ini           base (installation-level)
content/share/.md.ini     applies below share/
content/share/sub/.md.ini applies below share/sub/, wins over both
```

Only three keys are honoured per-directory (v2 semantics, unit-tested):

| Key | Effect in that directory |
| --- | --- |
| `DISABLE_UPLOAD` | whether reader uploads land here |
| `DISABLE_CLIPBOARD` | clipboard preview availability |
| `DISABLE_SAVE_CLIPBOARD_TO_FILE` | save-to-file availability |

Installation-level keys in a directory file are ignored and reported to the
error log. A directory file is only read when its realpath stays inside the
browse root. Uploads and clipboard saves always target the directory being
viewed (`dir` parameter, fully validated).

## Environment-level PHP settings

Recommended `php.ini` (ships in [`deploy/php.ini`](../deploy/php.ini)):

```ini
display_errors = Off
log_errors = On
session.cookie_httponly = 1
session.cookie_samesite = Lax
session.use_strict_mode = 1
opcache.enable = 1
opcache.validate_timestamps = 0   ; re-enable (or reset) when deploying code
upload_max_filesize = 16M
post_max_size = 17M
```
