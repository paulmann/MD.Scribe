# Development guide

## Architecture

```
Request → public/index.php (or md.php shim)
        → App::boot()          config · logger · session · csrf · request
        → FrontController      query-parameter routing (works without rewrites)
        → Controllers          Viewer · Browser · Page · Api · Media · Feed
        → MarkdownEngine       frontmatter → preprocess → league/commonmark
                               → token restore → sanitize → annotate → glossary
        → RenderCache          sha256(source × mtime × feature fingerprint)
        → Template             plain-PHP templates + layout inheritance
        → Response             ETag/304 · CSP nonce · security headers
```

Admin mirrors this: `public/admin.php → AdminKernel → Admin services`.

### Module map (`src/`)

| Namespace | Responsibility |
| --- | --- |
| `Core` | App kernel, `Config` (+ per-dir layering), `FeatureFlags` (ini > cookie > default), `Request`/`Response`, `Session`, `Csrf`, `Logger`, `AuditLog`, `HttpClient`, `Paths`, `Version` |
| `Files` | `PathValidator` (the 14 layers), `MarkdownRepository` (scan/metadata), `SearchIndex` (inverted index), `Uploader`, `FileManager` |
| `Markdown` | `MarkdownEngine`, `SourcePreprocessor`, `HtmlSanitizer`, `TableAnnotator`, `GlossaryService`, `MermaidNormalizer`, `UniversalPattern`, `CodeHighlighter`, `ExcerptService`, `RenderCache`, `EmojiMap`*, `Extensions/*` (parsers, renderers, listeners) |
| `Http` | `FrontController`, `AdminKernel`, `Controllers/*` |
| `Admin` | `AdminAuth`, `BackupService`, `UpdateService`, `HealthCheck`, `LinkChecker` |
| `Theme` | `ThemeCatalog` (token composition + CSS generation) |
| `View` | `Template` (sections/layouts/partials), `Icons`* |

\* generated files — do not edit by hand.

### Key design decisions

- **Query-parameter routing** — runs on any host without rewrite rules;
  `.htaccess` ships pretty-URL-friendly rewrites, `public/router.php` covers
  `php -S`.
- **One ContextHolder per render** — league renderers stay stateless while
  document context (dir, features, URL builders) flows through a holder;
  converters are cached **per engine instance** per feature fingerprint.
- **Placeholder tokens** for constructs CommonMark can't express directly
  (`$$…$$` blocks, `[TOC]`) — the same technique v2 used for code blocks,
  fence-aware (CommonMark §4.5).
- **Sanitize-after-render** — one DOM pass cleans everything, including
  app-generated markup (which is written to survive the allowlist).
- **No client framework** — ~39 KB of vanilla ES modules (bundled by esbuild)
  implementing shadcn-style behaviours (dialogs, sheets, dropdowns, toasts,
  command palette) with full keyboard/a11y support.

## Frontend build (development time only)

```bash
cd tools/frontend
npm install
node build.mjs            # tailwind + typography.css + admin.css + esbuild + themes.css
node build.mjs --vendor   # additionally refresh mermaid/katex/fonts/icons
node generate-icons.mjs   # regenerate src/View/Icons.php from icons.json
php ../build-themes.php   # regenerate themes.css only
```

Outputs are **committed** to `public/assets/` — production needs no Node.js.

Icon workflow: add the lucide name to `tools/frontend/icons.json`, run
`generate-icons.mjs`, use `Icons::svg('name')` in PHP.

Emoji map: `src/Markdown/EmojiMap.php` is generated from the GitHub gemoji
database (`db/emoji.json`).

## Backend tooling

```bash
composer test             # PHPUnit (126 tests)
composer stan             # PHPStan level 6 (strict types everywhere)
composer check            # both
php tools/build-manifest.php   # regenerate config/manifest.json (release step)
```

Tests live in `tests/Unit` and `tests/Feature`; each runs against a
throw-away project root (`tests/TestCase.php`) — no test ever touches real
storage or content. Security behaviours (path traversal, XSS vectors, CSRF,
flags) have dedicated regression tests, including the historic v2 bugs
(Unicode filenames, em-dashes, the `<path>` spin, fence-length nesting).

## Release checklist

1. Bump `src/Core/Version.php` (`VERSION`).
2. `cd tools/frontend && node build.mjs --vendor` — refresh assets.
3. `php tools/build-themes.php` (runs inside build.mjs).
4. `composer install --no-dev --optimize-autoloader` — slim vendor for shipping.
5. `composer check` (with dev deps) — must be green.
6. `php tools/build-manifest.php` — regenerate `config/manifest.json`
   (runtime deps only; dev packages are excluded automatically).
7. Update `CHANGELOG.md` and README badges.
8. Tag + publish; the release archive should contain everything except
   `storage/*` contents, `tools/frontend/node_modules` and `.md.ini`.

## Coding standards

- `declare(strict_types=1)` in every file; PHP 8.3 features welcome
  (typed class constants, readonly promotion, `#[\Override]` where apt,
  enums for closed sets, `json_validate()`).
- PSR-12-ish formatting; PSR-4 namespaces under `MdViewer\`.
- No `echo` outside templates/entry scripts; services return data,
  controllers return `Response`.
- Every user-facing string that can fail carries an actionable message.
- Security-relevant code gets a comment explaining *why*, and a test.

## Performance notes

- OPcache is the single biggest win (`validate_timestamps=0` in production).
- Documents answer `304` whenever possible; rendered HTML is cached per
  (source hash × feature fingerprint) in `storage/cache`.
- Search index is a single JSON file loaded once per request that needs it;
  rebuilds are fingerprint-gated.
- Mermaid/KaTeX JS loads only on pages that contain diagrams/math.
- The heaviest routine op is highlight.php on very large code blocks;
  rendering > 500 ms is logged as `slow markdown render`.
