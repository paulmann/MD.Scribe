# Development guide

## Architecture

```text
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
The stylesheet is built from three explicit sources (`templates`, `src`,
`public/assets/js`) because `@import "tailwindcss" source(none)` disables v4's
automatic repository scan: without it the word "invisible" in a changelog entry
added `.invisible` to the shipped CSS, so editing prose changed the bundle. CI
rebuilds with `--vendor` and **fails** on any drift from the committed
`public/assets` or `src/View/Icons.php` — as a non-blocking warning it let a
dependency bump merge with `package.json` ahead of the vendored files.

Icon workflow: add the lucide name to `tools/frontend/icons.json`, run
`generate-icons.mjs`, use `Icons::svg('name')` in PHP. Both generators
(`build.mjs --vendor` and `generate-icons.mjs`) must produce the same class —
only the second used to add the `circle-alert` fallback, so a vendor rebuild
left it out and every unknown icon name drew an empty `<svg>`.

## Rendering gate (browser)

The PHP suite cannot see the browser half of the pipeline: a server-side Mermaid
repair that never reached the renderer and an icon that silently became an empty
`<svg>` both shipped with 218 green tests. `tools/frontend/check-render.mjs`
closes that hole, and the CI job `render` runs it on every push.

```bash
php -S 127.0.0.1:8099 -t public public/router.php &
node tools/frontend/check-render.mjs --base=http://127.0.0.1:8099
```

21 checks per run: diagrams rendered (2 on `content/index.md`, 1 on
`content/docs/markdown-reference.md`), no failed diagram, no empty icon, KaTeX
nodes present with zero `.katex-error`, display math inside the column, zero CSP
violations, zero console/page errors, the table fits the column and wraps, the
Mermaid bundle matches the major `tools/frontend/package.json` asks for, the
theme switch re-renders with other colours, and — on a local server only — a
generated fixture proves that a repaired diagram reaches the renderer while a
broken one keeps its source visible.

It drives the system Chrome through **puppeteer-core**, not Playwright: every
check is structural (counts, error nodes, geometry), so pinning and downloading
a browser buys nothing. Set `CHROME_PATH` if Chrome is not in a standard place.

Negative test that proves the gate works: revert the loader to read
`data-mermaid-source` instead of the repaired `<pre>` body, rebuild, run the gate
— `FAIL repair: the repaired source reaches the renderer`, exit 1.

Emoji map: `src/Markdown/EmojiMap.php` is generated from the GitHub gemoji
database (`db/emoji.json`).

## Dependency policy — what is vendored, what is fetched, by whom

No host running MD.Scribe fetches anything from a package registry or a CDN at
runtime. The three dependency sets and their owners:

| Set | Installed | What reaches a host |
|---|---|---|
| PHP runtime — `league/commonmark`, `scrivo/highlight.php` | `composer install --no-dev` while the release archive is built | `vendor/` inside `MD.Scribe-<version>.zip` |
| PHP dev — `phpunit/phpunit`, `phpstan/phpstan` | `composer install` in a working copy | nothing |
| Frontend — mermaid, katex, lucide-static, Inter/JetBrains Mono, tailwind, esbuild | `npm install` in `tools/frontend`, development time only | the committed `public/assets/vendor/` (39 files) and `src/View/Icons.php` |

Consequences worth remembering:

- A dependency version reaches a user only through a commit — a Composer or npm
  install on the server is never part of an installation, and the docroot
  (`public/`) contains nothing that points outside the host
  (`Content-Security-Policy: default-src 'self'`, `src/Core/App.php`).
- The one outbound request the application knows how to make is the updater
  reading `Version::API_BASE` (`src/Admin/UpdateService.php:74`, called from the
  admin updates page — `src/Http/AdminKernel.php:543` — and from
  `action=update.check`, cached for 15 minutes). A failure is reported, never
  fatal: an installation without internet access simply sees "GitHub API
  unavailable". `ALLOW_UPDATE` (default `false`, `src/Admin/UpdateService.php:189`)
  gates *applying* an update, not the check; nothing else in the tree opens a
  socket.
- Dependabot (`.github/dependabot.yml`) watches the three ecosystems above, once
  a month, at most two open pull requests each. It is a maintenance reminder,
  not a delivery channel: a `public/assets/vendor/` change is a rendering change,
  so it needs `node build.mjs --vendor` and a page check before merge, and a
  `composer.json` bump of a runtime package needs the release archive rebuilt
  and `config/manifest.json` regenerated (release checklist step 5).

## Backend tooling

```bash
composer test             # PHPUnit (234 tests, 839 assertions)
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

1. Bump `src/Core/Version.php` (`VERSION`, `CODENAME`).
2. `cd tools/frontend && node build.mjs --vendor` — refresh assets.
3. `composer check` (with dev deps) — must be green, plus
   `pwsh -File tools/Invoke-ProjectDoctrine.ps1` (baseline: 12 checks, 0 errors,
   0 warnings) and the browser gate
   `node tools/frontend/check-render.mjs --base=http://127.0.0.1:8099`
   (21 checks) against a running `php -S`.
