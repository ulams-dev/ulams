# syntax=docker/dockerfile:1
# ulams PHP runtime: official php:8.3-fpm-alpine plus the extensions and tools the API needs.
# Build:  docker build -t ulams/php:8.3 api/docker/php
# The api/Dockerfile and api/Dockerfile.develop build the same thing inline in their
# php-base stage, so this file is only needed to publish the base image. See README.md.
ARG PHP_IMAGE=php:8.3-fpm-alpine3.24

FROM ${PHP_IMAGE}
COPY --from=mlocati/php-extension-installer:2.12.0 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer
COPY install.sh NOTICE /usr/local/src/ulams-php/
COPY conf/ /usr/local/src/ulams-php/conf/
RUN sh /usr/local/src/ulams-php/install.sh
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html
