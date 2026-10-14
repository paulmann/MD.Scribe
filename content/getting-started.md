---
title: "Getting started: install in two minutes"
tags: [install, guide]
---

# Getting started: install in two minutes

MD.Scribe v3 is a folder, not a service. Drop it on any PHP 8.3 host and it
runs — no database, no Node.js on the server, no build step.

## Requirements

| Component | Minimum | Notes |
| --------- | ------- | ----- |
| PHP       | 8.3     | `mbstring`, `dom`, `libxml`, `json`; `curl` for the updater |
| Disk      | ~15 MB  | including vendored Mermaid + KaTeX |
| Web server | any    | Apache, nginx, Caddy, or `php -S` |

## Quick start

```bash
# 1. Get the code
git clone https://github.com/paulmann/MD.Scribe.git /var/www/docs
cd /var/www/docs

# 2. Dependencies are vendored — if you cloned with --no-vendor, run:
composer install --no-dev --optimize-autoloader

# 3. Put your documents in content/
cp /path/to/your/*.md content/

# 4a. Development:
php -S localhost:8080 -t public public/router.php

# 4b. Production (nginx): point the docroot at public/
```

Open `http://localhost:8080/` — the welcome document renders, the Library
lists everything under `content/`, and `.md.ini` is created automatically
with safe defaults.

## Deployment layouts

### A. Docroot = `public/` (recommended)

```nginx
server {
    listen 443 ssl http2;
    server_name docs.example.com;
    root /var/www/docs/public;
    index index.php;

    location / { try_files $uri /index.php$is_args$args; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    # src/, storage/, config/ are outside the docroot — unreachable by design.
}
```

### B. Docroot = project root (shared hosting)

Upload the whole folder. The bundled `.htaccess` denies `src/`, `config/`,
`storage/`, `templates/`, `vendor/` and dotfiles. Browse to `/index.php` or
`/md.php` (the v2-compatible shim).

### Docker

```bash
docker build -f deploy/Dockerfile -t mdscribe .
docker run -p 8080:8080 -v ./content:/app/content mdscribe
```

## First steps after install

1. **Set the admin password** — edit `.md.ini`, set `ADMIN_PASSWORD = a-strong-secret`.
   On the next request it is hashed into `ADMIN_PASSWORD_HASH` and cleared.
2. Open `/admin.php`, log in, and review **Security → Health**.
3. Choose a theme in **Themes** and set it as the installation default in
   **Admin → Settings** (`THEME_DEFAULT`).
4. Drop your `.md` files into `content/` (or upload them in the admin panel).

> [!IMPORTANT]
> `storage/` must be writable by the PHP process (cache, search index, logs,
> backups). The health page tells you when it is not.

## Upgrading from v2

Keep your old `md.php?file=…` links working: place the v3 `md.php` shim at
the project root — it serves the same URLs, the same `&download=1` contract,
and still prefers `md.md` as the home document when present. See
[docs/upgrade-from-v2.md](../docs/upgrade-from-v2.md) for the full mapping.

---

Next: [Security model →](security.md) · [← Back to the tour](index.md)