4. Build the artifact with one command:

   ```powershell
   php tools/build-release.php --version=<version> --out=<dir outside the repository>
   ```

   It clones the release commit (`--no-hardlinks`), installs runtime dependencies
   only (`composer install --no-dev --optimize-autoloader`), so the archive ships
   `vendor/` and an install needs no Composer on the host; regenerates
   `config/manifest.json` **inside that clone**, because the manifest must describe
   the tree it travels with; checks that `src/Core/Version.php`, the manifest and
   `--version` agree; re-verifies every manifest entry against its file; and writes
   a flat `MD.Scribe-<version>.zip` plus `MD.Scribe-<version>.sha256`.

   It refuses to build and names the file when anything is off: a dirty working
   tree without `--commit`, a file changed after the manifest was built, a manifest
   entry whose file is missing, or a shipped file the manifest never mentions.

   Two wall-clock dependencies are pinned to the commit time (the manifest's
   `generated` field through `SOURCE_DATE_EPOCH`, and every file's mtime, which the
   zip records), so **two builds of one commit are byte-identical** — measured
   before the pin: same size, different sha256. `--dry-run` reports what would be
   written and writes nothing; `--verify-only=<dir>` re-checks a tree against its own
   manifest.

   The committed `config/manifest.json` therefore describes the tree that was
   released, and does not have to be regenerated as the tree moves on: every build
   carries the manifest generated inside its own clone. Regenerate it in the working
   tree only when you want `status()` of this checkout to be meaningful.
5. Update `CHANGELOG.md` and the README badges; commit.
6. Tag `v<version>`, create the GitHub **release** and attach the archive — the
   updater downloads the release asset, not the branch tarball, because a branch
   tarball never contains `vendor/`.
7. `php tools/verify-release.php --version=<version>` — verifies the published
   artifact end to end (digest, manifest, a booted copy, the six routes, the
   browser gate). Run it **after** step 6, because it downloads the release
   asset: this is the check that tells a release from a hypothesis.
8. **Deploy that archive and re-run the checks before announcing anything**:
   `mdscribe-deploy <version> --dry-run`, then `mdscribe-deploy <version>`.
   The script verifies its own result on the live URL and rolls back by itself.
   A release published before this step is a hypothesis, not a release; three
   defects in the 3.4.x line were found only here, and the 3.5.0 deploy lost
   `vendor/` to a manual `rsync --delete`.

## Verified deployment

Three tools exist so a deploy cannot quietly succeed at the wrong thing:

| Tool | Where | What it proves |
|---|---|---|
| `tools/verify-release.php` | any machine with PHP 8.5 + Node | the published archive is the manifest's tree, and that tree serves pages: the digest the release publishes, every entry's sha256, nothing unexpected, `php -S` on a free port, six routes, the browser gate pointed at it with `--root` |
| `tools/deploy/mdscribe-deploy` | the host | the switch happened and the live site reflects it: staged manifest check, atomic symlink swap, opcache reset, manifest + six routes + version on the live URL, automatic rollback |
| `tools/deploy/verify-tree.php` | the host (`/usr/local/lib/mdscribe/`) | one tree against its own manifest — used after the switch and on demand |

```bash
php tools/verify-release.php --version=3.6.1    # artifact level, anywhere
mdscribe-deploy 3.6.1 --dry-run                  # placement level, on the host
mdscribe-deploy 3.6.1                            # install, verify, roll back on failure
mdscribe-deploy --status | --rollback | --install-tools
```

The layout it creates once, after a `tar` backup of the old tree:

```
<site>/mdscribe                -> mdscribe.releases/<version>   (symlink, swapped with mv -T)
<site>/mdscribe.releases/<v>/  the tree exactly as published
<site>/mdscribe-data/          content/ storage/ .md.ini — the only runtime state
```

Runtime state lives outside the release so a new version never copies it and a
rollback never loses a page written after the switch. Releases up to 3.4.x wrap
the tree in `MD.Scribe-<version>/`; 3.5.0 is flat, and the script accepts both.

### Three things this host taught us

1. **A git archive has no `vendor/`.** The 2026-11-14 deploy ran
   `rsync -a --delete` from `git archive` without excluding `vendor`: 731 files
   disappeared and every page served a PHP fatal. The script installs only the
   release archive and refuses a tree without `vendor/autoload.php`.
2. **opcache keys scripts by the path it was given, not by the resolved target.**
   `public/mdscribe/index.php` stayed cached under that name with `ts=0`, so
   pointing the symlink at another release kept serving the old bytecode: the
   site reported 3.4.4 for minutes while the link was already on 3.5.0, and
   `systemctl reload php8.5-fpm` does not clear the shared segment. The script
   calls `opcache_reset()` through a one-shot endpoint in the site docroot
   (random name, token, removed immediately) instead of restarting a pool it
   shares with 25 other sites.
3. **An archive built by `zipfile` carries no POSIX modes.** `unzip` applied the
   host umask and produced `0666` on every file — PHP code writable by any local
   account. The script normalises directories to 0755, files to 0644, and keeps
   shebang files executable.

### Code outside static analysis

`bin/md-scribe-render` (the standalone CLI renderer) is not in PHPStan's `paths`
and not in the CI lint list. That is a deliberate exclusion with a price:

- it is a user-facing entry point that runs outside the request lifecycle, so it
  was kept out of the analysis of `src` + the web entry scripts;
- the price is paid by hand — run `php -l bin/md-scribe-render` before editing it
  and a smoke run after (`bin/md-scribe-render --html README.md > /tmp/out.html`);
- the doctrine checker does not cover it either, so a syntax error there reaches
  a release and only fails at runtime.

Everything else — `src`, `public`, `templates`, `tools`, `tests` and the three
root scripts — is linted in CI, and `src` is analysed at level 6.

## Coding standards

- `declare(strict_types=1)` in every file; PHP 8.5 features welcome
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
