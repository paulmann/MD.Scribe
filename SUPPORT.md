# Support

MD.Scribe is self-hosted software: the documentation that ships with the version you run
is the first place to look, and the answer is usually already there. Work through the
sources below in this order.

## 1. The wiki

<https://github.com/paulmann/MD.Scribe/wiki> is the task-oriented guide: install the
release archive, configure the viewer, operate the admin panel, upgrade, and fix the
problems that come up on a real host. Start at the
[Home](https://github.com/paulmann/MD.Scribe/wiki/Home) page, which links every section.

## 2. The documentation in the repository

The files in `docs/` are the reference for the tag you run. They are versioned with the
code, so they match your release exactly.

| Page | Answers |
| --- | --- |
| [docs/installation.md](docs/installation.md) | deployment layouts, servers, Docker, permissions |
| [docs/configuration.md](docs/configuration.md) | all `.md.ini` keys, per-directory files, toggles |
| [docs/markdown-features.md](docs/markdown-features.md) | the full syntax matrix with examples |
| [docs/admin-guide.md](docs/admin-guide.md) | every admin page, workflow and the machine API |
| [docs/theming.md](docs/theming.md) | the theme catalog, tokens, custom themes |
| [docs/security.md](docs/security.md) | the threat model, the sanitizer, CSP, hardening checklist |
| [docs/http-api.md](docs/http-api.md) | the public, admin and machine JSON endpoints |
| [docs/troubleshooting.md](docs/troubleshooting.md) | symptoms and fixes for common failures |
| [docs/upgrade-from-v2.md](docs/upgrade-from-v2.md) | the v2 to v3 migration |
| [docs/development.md](docs/development.md) | architecture, build pipeline, tests, releases |

## 3. GitHub issues

When the documentation does not cover the problem, open an issue:

- **A defect** — use the [bug report form](.github/ISSUE_TEMPLATE/bug.yml). It asks for
  the version, the environment, the exact command and the output, because a self-hosted
  install cannot be reproduced without them.
- **A feature or a behaviour change** — use the
  [feature request form](.github/ISSUE_TEMPLATE/feature.yml).

Issues are for reproducible defects and concrete proposals. A question that the wiki or
`docs/` already answers is better served by improving that page: a pull request against
the paragraph that was unclear helps every later reader.

## 4. Security reports

Do not open an issue for a vulnerability. Use the private advisory channel described in
[SECURITY.md](SECURITY.md); the report stays visible only to the maintainer until a fix
ships.

## What a good report contains

1. **Version** — the release you installed (for example 3.6.1), not the branch name.
2. **Environment** — operating system, PHP version from `php -v`, web server and SAPI
   (mod_php, PHP-FPM, `php -S`, Docker), and whether the docroot is `public/` or the
   project root.
3. **The exact command or URL** — copy it, do not paraphrase it, and replace your host
   with `localhost` or `example.com`.
4. **The result** — the exit code and the output as text, with secrets removed, plus what
   you expected instead.
5. **What you already tried** — the documentation page you followed and the checks you
   ran; this is what turns a report into a fix.

Never paste an API key, an admin password or hash, a session cookie, a private key, a real
domain, an IP address or personal data. Replace it with `REDACTED`; a maintainer can ask
for the value through a private channel if it is genuinely needed.
