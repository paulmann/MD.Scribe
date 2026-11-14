# Security policy

MD.Scribe serves Markdown documents to browsers from a single PHP process. It treats every
document as untrusted input and every request as potentially hostile. This page describes
which versions receive fixes and how to report a problem; the full threat model, the
control-by-control reasoning and the hardening checklist live in
[docs/security.md](docs/security.md).

## Supported versions

| Version | Supported | Notes |
| --- | --- | --- |
| 3.6.x | Yes | current line (3.6.2 released 2026-11-14) |
| 3.5.x | No | previous line; upgrade to 3.6.x to receive fixes |
| 3.4.x and earlier | No | upgrade to 3.6.x to receive fixes |

Security fixes are released as patch versions on the current line. Older lines are not
maintained: a report against one is answered with "upgrade and retest" unless the defect
also reproduces on 3.6.x.

## Reporting a vulnerability

Use GitHub's private reporting channel:
<https://github.com/paulmann/MD.Scribe/security/advisories/new> ("Report a vulnerability"
on the repository's Security tab).

- **Do not open a public issue or a pull request that describes the defect.** A public
  report exposes every unpatched installation.
- If the form is not available to you, contact the maintainer
  ([@paulmann](https://github.com/paulmann)) on GitHub and say only that you have a
  security report; send the detail through the private channel it opens.
- Test only against an installation you own, and use `localhost` or `example.com` in any
  example you paste.

A report is confirmed and fixed as fast as a single-maintainer project allows; there is no
paid support contract and therefore no response-time guarantee. The advisory thread is
where status is reported, and the fix is published with the release notes that name the
affected versions.

## What to include

- The affected version (for example 3.6.2) and whether the defect also reproduces on the
  latest release.
- The deployment shape: docroot at `public/` or at the project root, PHP version, web
  server and SAPI, and the relevant non-default `.md.ini` keys (`ALLOW_RAW_HTML`,
  `ALLOW_EXTERNAL_IMAGES`, `ADMIN_ENABLED`, `DISABLE_UPLOAD`).
- The request or interaction that triggers the defect, with parameters and headers, and
  the observed vs expected result.
- The impact you believe it has: what an attacker gains, and what they need first
  (network position, a reader account, an admin session, a document they can upload).
- A minimal proof of concept, and a suggested fix if you have one.
- Whether you want to be credited in the advisory.

Redact secrets, tokens, cookies, real hostnames, IP addresses and personal data before
pasting anything.

## What the project already defends against

- **Path handling.** A 14-layer `PathValidator` runs on every path input: length, NUL and
  control bytes, URL-decoding, absolute and UNC paths, `..`, a strict character
  whitelist, depth, the `.md` extension, realpath containment, and regular-file plus
  post-symlink extension checks.
- **Rendered output.** Author HTML is escaped by default and unsafe link schemes are
  refused; a DOM-allowlist sanitizer then cleans every rendered document, dropping `on*`
  attributes, removing forms and scripts, and restricting SVG to inert graphics.
- **Browser policy.** A per-request-nonce Content-Security-Policy (`default-src 'self'`,
  no inline scripts, `object-src 'none'`, `frame-ancestors 'self'`), plus
  `X-Frame-Options`, `nosniff` and `noindex` on admin responses.
- **Admin surface.** `password_hash`, per-IP login rate limiting, session regeneration,
  CSRF tokens (`hash_equals`) on every mutating request, `SameSite=Lax` cookies, audited
  mutations and sha256 conflict detection on saves.
- **Secrets.** `.md.ini` sits outside the docroot or is denied by server rules; the API
  key and the password hash are never rendered; the machine API answers JSON only.
- **Update integrity.** The release tarball is verified per file against its sha256
  manifest, and a single mismatch refuses the whole update.

Details, including the CSP trade-off for inline styles and the update path:
[docs/security.md](docs/security.md).

## What the project does not do

- **No telemetry.** Nothing about an installation, its documents or its visitors is
  collected, counted or sent anywhere. There is no analytics code, no crash reporter and
  no license check.
- **No network calls while serving a page.** The viewer and the admin panel make no
  outbound request during a normal page load. The only outbound connections are the
  update check and the update download, both started by an administrator from the admin
  panel.
- **No CDN and no third-party assets.** Tailwind output, JavaScript, Mermaid, KaTeX,
  fonts, icons and the emoji database are served from the installation itself; the CSP
  enforces `default-src 'self'` so a third-party script could not run even if one were
  injected.

## Out of scope

- Vulnerabilities in third-party packages (CommonMark, highlight.php, Mermaid, KaTeX,
  Tailwind): report them upstream, and tell the maintainer so the dependency can be
  bumped.
- Misconfiguration that `docs/installation.md` and the hardening checklist in
  [docs/security.md](docs/security.md) already warn about, for example a docroot at the
  project root without working `.htaccess` denials.
- Reports that require an already-compromised host or an authenticated administrator
  session to be useful on their own.
