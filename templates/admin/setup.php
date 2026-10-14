<?php
/**
 * MD.Scribe 3.0 — first-run setup (password not configured yet).
 *
 * Setup requires EITHER a request from localhost OR the one-time setup code
 * from storage/logs/setup.log (read it via SSH/FTP). This stops a stranger
 * who discovers /admin.php from claiming the installation.
 *
 * @var MdViewer\Core\App $app
 * @var bool $localhostAllowed
 */

use MdViewer\View\Icons;

$req = $app->request;
$nonce = $app->nonce();
$codeHint = $localhostAllowed ? '' : 'storage/logs/setup.log';
?>
<!doctype html>
<html lang="en" data-neutral="zinc" data-accent="default" data-radius="md" data-font="inter">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup · MD.Scribe Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e($req->assetUrl('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/themes.css')) ?>">
</head>
<body class="grid min-h-dvh place-items-center bg-muted/40 px-4">

<main class="w-full max-w-md">
  <div class="mb-6 text-center">
    <span class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-xl bg-primary text-primary-foreground shadow-lg"><?= Icons::svg('key-round', 'h-5 w-5') ?></span>
    <h1 class="text-xl font-bold tracking-tight">First-run setup</h1>
    <p class="mt-1 text-sm text-muted-foreground">Create the admin password to unlock the panel.</p>
  </div>

  <form id="md-setup-form" class="md-card p-6" method="post" novalidate>
    <?php if (!$localhostAllowed): ?>
    <div class="mb-4 rounded-lg border border-border bg-muted/50 p-3 text-xs text-muted-foreground">
      <p class="mb-1 font-semibold text-foreground">Remote setup requires the one-time code</p>
      <p>Read it on the server: <code class="md-code-inline"><?= e($codeHint) ?></code> (created on first visit; mode 0600).</p>
    </div>
    <div class="mb-4">
      <label class="md-label" for="setup-code">Setup code</label>
      <input class="md-input md-font-mono" id="setup-code" name="code" type="text" autocomplete="one-time-code" required>
    </div>
    <?php else: ?>
    <input type="hidden" id="setup-code" name="code" value="">
    <p class="mb-4 rounded-lg border border-border bg-muted/50 p-3 text-xs text-muted-foreground">
      <?= Icons::svg('info', 'h-3.5 w-3.5 inline-block align-[-2px] mr-1') ?>
      Localhost detected — no setup code needed. Remote administrators use the code in
      <code class="md-code-inline">storage/logs/setup.log</code>.
    </p>
    <?php endif; ?>

    <div class="mb-2">
      <label class="md-label" for="setup-pass">New admin password</label>
      <input class="md-input" id="setup-pass" name="password" type="password" autocomplete="new-password" required minlength="10">
      <p class="md-hint">At least 10 characters. Stored as a <code class="md-code-inline">password_hash()</code> digest in <code class="md-code-inline">.md.ini</code>.</p>
    </div>
    <div class="mb-4">
      <label class="md-label" for="setup-pass2">Repeat password</label>
      <input class="md-input" id="setup-pass2" name="password2" type="password" autocomplete="new-password" required minlength="10">
    </div>
    <p id="md-setup-error" class="mb-3 hidden text-sm text-destructive" role="alert"></p>
    <button type="submit" class="md-btn md-btn-primary w-full" id="md-setup-submit"><?= Icons::svg('check', 'h-4 w-4') ?>Create password &amp; continue</button>
  </form>
</main>

<script nonce="<?= e($nonce) ?>">
document.getElementById('md-setup-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  var errorEl = document.getElementById('md-setup-error');
  errorEl.classList.add('hidden');
  var pass = document.getElementById('setup-pass').value;
  var pass2 = document.getElementById('setup-pass2').value;
  if (pass !== pass2) { errorEl.textContent = 'Passwords do not match.'; errorEl.classList.remove('hidden'); return; }
  if (pass.length < 10) { errorEl.textContent = 'Use at least 10 characters.'; errorEl.classList.remove('hidden'); return; }
  var button = document.getElementById('md-setup-submit');
  button.disabled = true;
  try {
    var body = new URLSearchParams({
      action: 'setup',
      code: document.getElementById('setup-code').value,
      password: pass,
      _token: <?= json_encode($app->csrf->token()) ?>
    });
    var response = await fetch(<?= json_encode($req->scriptUrl()) ?> + '?action=setup', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': <?= json_encode($app->csrf->token()) ?> },
      body: body.toString(),
      credentials: 'same-origin'
    });
    var data = await response.json();
    if (data.ok) { location.href = data.redirect || <?= json_encode($req->url(['page' => 'login'])) ?>; return; }
    errorEl.textContent = data.error || 'Setup failed';
    errorEl.classList.remove('hidden');
  } catch (err) {
    errorEl.textContent = 'Network error — try again.';
    errorEl.classList.remove('hidden');
  }
  button.disabled = false;
});
</script>
</body>
</html>
