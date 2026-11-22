#!/bin/sh
set -eu
umask 077

php -r '$key = (string) getenv("APP_KEY"); if (str_starts_with($key, "base64:")) { $key = (string) base64_decode(substr($key, 7), true); } if (strlen($key) !== 32) { fwrite(STDERR, "Configure una APP_KEY válida de 32 bytes antes de arrancar.\n"); exit(1); }'

if [ "${DB_CONNECTION:-}" != "sqlite" ] || [ -n "${DB_URL:-}" ]; then
    echo 'Configure SQLite mediante DB_DATABASE y retire DB_URL.' >&2
    exit 1
fi
if [ ! -w "$(dirname "$DB_DATABASE")" ]; then
    echo 'El directorio SQLite debe ser escribible.' >&2
    exit 1
fi
if [ ! -e "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi

# Render inyecta PORT en cada arranque. Se valida antes de escribir la
# configuración de Apache para no inyectar directivas arbitrarias.
PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*) echo 'PORT debe ser un número de puerto válido.' >&2; exit 1 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo 'PORT debe estar entre 1 y 65535.' >&2
    exit 1
fi

printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${PORT}>
    ServerName localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
    LogLevel warn
</VirtualHost>
EOF

php artisan migrate --force
php artisan demo:ensure-seeded
unset DEMO_STUDENT_PASSWORD_HASHES
php artisan qr-google:sync-links

# Los comandos anteriores se ejecutan como root; se devuelve la propiedad a
# www-data para que Apache pueda escribir SQLite, sesiones y caché.
chown -R www-data:www-data bootstrap/cache storage
chmod -R u=rwX,g=,o= bootstrap/cache storage

exec apache2-foreground
