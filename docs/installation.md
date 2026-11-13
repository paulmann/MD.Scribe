# Installation & deployment

MD.Scribe is a folder of PHP files. There is no installer service, no database
and no build step on the server — deployment means putting the folder where
PHP runs and pointing a web server at it.

## Requirements

| Component | Required | Notes |
| --- | --- | --- |
| PHP | **8.5+** (verified on 8.5.12) | `mbstring`, `dom`, `libxml`, `json` |
| PHP (recommended) | `curl`, `phar`, `zip`, `opcache` | updater, backup archives, performance |
| Web server | any | Apache, nginx, Caddy, LiteSpeed, `php -S` |
| Disk | ~15 MB | app + vendored assets |
| Writable | `storage/`, `.md.ini` | cache, index, logs, backups, config |

## Layout A — docroot at `public/` (recommended)

The cleanest and safest layout: `src/`, `config/`, `storage/`, `templates/`
and `vendor/` are physically unreachable from the web.

```
/var/www/docs/            ← project root
├── public/               ← DOCUMENT ROOT
│   ├── index.php
│   ├── admin.php
│   └── assets/
├── src/ config/ storage/ content/ …
```

### nginx

A ready-made vhost ships in [`deploy/nginx.conf`](../deploy/nginx.conf):

```nginx
server {
    listen 443 ssl http2;
    server_name docs.example.com;
    root /var/www/docs/public;
    index index.php;

    location / { try_files $uri /index.php$is_args$args; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~* \.(css|js|woff2|svg|png|jpg|webp)$ {
        expires 30d;
        add_header Cache-Control "public";
    }
}
```

### Apache

Point `DocumentRoot` at `public/`. The bundled `public/.htaccess` provides the
rewrite (`try_files` equivalent), compression and cache headers. Required:
`mod_rewrite` enabled and `AllowOverride All` for the directory.

### Caddy

```caddy
docs.example.com {
    root * /var/www/docs/public
    php_fastcgi unix//run/php/php8.5-fpm.sock
    try_files {path} /index.php?{query}
    file_server
    encode zstd gzip
}
```

### Docker

```bash
docker build -f deploy/Dockerfile -t mdscribe .
docker run -d -p 8080:80 \
  -v "$PWD/content:/app/content" \
  -v mdscribe-storage:/app/storage \
  --name docs mdscribe
```

`deploy/docker-compose.yml` adds a persistent volume and sane PHP settings.

## Layout B — docroot at the project root (shared hosting)

Upload the whole project as your site. The bundled root `.htaccess` denies
`src/`, `config/`, `storage/`, `templates/`, `tests/`, `tools/`, `vendor/`,
`docs/`, `deploy/` and all dotfiles, and PHP only executes the three entry
scripts (`index.php`, `md.php`, `admin.php` — the latter two also reachable
under `public/`).

Browse to `https://site/index.php` (or `https://site/md.php` for the v2
contract). Assets resolve under `/public/assets/…` automatically — no
configuration needed, even in sub-directories.

> [!WARNING]
> Layout A is strictly safer: `.htaccess` protection depends on Apache
> honoring it (`AllowOverride`). If your host runs nginx or disables
> overrides, use Layout A.

## Layout C — PHP built-in server (development)

```bash
php -S localhost:8080 -t public public/router.php
```

## First run

1. Open the viewer once — `.md.ini` is created with safe defaults and an
   auto-generated `API_KEY`.
2. Open `admin.php` → **first-run setup**:
   - from localhost: just set the admin password (≥ 10 chars);
   - remotely: read the one-time setup code from `storage/logs/setup.log`
     (via SSH/FTP) and enter it with the new password.
   Alternatively set `ADMIN_PASSWORD = …` in `.md.ini` once — it is hashed
   into `ADMIN_PASSWORD_HASH` and cleared on the next request.
3. Check **Admin → Security & health** — every check should be green.

## Permissions

```bash
chown -R www-data:www-data storage
chmod -R ug+rwX storage
chmod 640 .md.ini          # contains the API key & password hash
```

`content/` must be writable only if you use uploads/editor from the panel
(reader uploads are disabled by default).

## Upgrading

**Admin → Updates** (or `updater.php?update=true` with `ALLOW_UPDATE = true`)
downloads the release archive, verifies every file against its sha256
manifest, backs up replaced files to `storage/backups/app-*/` and swaps them.
`content/`, `storage/` and `.md.ini` are never touched. Manual upgrades:
replace the code directories, keep those three.

## Verified installations

Layouts that were actually exercised end to end, with what was measured:

| Host | Layout | PHP | Checked |
| --- | --- | --- | --- |
| Ubuntu 26.04, nginx, PHP-FPM per-site pool | A — docroot `public/`, mounted in a sub-directory (`/mdscribe/`) next to another application | 8.5.12 | viewer, library, tags page, search with `tag:`/`path:`, missing document → 404, `manifest.webmanifest` + `sw.js` served, machine API `status`/`conflicts`/`install dry_run` (948 files verified against the release archive), `public/updater.php` bridge |

A sub-directory install needs nothing beyond the document root: the front
controller derives its web prefix from `SCRIPT_NAME`, so assets, links and the
service-worker scope stay inside `/mdscribe/`. Document-root-at-`public/` installs
reach the updater through the shipped `public/updater.php` bridge.

## Static MIME type for the PWA manifest

`public/manifest.webmanifest` needs `application/manifest+json`. Most servers do
not know the extension and fall back to `application/octet-stream`; browsers keep
working, but the manifest is reported as a bad type in devtools and some do reject
it. One line per server:

```nginx
# nginx, inside the server block (or a vhost resource file)
types { application/manifest+json webmanifest; }
```

```apache
# Apache
AddType application/manifest+json .webmanifest
```

The service worker (`public/sw.js`) needs no change: `application/javascript` is
already associated with `.js`, and its scope is the directory it is served from.

## Verification checklist

- [ ] `https://site/` renders the welcome document
- [ ] `https://site/index.php?page=browser` lists your documents
- [ ] `https://site/admin.php` asks for login (after setup)
- [ ] `https://site/.md.ini` → **403/404** (never served!)
- [ ] `https://site/src/Core/App.php` → 403/404 in both layouts
- [ ] DevTools → Network: no requests to third-party hosts
- [ ] Admin → Security & health: all green
