# syntax=docker/dockerfile:1
# The PHP runtime is built from docker/php in this repository (see docker/php/README.md).
# The php-base stage below does exactly what docker/php/Dockerfile does, so
# `docker compose up --build` needs no prebuilt image. To build on a published base instead,
# pass --build-arg BASE_IMAGE=ulams/php:8.4 and the php-base stage is skipped.
ARG BASE_IMAGE=php-base
ARG PHP_IMAGE=php:8.4-fpm-alpine3.24

FROM ${PHP_IMAGE} AS php-base
COPY --from=mlocati/php-extension-installer:2.12.0 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/ /usr/local/src/ulams-php/
RUN sh /usr/local/src/ulams-php/install.sh
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html

FROM ${BASE_IMAGE}
WORKDIR /var/www/html
EXPOSE 9000
COPY / /var/www/html
RUN \
  cp docker/conf/supervisor/supervisord.conf /etc/supervisord.conf \
  && cp docker/conf/php/ulams-custom-php.ini /usr/local/etc/php/conf.d/ulams-custom-php.ini \
  && cp docker/conf/php/ulams-production-php.ini /usr/local/etc/php/conf.d/zz-ulams-production-php.ini \
  && cp docker/conf/php/php-fpm-custom.conf /usr/local/etc/php-fpm.d/php-fpm-custom.conf
# No dev dependencies; every class is in the classmap, so autoloading never probes the disk.
RUN composer install --no-scripts --no-dev --no-interaction --classmap-authoritative
# LiaScript player (BSD-3-Clause), pinned version and checksum, not kept in git
RUN sh packages/liascript/bin/fetch-player.sh
# The package manifest is built at start from this vendor/ (php-profile.sh), never copied in
RUN rm -f bootstrap/cache/*.php
# init.sh builds the config/route/event/view caches per domain at start (php-profile.sh)
ENV ULAMS_OPTIMIZE=true

CMD /var/www/html/init.sh

HEALTHCHECK --interval=30s --timeout=30s --start-period=10s --retries=5 CMD [ "php", "artisan", "health:check" ]