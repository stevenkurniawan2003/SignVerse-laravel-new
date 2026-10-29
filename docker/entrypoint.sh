#!/bin/sh
set -e

cd /var/www/html

# Apache mendengarkan port dari env PORT (Render/Railway/dsb. mengisi ini otomatis).
PORT="${PORT:-80}"
export PORT
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY belum di-set. Buat dengan: php artisan key:generate --show" >&2
    exit 1
fi

# SQLite: pastikan file database ada (sebaiknya di volume agar data tidak hilang).
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
    chown -R www-data:www-data "$(dirname "$DB_FILE")"
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

php artisan optimize

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

exec "$@"
