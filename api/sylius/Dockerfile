# syntax=docker/dockerfile:1
# ulams-commerce: the headless Sylius 2.3 service (ADR 0131), one image with two commands (`web`, `worker`).
#
#   docker build -t ulams/commerce:dev api/sylius          (from the repository root)
#   compose (from api/): profile `commerce`, services `sylius` and `sylius-worker`
#
# PHP-FPM behind an unprivileged nginx (port 8080, ADR 0056 style). No Node: the native staff admin UI needs the
# Webpack assets, which are built later (CM-8); the Admin API and Shop API this service exists for do not.
ARG PHP_IMAGE=php:8.4-fpm-alpine3.24

FROM ${PHP_IMAGE} AS base
COPY --from=mlocati/php-extension-installer:2.12.0 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions intl gd pdo_pgsql pdo_mysql opcache zip bcmath exif sodium \
  && apk add --no-cache nginx tini curl \
  && addgroup -g 1000 sylius && adduser -D -u 1000 -G sylius sylius \
  && mkdir -p /var/www/sylius /run/nginx /var/lib/nginx/tmp /var/log/nginx \
  && chown -R sylius:sylius /var/www/sylius /run/nginx /var/lib/nginx /var/log/nginx

FROM base AS vendor
WORKDIR /var/www/sylius
COPY composer.json composer.lock symfony.lock ./
# bundles/ holds the ulams bridge (its namespace is autoloaded from composer.json)
COPY bundles bundles
RUN n=1; until composer install --no-scripts --no-dev --no-interaction --no-progress --prefer-dist; do [ "$n" -ge 3 ] && exit 1; n=$((n+1)); echo "retrying composer install ($n/3)" >&2; sleep 5; done

FROM base
WORKDIR /var/www/sylius
COPY --from=vendor /var/www/sylius/vendor vendor
COPY . .
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-ulams-sylius.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-ulams-sylius.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/entrypoint.sh /usr/local/bin/ulams-sylius
ENV APP_ENV=prod APP_DEBUG=0
# Autoload as a classmap and warm nothing that needs the database or a secret: those come at start (entrypoint.sh).
RUN composer dump-autoload --no-scripts --no-dev --classmap-authoritative \
  && chmod +x /usr/local/bin/ulams-sylius \
  && mkdir -p var config/jwt config/encryption public/media \
  && chown -R sylius:sylius var config/jwt config/encryption public/media
USER sylius
EXPOSE 8080
ENTRYPOINT ["/sbin/tini", "--", "ulams-sylius"]
CMD ["web"]
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 CMD curl -fsS http://127.0.0.1:8080/ulams/health >/dev/null || exit 1
