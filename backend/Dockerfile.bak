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

COPY . .

RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

RUN composer dump-autoload --optimize || { echo '=== php -m ==='; php -m; echo '=== artisan package:discover -vvv ==='; php artisan package:discover --ansi -vvv; exit 1; }

RUN touch database/database.sqlite \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs \
    && chmod -R 777 storage bootstrap/cache database

EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
