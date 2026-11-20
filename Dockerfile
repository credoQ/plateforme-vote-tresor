# syntax=docker/dockerfile:1
# Seules les étapes app et web sont lancées, les autres préparent dépendances et assets.

# --- 1. Dépendances PHP (sans les outils de développement) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Les extensions PHP sont dans l'image finale, pas dans celle de Composer : on ne les vérifie pas ici.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# --- 2. Assets (CSS et JS compilés par Vite) ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
# Tailwind cherche les classes utilisées dans les vues (voir les @source de resources/css/app.css).
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN mkdir -p storage/framework/views && npm run build

# --- 3. Application : PHP-FPM et le code ---
FROM php:8.5-fpm-bookworm AS app

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
# pdo_pgsql : PostgreSQL ; gd : images des PDF (dompdf) ; intl : formats français ; zip : classeurs Excel (OpenSpout) ;
# pcntl : arrêt propre des tâches trop longues (queue:work --timeout). OPcache est intégré à PHP depuis la 8.5.
RUN install-php-extensions pdo_pgsql gd intl zip pcntl

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-plateforme.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-plateforme.conf
# Valeur par défaut, remplacée par PHP_FPM_MAX_CHILDREN du .env.
ENV PHP_FPM_MAX_CHILDREN=20

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/fonts bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# --- 4. Serveur web : Nginx avec les fichiers publics (CSS, JS, polices, images) ---
FROM nginx:1.27-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
