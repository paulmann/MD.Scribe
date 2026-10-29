# HTTP API reference

Four API surfaces, all same-origin:

1. **Public JSON API** — `index.php?api=…` (reader features)
2. **Admin JSON API** — `admin.php?action=…` (session-authenticated)
3. **Machine API** — `updater.php?api_key=…&action=…&format=json` (v2-compatible)
4. **Machine render API** — `POST index.php?api=render` + `X-Api-Key` (stateless)

Conventions: responses are `application/json; charset=utf-8`, `no-store`,
`nosniff`; failures carry `{ok: false, error: "…"}` plus a meaningful HTTP
status; all POSTs require the CSRF token (public: from
`window.MDV_CONFIG.csrf`; admin: `window.MDV_ADMIN.csrf`) — except the machine
render API, whose credential is the key.

## Machine render API (`POST ?api=render`, key-authenticated)

Renders one payload to an **HTML fragment** for another application: a portal
answer, an e-mail body, a comment window. Stateless by design — no session, no
CSRF token, no cookies, nothing written to disk. Since **3.2.2**.

```http
POST /index.php?api=render
X-Api-Key: mdv_<40 hex>
Content-Type: application/json

{"content": "# Заголовок\n\n- раз\n- два", "format": "auto"}
```

The credential is the `API_KEY` from `.md.ini` (generated on first load, format
`mdv_` + 40 hex characters). `X-Api-Key:` is the canonical way; `?api_key=…` is
accepted for tools that cannot set headers. Both work at the same time; the
header wins when both are present.

