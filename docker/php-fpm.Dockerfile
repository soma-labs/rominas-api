# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — Composer dependencies (no dev, optimized autoloader)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# Install deps against the lockfile first for layer caching, then the source.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------------------------------------------------------------------------
# Stage 2 — Runtime (php-fpm + supervisor + cron). MVP: one container runs
# php-fpm, the queue worker, and the scheduler. See rominas-deployment/README.md
# for why this is an anti-pattern and how to split into services later.
# ---------------------------------------------------------------------------
# PHP 8.5 matches the Sail dev runtime; the lockfile needs >= 8.4.1. opcache is
# compiled into PHP 8.5, so it is not in the extension list below.
FROM php:8.5-fpm-bookworm

# System packages + PHP extensions. No gd/redis: the API stores no media and the
# queue/cache/session all use the database driver. pcntl gives queue:work clean
# signal handling for graceful shutdown.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        cron \
        supervisor \
        default-mysql-client \
        libicu-dev \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath intl zip pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer available in the runtime image (for maintenance: dump-autoload, etc.).
COPY --from=vendor /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-rominas.ini

WORKDIR /var/www/html

# App source + the optimized vendor/ from the composer stage.
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Writable dirs (the storage volume may start empty; entrypoint re-ensures these).
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Process supervision + scheduler cron.
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/cron/laravel-scheduler /etc/cron.d/laravel-scheduler
RUN chmod 0644 /etc/cron.d/laravel-scheduler

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
