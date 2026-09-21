FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM php:8.4-fpm-alpine

RUN apk add --no-cache libzip-dev postgresql-dev \
    && docker-php-ext-install bcmath opcache pdo_mysql pdo_pgsql zip

WORKDIR /var/www

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-parafa.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage

USER www-data

EXPOSE 9000

CMD ["php-fpm", "-F"]
