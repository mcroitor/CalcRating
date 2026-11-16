#!/bin/sh
set -e

mkdir -p \
    /var/www/html/database/sqlite \
    /var/www/html/storage/app/public/clubs \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/backups

if [ ! -f /var/www/html/database/sqlite/database.sqlite ]; then
    touch /var/www/html/database/sqlite/database.sqlite
fi

if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link --no-interaction >/dev/null 2>&1 || true
fi

if [ -z "${APP_KEY:-}" ] && [ ! -f /var/www/html/.env ]; then
    echo "WARNING: APP_KEY is not set and .env file is missing. Generate a key with: php artisan key:generate --show" >&2
fi

if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
