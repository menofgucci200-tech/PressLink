# syntax=docker/dockerfile:1

# ---- Étape 1 : build des assets front (Tailwind/Vite) ----
FROM node:22-alpine AS node-build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY resources ./resources
COPY vite.config.* ./
RUN npm run build

# ---- Étape 2 : image runtime PHP-FPM ----
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache \
        libzip-dev zlib-dev libpng-dev libjpeg-turbo-dev freetype-dev icu-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache

# Le binaire Composer est copié depuis l'image officielle plutôt que
# réinstallé — mais `composer install` tourne ICI, dans l'image finale,
# une fois les extensions PHP (gd, zip, intl...) en place. Les lancer
# dans une image composer:2 séparée fait échouer la vérification de
# plateforme de phpoffice/phpspreadsheet (ext-gd), qui n'y est pas
# installée alors qu'elle l'est bien à l'exécution réelle.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Réglages de production : mémoire/upload adaptés aux photos client + logo pressing.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/presslink.ini

WORKDIR /var/www/html

COPY . .
COPY --from=node-build /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && php artisan storage:link

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
