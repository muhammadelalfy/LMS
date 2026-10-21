# syntax=docker/dockerfile:1.7

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM dunglas/frankenphp:1-php8.5-bookworm

RUN install-php-extensions pdo_sqlite intl zip gd bcmath pcntl redis opcache exif

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-mudarisy.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/mudarisy-entrypoint
COPY --chmod=0755 docker/init.sh /usr/local/bin/mudarisy-init

WORKDIR /app
COPY --from=vendor --chown=www-data:www-data /app /app

# database/ and storage/ are replaced by shared volumes at runtime; keep pristine
# copies so the init container can sync code-owned files into the volume.
RUN php artisan package:discover --ansi \
 && rm -f database/*.sqlite database/tenant* \
 && cp -a database /app/database.dist \
 && cp -a storage /app/storage.dist \
 && ln -sfn /app/storage/app/public /app/public/storage \
 && mkdir -p /data/caddy /config/caddy \
 && chown -R www-data:www-data /app /data /config

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:8000

USER www-data
EXPOSE 8000
ENTRYPOINT ["mudarisy-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
