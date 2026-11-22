#!/bin/sh
set -eu
umask 077

php -r '$key = (string) getenv("APP_KEY"); if (str_starts_with($key, "base64:")) { $key = (string) base64_decode(substr($key, 7), true); } if (strlen($key) !== 32) { fwrite(STDERR, "Configure una APP_KEY válida de 32 bytes antes de arrancar.\n"); exit(1); }'

if [ "${DB_CONNECTION:-}" != "sqlite" ] || [ -n "${DB_URL:-}" ]; then
    echo 'Configure SQLite mediante DB_DATABASE y retire DB_URL.' >&2
    exit 1
fi
if [ ! -w "$(dirname "$DB_DATABASE")" ]; then
    echo 'El directorio SQLite debe ser escribible por www-data.' >&2
    exit 1
fi
if [ ! -e "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi

php artisan migrate --force
php artisan demo:ensure-seeded
php artisan qr-google:sync-links
exec php -d expose_php=0 -d display_errors=0 -d log_errors=1 -d post_max_size=1M \
    -S "0.0.0.0:${PORT:-10000}" -t public "$PWD/docker/router.php"
