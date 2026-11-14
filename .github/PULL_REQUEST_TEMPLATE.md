# Pull request

## What changed and why

<!-- One logical change per pull request. State the defect or the need, not only the diff. -->

Closes #

## How it was verified

<!-- Paste the commands you ran and their exit codes. Each one must exit 0. -->

```bash
composer install
composer test
composer stan
php -S localhost:8080 -t public public/router.php
```

## Checklist

- [ ] `composer test` passes (236 tests, 846 assertions) and exits `0`
- [ ] `composer stan` is clean at PHPStan level 6 and exits `0`
- [ ] The app boots with the `php -S` command above, and the changed page or endpoint was exercised
- [ ] One logical change; no unrelated formatting churn in the diff
- [ ] New behaviour has a test; a security fix has a regression test that fails without it
- [ ] No secrets, cookies, private keys, real hosts, IP addresses or personal data in the diff
- [ ] Docs in `docs/` and `CHANGELOG.md` updated, when behaviour or routes changed
- [ ] Translations (for example `README.ru.md`) updated here if English headings changed
- [ ] No new runtime dependency without a prior discussion; still offline, zero CDN
- [ ] Assets rebuilt with `node build.mjs` and committed, if `tools/frontend/` changed
- [ ] `php -l bin/md-scribe-render` and a smoke run, if that script was touched