| `format` | Pipeline | Output |
| --- | --- | --- |
| `markdown` | `MdViewer\Standalone\Renderer`, embed mode | bare HTML fragment: `<h1>`, `<ul>`, `<table>`, `<pre><code class="hljs language-php">`; no `<html>`/`<head>`/`<body>`, no toolbars, no heading numbering, no auto-TOC |
| `html` | `MdViewer\Markdown\HtmlSanitizer` (DOM allowlist) | cleaned fragment: `<script>`, `on*` handlers, `javascript:` URLs and non-allowlisted tags are removed |
| `text` | `<pre>` + `htmlspecialchars()` | the payload shown verbatim, `<` and `&` escaped |
| `auto` (default) | detection | HTML when the content starts with a block tag from `p, div, h1–h6, ul, ol, table, pre, blockquote, section, article, details, hr, br, img, a, span, code, strong, em`; Markdown when any explicit Markdown construct is present (`#` heading, `-`/`*`/`1.` list, ```` ``` ```` fence, `>` quote, `**bold**`, `[text](url)`, `\|…\|` table, `---`); ties go to Markdown; plain text otherwise |

Success — `200`:

```json
{
  "ok": true,
  "data": {
    "format": "markdown",
    "html": "<h1 id=\"zagl...\">Заголовок</h1>\n<ul>\n<li>раз</li>\n<li>два</li>\n</ul>",
    "bytes": 96,
    "elapsed_ms": 1.87,
    "renderer": "MD.Scribe/3.2.2"
  }
}
```

- `bytes` — length of the returned `html` fragment in bytes (UTF-8).
- `elapsed_ms` — server-side rendering time (float, milliseconds).
- `html` is always a **fragment**, never a document: the caller supplies its own
  page and stylesheet (for example `github-markdown-css`). The Markdown branch
  emits no wrapper `div` — the fragment starts with the first rendered block.

Failures — `{ok: false, error: {code, message}}`:

| Status | `code` | When |
| --- | --- | --- |
| `401` | `bad_key` | no key, wrong key, or `API_KEY` not set in `.md.ini` |
| `422` | `empty_content` | `content` missing, not a string, or whitespace only |
| `422` | `bad_format` | `format` not one of `auto`, `markdown`, `html`, `text` |
| `413` | `too_large` | `content` above `262144` bytes (256 KiB) |

```json
{"ok": false, "error": {"code": "bad_key", "message": "invalid or missing API key"}}
```

Compatibility: the long-standing browser endpoint `POST ?api=render` (clipboard
preview, session + CSRF token, fields `markdown`/`dir`) is **unchanged**. The
only routing rule is the credential: a request without `X-Api-Key` and without
`?api_key=` takes the legacy branch, so an old client that sends neither sees
exactly the old behaviour (`403` `csrf` when its token is missing).

## Public JSON API

### `GET ?api=search&q=…&limit=50`

Full-text search (requires `FEATURE_SEARCH`).

```json
{
  "ok": true, "query": "sanitizer", "took_ms": 3, "total": 2,
  "results": [
    {"path": "security.md", "title": "Security model",
     "snippet": "…DOM-allowlist <mark>sanitizer</mark>…",
     "score": 380, "modified": 1791590000}
  ]
}
```

Snippets contain escaped text with `<mark>` highlights only.

### `GET ?api=suggest&q=…`

Typeahead for the command palette — documents and headings:

```json
{"ok": true, "results": [
  {"path": "docs/api.md", "title": "API", "heading": "Endpoints",
   "anchor": "endpoints", "kind": "heading"}
]}
```

### `GET ?api=toc&file=…` (file optional → home document)

```json
{"ok": true, "headings": [{"level": 2, "text": "Intro", "anchor": "intro"}]}
```

### `GET ?api=config`

Runtime configuration mirror of `window.MDV_CONFIG` (features, flags, urls).

### `POST ?api=render`

Renders pasted Markdown with the full engine (clipboard preview). Gated by
the per-directory `DISABLE_CLIPBOARD` flag.

| Field | Meaning |
| --- | --- |
| `markdown` | source text (≤ 4 MB) |
| `dir` | directory context (relative, validated) |

→ `{ok, html, title, headings, tocHtml, hasMermaid, hasMath, readingMinutes}`

### `POST ?api=save`

Saves pasted Markdown as a file. Gated by `DISABLE_SAVE_CLIPBOARD_TO_FILE`
(per-directory aware). Fields: `content`, `filename` (must end `.md`,
whitelist charset), `dir`. → `{ok, path}` or `403/400/413`.

### `POST ?api=upload` (multipart)

Reader upload; gated by `DISABLE_UPLOAD`. Field `file` + `dir`.
→ `{ok, path}`; collision-safe (`name-1.md`).

## Admin JSON API

All actions require an authenticated session; all POSTs require
`X-CSRF-Token` (or `_token`). Statuses: `401` unauthorized, `403` CSRF/flag,
`404`, `409` conflict, `413`, `400`.

| Action | Method | Payload → Result |
| --- | --- | --- |
| `login` / `logout` / `setup` | POST | credentials → `{ok, redirect?}` |
| `file.read` | GET | `path` → `{ok, content, hash, modified}` |
| `file.write` | POST | `path, content, hash?` → `{ok, path, hash}` (409 on hash mismatch) |
| `file.create` | POST | `path, content?` → `{ok, path}` (parents auto-created) |
| `file.rename` | POST | `from, to` → `{ok, path}` |
| `file.move` | POST | `from, to_dir` → `{ok, path}` |
| `file.delete` | POST | `path` → soft-delete to trash |
| `folder.create` / `folder.delete` | POST | `path` (delete requires empty) |
| `upload.md` / `upload.image` | POST multipart | `file, dir` → `{ok, path|url_path}` |
| `preview` | POST | `content` → `{ok, html, title}` |
| `trash.list` / `trash.restore` | GET/POST | `name` |
| `index.rebuild` / `index.stats` | POST/GET | → `{documents, terms, took_ms}` |
| `backup.create` | POST | `note?` → `{ok, id, files, bytes}` |
| `backup.list` / `backup.delete` | GET/POST | `id` |
| `backup.restore` | POST | `id` (needs `ALLOW_RESTORE`) → `{ok, files, pre_restore}` |
| `backup.download` | GET | `id` → zip stream |
| `update.check` | GET | `force=1?` → `{ok, current, latest, newer, notes, url}` |
| `update.status` | GET | manifest-vs-disk per tracked file |
| `update.apply` | POST | needs `ALLOW_UPDATE` → `{ok, from, to, replaced, unchanged, backup}` |
| `settings.set` | POST (JSON) | `{KEY: value, …}` → `{ok, applied[], errors[]}` |
| `settings.regenerate-key` | POST | → `{ok, api_key}` |
| `logs.tail` | GET | → `{logs[], audit[]}` |
| `health.run` | GET | → `{ok, version, checks[]}` |
| `links.check` | GET | → `{documents, broken_links[], broken_media[], orphans[], took_ms}` |
| `cache.clear` | POST | → `{ok, removed}` |
| `theme.save` / `theme.delete` | POST | custom accent CRUD |

## Machine API (v2-compatible)

Credential: `API_KEY` from `.md.ini`, passed as `?api_key=`. JSON only —
never HTML; PHP notices never leak into the body. `ALLOW_UPDATE` is **not**
required for the API (the key is the credential), matching v2 semantics.

```
GET  updater.php?api_key=<key>&action=status&format=json
GET  updater.php?api_key=<key>&action=conflicts&format=json
POST updater.php?api_key=<key>&action=install&format=json[&dry_run=1]
```

Envelope (unchanged from v2):

```json
{
  "ok": true, "action": "install", "version": "3.0.0",
  "steps":  [{"step": "install:release", "status": "ok", "detail": "3.0.0 → 3.1.0, 12 files"}],
  "files":  [{"path": "src/Core/App.php", "status": "present", "version": "3.0.0"}],
  "conflicts": [], "errors": []
}
```

- `status` — local install state per tracked file (`present | modified |
  missing`), no network I/O.
- `conflicts` — files that differ from the shipped manifest (dry-run report;
  never writes).
- `install` — verified release apply (POST only; `dry_run=1` stops after the
  report). HTTP codes: `200`, `400` unknown action, `403` unauthorized,
  `405` install-without-POST.

## Feeds

- `index.php?page=sitemap` — sitemap 0.9 XML of all documents (`lastmod`).
- `index.php?page=feed` — RSS 2.0, 30 most recently modified documents with
  descriptions.

Both honour `SITEMAP_ENABLED` / `RSS_ENABLED` and are cached (1 h / 15 min).
