#!/bin/sh
# Builds the ulams PHP runtime on top of the official php:8.4-fpm-alpine image.
# Run by docker/php/Dockerfile and by the php-base stage of api/Dockerfile and
# api/Dockerfile.develop, so the package list lives in one place.
# Expects install-php-extensions and composer to be copied in beforehand.
set -eux

SRC_DIR="$(cd "$(dirname "$0")" && pwd)"

# Runtime tools used by the app and by the container scripts:
# - bash: init.sh, queue.sh, scheduler.sh, broadcast.sh, domains.sh
# - supervisor: runs php-fpm, Horizon, queue workers and the scheduler
# - ffmpeg (ffmpeg + ffprobe): HLS processing in packages/video (php-ffmpeg)
# - jpegoptim, optipng, pngquant, gifsicle: spatie/image-optimizer in packages/images
# - unzip: composer dist installs
apk add --no-cache \
    bash \
    ffmpeg \
    gifsicle \
    jpegoptim \
    optipng \
    pngquant \
    supervisor \
    unzip

# PHP extensions on top of the ones compiled into the official image
# (ctype, curl, dom, fileinfo, iconv, mbstring, opcache, openssl, pdo_sqlite,
# posix, sodium, tokenizer, xml*, zlib, ...). install-php-extensions adds the
# runtime libraries and removes the build dependencies afterwards.
# gd is built with freetype, jpeg, png, webp, avif and xpm support.
install-php-extensions \
    apcu \
    bcmath \
    exif \
    gd \
    intl \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    redis \
    zip

rm -f /usr/local/bin/install-php-extensions

# php-fpm pool defaults (the app's docker/conf/php/php-fpm-custom.conf sorts after it and wins)
cp "$SRC_DIR"/conf/php-fpm.d/*.conf /usr/local/etc/php-fpm.d/

mkdir -p /usr/local/share/doc/ulams-php /etc/supervisor/conf.d /etc/supervisor/custom.d
cp "$SRC_DIR/NOTICE" /usr/local/share/doc/ulams-php/NOTICE

rm -rf /tmp/* /var/cache/apk/* /root/.cache "$SRC_DIR"
