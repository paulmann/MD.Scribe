# Contributing to MD.Scribe

MD.Scribe is maintained by Mikhail Deynekin ([@paulmann](https://github.com/paulmann)).
Bug reports, documentation fixes, translations and code changes are welcome. This page
states what a pull request must contain, what has to run before it is reviewed, and the
conventions the repository already follows.

## Before you start

- **A defect.** Search the open and closed issues, then read
  [docs/troubleshooting.md](docs/troubleshooting.md). If the problem is new, open the
  [bug report form](.github/ISSUE_TEMPLATE/bug.yml).
- **A behaviour change or a new feature.** Open a feature request first. The project has
  no plugin API and a deliberately small runtime surface, so a change that looks local
  usually reaches the renderer, the sanitizer or the admin kernel.
- **A vulnerability.** Do not open an issue. Follow [SECURITY.md](SECURITY.md).

## Development setup

```bash
git clone https://github.com/paulmann/MD.Scribe.git
cd MD.Scribe
composer install
```

Runtime requirements: PHP 8.5 or newer with `mbstring`, `dom`, `libxml` and `json`
(`curl`, `phar` and `zip` are recommended for the updater and backup archives). There is
no database, no framework and no build step on the server.

Node.js is required only for the frontend pipeline. Run it when you change the Tailwind
sources, the JavaScript modules, the theme compiler or the icon set:

```bash
cd tools/frontend
npm install
node build.mjs
```

The output under `public/assets/` is committed. Regenerate it with `node build.mjs` and
commit the result in the same pull request; do not hand-edit files that the pipeline
writes. Production installs never need Node.js.

## Commands that must pass

Every command below must exit `0` before a pull request is reviewed.

| Command | What it proves |
| --- | --- |
| `composer install` | the development dependencies resolve on PHP 8.5 |
| `composer test` | the suite passes: 214 tests, 678 assertions, 1 skipped (2026-11-14) |
| `composer stan` | PHPStan level 6 is clean for `src/` and the entry scripts |
| `php -S localhost:8080 -t public public/router.php` | the application boots and serves its pages |

`composer check` runs PHPStan and PHPUnit in one step. If a test is skipped on your
machine, name it and say why in the pull request; a new skip is a finding, not a detail.

The frontend build is a separate gate and needs no special command beyond `node build.mjs`
above: a committed asset that does not match its source is a defect.

## Pull request rules

1. **One logical change per pull request.** A fix, its test and its documentation belong
   together; three unrelated fixes do not.
2. **New behaviour comes with a test.** Security-relevant behaviour needs a regression
   test that fails without the fix. Tests live in `tests/Unit` and `tests/Feature` and run
   against a throw-away project root, so they never touch real content or storage.
3. **No new runtime dependency without a discussion first.** This covers Composer
   packages, npm packages that end up in `public/assets/`, fonts and vendored JavaScript.
   Everything must work offline: the project ships zero CDN requests, and a dependency
   that fetches from the network at runtime will be rejected.
4. **No secrets and no real infrastructure.** No API keys, tokens, passwords, cookies,
   private keys, real hostnames, IP addresses, personal data or customer content. Use
   `example.com` and `localhost` in examples, and redact output before pasting it.
5. **Documentation ships with the change.** If behaviour, configuration keys, routes or
   the admin panel change, update the matching page in `docs/`, the README section and
   the `CHANGELOG.md` entry in the same pull request.
6. **Keep the diff reviewable.** Do not reformat files you are not changing, and do not
   mix whitespace churn with a behaviour change.
7. **Preserve the compatibility shims.** `md.php`, `updater.php` and the query-parameter
   routes carry the v2 URL contract; a change there needs a note in the pull request.

## Coding style

- `declare(strict_types=1);` in every PHP file; PSR-4 namespaces under `MdViewer\`.
- PHP 8.5 features are welcome: typed class constants, readonly promotion, enums for
  closed sets, `#[\Override]` where it applies.
- PHPStan level 6 must stay clean. Do not add a baseline entry or an inline ignore to
  silence a new error; fix the type instead.
- No `echo` outside templates and entry scripts. Services return data, controllers return
  a `Response`.
- Security-relevant code carries a comment explaining *why* and a test that exercises it.
- `bin/md-scribe-render` is outside PHPStan's paths by design. When you edit it, run
  `php -l bin/md-scribe-render` and one smoke run before opening the pull request.

## Documentation style

- Plain Markdown, one H1 per page, no emoji and no marketing adjectives.
- Wrap prose near 100 characters, tag every code fence with a language and keep tables
  rectangular.
- Link to the page in `docs/` instead of duplicating it, and use relative links inside
  the repository.
- Absolute machine paths, personal e-mail addresses and invented hostnames are not
  acceptable in any file.

## Language policy

English files are canonical. A translation is a separate file that carries a language
suffix: `README.ru.md`, `README.de.md`, `docs/configuration.ru.md`. Translate the English
file as it stands, update the translation in the same commit as the English change, and
never mix two languages in one file. The README lists the translations that are
maintained; a translation that has fallen behind its English source is a defect.

## Commit messages

Commits follow [Conventional Commits](https://www.conventionalcommits.org/):

```text
type(scope): imperative summary

Why the change is needed, and what it changes for a user. Wrap the body near
100 characters.
```

Types in use: `feat`, `fix`, `docs`, `test`, `refactor`, `perf`, `build`, `ci`, `chore`.
The summary is imperative, lowercase and at most 72 characters; the scope names the area
(`search`, `admin`, `markdown`, `security`, `deps`). Mark a breaking change with `!` after
the type or scope, and explain the migration in the body.

```text
feat(search): rank wikilink targets above body matches
fix(admin): reject uploads whose sniffed type is not an image
docs(security): record the CSP trade-off for inline styles
chore(deps): bump league/commonmark to 2.7.1
```

## Reporting a bug

Open the [bug report form](.github/ISSUE_TEMPLATE/bug.yml) and include:

- the MD.Scribe version you run (the release you installed, for example 3.6.0);
- the environment: operating system, PHP version (`php -v`), web server and SAPI
  (mod_php, PHP-FPM, `php -S`, Docker), and whether the docroot is `public/` or the
  project root;
- the exact command or URL that fails, with `localhost` or `example.com` in place of your
  host;
- the exit code, and the output with secrets removed;
- what you expected and what happened instead, and anything you already ruled out.

Paste output as text, not as a screenshot, so it can be searched. Never paste an API key,
session cookie, admin hash, real domain or personal data; if the output contains one,
replace it with `REDACTED` before submitting.

## Code of conduct

Participation is governed by the [Contributor Covenant 2.1](CODE_OF_CONDUCT.md). Report
unacceptable behaviour to the maintainer through GitHub, as described in that file.

## License

By contributing you agree that your contribution is licensed under the MIT license in
[LICENSE](LICENSE), the same terms as the project. Earlier releases (up to 3.3.0) shipped
under PolyForm Noncommercial 1.0.0; that does not apply to new contributions.
