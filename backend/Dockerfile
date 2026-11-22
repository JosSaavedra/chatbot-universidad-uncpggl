FROM php:8.4-cli

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libonig-dev \
        libzip-dev \
        libcurl4-openssl-dev \
    && docker-php-ext-install mbstring zip curl pcntl \
    && (php -m | grep -qi pdo_sqlite || (apt-get install -y libsqlite3-dev && docker-php-ext-install pdo_sqlite)) \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY artisan composer.json composer.lock ./
COPY app/ app/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY database/factories/ database/factories/
COPY database/migrations/ database/migrations/
COPY database/seeders/ database/seeders/
COPY docker/ docker/
COPY public/ public/
COPY resources/ resources/
COPY routes/ routes/

RUN mkdir -p bootstrap/cache storage/database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R u=rwX,g=,o= bootstrap/cache storage

ENV APP_ENV=production APP_DEBUG=false DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/database/database.sqlite \
    PHP_CLI_SERVER_WORKERS=4

USER www-data

EXPOSE 10000

# El arranque migra SQLite, siembra datos ficticios sólo si la base está vacía
# y DEMO_SEED_ON_EMPTY=true, y sincroniza los vínculos QR. Nunca restablece
# contraseñas ni sobrescribe registros existentes.
CMD ["sh", "docker/start.sh"]
