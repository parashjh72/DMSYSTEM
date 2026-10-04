# ------------------------------------------------------------------
# DMSYSTEM — production image for Coolify (Dockerfile build pack)
# PHP 8.4 + nginx + PHP-FPM via the serversideup Laravel image.
# ------------------------------------------------------------------

# Stage 1: build frontend assets with Vite
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Stage 2: runtime
FROM serversideup/php:8.4-fpm-nginx AS runtime

USER root

# Composer binary for the dependency install step
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Application code (frontend build output copied in from stage 1)
COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=frontend /app/public/build /var/www/html/public/build

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

RUN composer install --no-dev --optimize-autoloader --no-progress --no-interaction \
    && php artisan storage:link \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R ug+rwx storage bootstrap/cache \
    && rm -rf /tmp/composer-cache

# serversideup images serve on 8080 by default
EXPOSE 8080

USER www-data
