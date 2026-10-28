FROM php:8.5-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev unzip \
    && docker-php-ext-install intl \
    && docker-php-ext-install pdo_mysql \
    && sed -i 's/^;clear_env = no/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN composer dump-autoload --no-interaction --no-scripts \
    && mkdir -p var/cache var/log public/uploads \
    && chown -R www-data:www-data var public/uploads

EXPOSE 9000

CMD ["php-fpm", "-F"]
