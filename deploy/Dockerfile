# MD.Viewer 3 — production container (Apache + PHP 8.3, docroot at public/)
#
#   docker build -f deploy/Dockerfile -t mdviewer .
#   docker run -d -p 8080:80 -v "$PWD/content:/app/content" \
#              -v mdviewer-storage:/app/storage --name docs mdviewer

FROM php:8.3-apache

# Extensions MD.Viewer needs/recommends: mbstring (required), zip (backup
# archives), opcache (performance). dom/libxml/json are compiled in.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libonig-dev zlib1g-dev unzip \
    && docker-php-ext-install -j"$(nproc)" mbstring zip \
    && docker-php-ext-enable opcache \
    && a2enmod rewrite headers expires deflate \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Project
COPY --chown=www-data:www-data . /app

# PHP configuration (production posture)
COPY deploy/php.ini /usr/local/etc/php/conf.d/mdviewer.ini

# Apache: docroot → public/, allow .htaccess overrides
ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri -e 's!/var/www/html!/app/public!g' \
        /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Runtime dirs must be writable by the PHP user
RUN mkdir -p /app/storage/cache /app/storage/logs /app/storage/index \
             /app/storage/state /app/storage/backups /app/storage/themes \
    && chown -R www-data:www-data /app/storage /app/content \
    && chmod -R ug+rwX /app/storage

VOLUME ["/app/storage", "/app/content"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -fsS "http://127.0.0.1/index.php?page=browser" -o /dev/null || exit 1
