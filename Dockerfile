FROM php:8.5.12-fpm-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libicu-dev libpq-dev libzip-dev libcurl4-openssl-dev unzip ca-certificates openssl && docker-php-ext-install mbstring intl pdo_pgsql sockets pcntl zip && pecl install redis && docker-php-ext-enable redis && rm -rf /var/lib/apt/lists/*
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini
WORKDIR /app

FROM php-base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-progress --no-interaction

FROM node:24-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
RUN npm run build

FROM php-base AS runtime
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && php artisan package:discover --no-interaction
COPY docker/php/entrypoint.sh /usr/local/bin/mail-entrypoint
RUN chmod +x /usr/local/bin/mail-entrypoint
USER www-data
ENTRYPOINT ["/usr/local/bin/mail-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.28-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /app/public
COPY --from=assets /app/public/build /app/public/build
RUN rm -f /app/public/hot
