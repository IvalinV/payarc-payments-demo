#!/bin/sh

set -eu

max_attempts=60
attempt=1

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    php artisan passport:keys
fi

chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
chmod 600 storage/oauth-private.key
chmod 660 storage/oauth-public.key

echo "Waiting for the database..."
until php artisan migrate --force >/dev/null 2>&1; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "Database did not become ready after ${max_attempts} attempts." >&2
        exit 1
    fi

    attempt=$((attempt + 1))
    sleep 2
done

echo "Running migrations..."
php artisan migrate --force

echo "Seeding demo data..."
php artisan apiato:seed-deploy

mkdir -p storage/framework
touch storage/framework/app-ready

echo "Starting PHP-FPM..."
exec php-fpm -F
