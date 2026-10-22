# Standalone renderer (embedding MD.Scribe in another site)

MD.Scribe normally renders documents inside its own request lifecycle (session,
CSP nonces, theme cookies, search index). Sometimes only the **Markdown →
HTML** half is needed: a README viewer on a corporate site, a docs block in a
CMS, a static page generator, a bot that renders release notes. Since **3.2.0**
that pipeline is available without the front controller.

Two interfaces, one engine:

| Interface | Use it from |
| --- | --- |
| `MdViewer\Standalone\Renderer` (PHP class) | any PHP 8.3+ code with the MD.Scribe tree and `vendor/` available |
| `bin/md-scribe-render` (CLI) | shell scripts, cron jobs, static-site generators |

Both produce the same HTML as the viewer — CommonMark 0.31 + GFM tables,
task lists, footnotes, definition lists, GitHub alerts, KaTeX placeholders,
Mermaid blocks, attribute syntax, emoji, server-side syntax highlighting and
the DOM-allowlist sanitiser — minus the viewer chrome.

## Embed mode

`Standalone\Renderer` defaults to **embed mode**:

- fenced code blocks become portable
  `<pre><code class="hljs language-php">…</code></pre>` (no toolbar, no copy
  buttons, no wrapper `div`s), so an external stylesheet — for example
  `github-markdown-css` — can style them exactly like a GitHub README;
- automatic heading numbering and the auto-TOC are off (GitHub does not number
  README headings);
- download/copy controls are off.

Everything else stays on. HTML is always sanitised; `ALLOW_RAW_HTML` is
honoured from `.md.ini`.

## PHP

```php
require '/path/to/MD.Scribe/src/bootstrap.php';

$renderer = new \MdViewer\Standalone\Renderer();

// fragment (embed mode)
$result = $renderer->renderFile('/srv/repos/ragraf/README.md');
echo $result->html;                       // safe HTML fragment
$result->title;                           // first heading / frontmatter title
$result->headings;                        // [{level,text,anchor,number}, …]

// relative links and images resolved against the repository's subdomain
$result = $renderer->renderFileWithBase(
    '/srv/repos/ragraf/README.md',
    'https://ragraf.example.com/',
    '/srv/repos/ragraf',
);
echo $result->html;
```

Pass `['plainCodeBlocks' => false]` to `renderFile()` / `renderString()` to get
the viewer's code blocks with toolbar and copy buttons instead.

## CLI

```bash
# HTML fragment, embed mode
bin/md-scribe-render README.md

# complete page that loads the GitHub Markdown stylesheet
bin/md-scribe-render README.md \
  --html \
  --title="RAGRAF — README" \
  --base-url="https://ragraf.example.com/" \
  --css="https://example.com/readme.css" > readme.html

# machine-readable result
bin/md-scribe-render README.md --json | jq -r '.title'

# stdin
cat notes.md | bin/md-scribe-render - --title="Notes" --html
```

| Option | Meaning |
| --- | --- |
| `--html` | full `<!DOCTYPE html>` page instead of a fragment |
| `--json` | `RenderResult` as JSON (`html`, `headings`, `tocHtml`, `title`, `words`, flags) |
| `--toc` | reserved for the generated TOC block (`headings` are always returned) |
| `--title=TEXT` | page title for `--html` (default: document title) |
| `--base-url=URL` | resolve relative links/media against URL (implies a doc root of the file's directory) |
| `--css=URL` | stylesheet to link in `--html`; repeatable |
| `--lang=CODE` | `lang` attribute (default `en`) |
| `--viewer-mode` | keep viewer code blocks (toolbar, copy buttons, numbering) |
| `--strip-badges` | remove a README badge plate (shields/CI badges) in memory; the file is not modified |

Exit codes: `0` success, `1` read/render error, `2` bad arguments.

## Requirements

The same as the application: PHP ≥ 8.3 with `mbstring`, `dom`, `libxml`,
`json`, and `vendor/autoload.php` (`composer install --no-dev` or a release
archive). The CLI needs no web server, no session and no writable `storage/`
for rendering — only `.md.ini` readability; if it is missing, defaults apply.

## Badge plates

README files often start with a row of shields/CI badges. Those images are
served by third-party badge hosts and are frequently useless — or blocked — in
an embedded page. `stripBadges` (CLI: `--strip-badges`) removes badge-only
lines before rendering:

```php
$result = $renderer->renderFile('/srv/repos/x/README.md', ['stripBadges' => true]);
```

```bash
bin/md-scribe-render README.md --html --strip-badges
```

The cleanup happens in memory: the source file on disk is never rewritten. Only
lines that contain nothing but badge markup/links are dropped; prose that
happens to mention a badge URL is kept.

## Styling

MD.Scribe's own design system lives in `public/assets/css/`. For a GitHub-like
README page the recommended pair is
[github-markdown-css](https://github.com/sindresorhus/github-markdown-css)
(`.markdown-body` container) plus a highlight.js theme matching
`hljs-*` token classes. Alert callouts and quotes are rendered as
`.md-alert-*` / `.md-quote` elements and can be styled to taste (MD.Scribe's
`typography.css` contains a reference implementation).
