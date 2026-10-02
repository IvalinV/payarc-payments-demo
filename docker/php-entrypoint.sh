#!/bin/sh

set -eu

max_attempts=60
attempt=1

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

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

mkdir -p storage/framework
touch storage/framework/app-ready

echo "Starting PHP-FPM..."
exec php-fpm -F
