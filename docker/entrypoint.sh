#!/bin/sh
set -e

cd /var/www/html

# The storage volume can start empty on a fresh deploy — ensure the framework
# directories exist and are writable before anything boots.
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Cache config/routes/views now that the runtime env is present.
# Deliberately does NOT migrate — migrations are an explicit deploy step:
#   docker compose exec php-fpm php artisan migrate --force
php artisan optimize || true

exec "$@"
