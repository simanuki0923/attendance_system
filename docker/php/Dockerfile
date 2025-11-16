FROM composer:2 AS composer

FROM php:8.4-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev curl git unzip bash

RUN docker-php-ext-configure intl \
 && docker-php-ext-install -j$(nproc) pdo_mysql zip intl bcmath opcache

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www