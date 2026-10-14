<?php
/**
 * MD.Viewer 3.0 — admin login page (standalone, no sidebar).
 *
 * @var MdViewer\Core\App $app
 */

use MdViewer\View\Icons;

$req = $app->request;
$nonce = $app->nonce();
$locked = 0;
?>
<!doctype html>
<html lang="en" class="<?= e($app->config->str('MODE_DEFAULT') === 'dark' ? 'dark' : '') ?>" data-neutral="zinc" data-accent="default" data-radius="md" data-font="inter" data-mode-default="<?= e($app->config->str('MODE_DEFAULT')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · MD.Viewer Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e($req->assetUrl('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e($app->tpl->asset('css/themes.css')) ?>">
<script nonce="<?= e($nonce) ?>">
(function () {
  var root = document.documentElement, stored = null;
  try { stored = JSON.parse(localStorage.getItem('mdv.theme') || 'null'); } catch (e) {}
  if (stored) {
    if (stored.neutral) root.setAttribute('data-neutral', stored.neutral);
    if (stored.accent) root.setAttribute('data-accent', stored.accent);
    if (stored.radius) root.setAttribute('data-radius', stored.radius);
    if (stored.font) root.setAttribute('data-font', stored.font);
  }
  var mode = (stored && stored.mode) || root.getAttribute('data-mode-default') || 'system';
  root.classList.toggle('dark', mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches));
})();
</script>
</head>
<body class="grid min-h-dvh place-items-center bg-muted/40 px-4">

<main class="w-full max-w-sm">
  <div class="mb-6 text-center">
    <span class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-xl bg-primary text-primary-foreground shadow-lg"><?= Icons::svg('shield', 'h-5 w-5') ?></span>
    <h1 class="text-xl font-bold tracking-tight">MD.Viewer Admin</h1>
    <p class="mt-1 text-sm text-muted-foreground">Sign in to manage your documentation</p>
  </div>

  <form id="md-login-form" class="md-card p-6" method="post" novalidate>
    <div class="mb-4">
      <label class="md-label" for="login-user">Username</label>
      <input class="md-input" id="login-user" name="username" type="text" autocomplete="username" required autofocus value="admin">
    </div>
    <div class="mb-4">
      <label class="md-label" for="login-pass">Password</label>
      <input class="md-input" id="login-pass" name="password" type="password" autocomplete="current-password" required>
    </div>
    <p id="md-login-error" class="mb-3 hidden text-sm text-destructive" role="alert"></p>
    <button type="submit" class="md-btn md-btn-primary w-full" id="md-login-submit">
      <?= Icons::svg('log-in', 'h-4 w-4') ?>Sign in
    </button>
  </form>

  <p class="mt-5 text-center text-xs text-muted-foreground">
    <a class="md-link" href="<?= e(rtrim(dirname($req->scriptUrl()), '/')) ?>/index.php">← Back to the site</a>
  </p>
</main>

<script nonce="<?= e($nonce) ?>">window.MDV_ADMIN = { scriptUrl: <?= json_encode($req->scriptUrl()) ?>, csrf: <?= json_encode($app->csrf->token()) ?> };</script>
<script nonce="<?= e($nonce) ?>">
document.getElementById('md-login-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  var errorEl = document.getElementById('md-login-error');
  var button = document.getElementById('md-login-submit');
  errorEl.classList.add('hidden');
  button.disabled = true;
  try {
    var body = new URLSearchParams({
      action: 'login',
      username: document.getElementById('login-user').value,
      password: document.getElementById('login-pass').value,
      _token: window.MDV_ADMIN.csrf
    });
    var response = await fetch(window.MDV_ADMIN.scriptUrl + '?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window.MDV_ADMIN.csrf },
      body: body.toString(),
      credentials: 'same-origin'
    });
    var data = await response.json();
    if (data.ok) { location.href = window.MDV_ADMIN.scriptUrl; return; }
    errorEl.textContent = data.error || 'Login failed';
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
