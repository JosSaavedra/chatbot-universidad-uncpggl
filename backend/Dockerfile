FROM php:8.4-apache

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data

# Extensiones requeridas por Laravel 13 / Symfony 8.1 (PHP >= 8.4).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libonig-dev \
        libzip-dev \
        libcurl4-openssl-dev \
        libsqlite3-dev \
    && docker-php-ext-install mbstring zip curl pcntl \
    && (php -m | grep -qi pdo_sqlite || docker-php-ext-install pdo_sqlite) \
    && (a2dismod mpm_event || true) \
    && a2enmod mpm_prefork rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache sólo sirve el directorio público; el resto del proyecto queda fuera
# del DocumentRoot. La configuración de puerto se genera en docker/start.sh
# porque Render inyecta PORT en tiempo de ejecución.
COPY docker/apache-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY docker/php-render.ini /usr/local/etc/php/conf.d/zz-render.ini

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
    DB_DATABASE=/var/www/html/storage/database/database.sqlite

EXPOSE 10000

# El proceso maestro de Apache se inicia como root para poder enlazar el puerto
# dinámico PORT; los procesos hijos atienden como www-data. Antes de ejecutar
# Apache se migran los esquemas, se siembran datos ficticios sólo si la base
# está vacía y se sincronizan los vínculos QR. Nunca se restablecen
# contraseñas ni se sobrescriben registros existentes.
CMD ["sh", "docker/start.sh"]
