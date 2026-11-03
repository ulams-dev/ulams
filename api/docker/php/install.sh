#!/bin/sh
# Builds the ulams PHP runtime on top of the official php:8.4-fpm-alpine image.
# Run by docker/php/Dockerfile and by the php-base stage of api/Dockerfile and
# api/Dockerfile.develop, so the package list lives in one place.
# Expects install-php-extensions and composer to be copied in beforehand.
set -eux

SRC_DIR="$(cd "$(dirname "$0")" && pwd)"

# Refuse to build on a PHP that is below the minimum. 8.4.27 fixes the two critical unauthenticated RCE bugs of
# php.net advisory 2026-10-21-5 (CVE-2026-109618, CVE-2026-109619). The minimum is the MIN_PHP_VERSION build arg
# (see docker/php/README.md); this is its only default. A stale base image or build cache fails here, not in production.
MIN_PHP_VERSION="${MIN_PHP_VERSION:-8.4.27}"
if ! MIN_PHP_VERSION="$MIN_PHP_VERSION" php -r 'exit(version_compare(PHP_VERSION, getenv("MIN_PHP_VERSION"), ">=") ? 0 : 1);'; then
    echo "ERROR: the base image has PHP $(php -r 'echo PHP_VERSION;'), below the required minimum $MIN_PHP_VERSION." >&2
    echo "Pull a newer php base image (docker build --pull) or wait for the official image to be updated." >&2
    echo "See https://www.php.net/archive/2026.php#2026-10-21-5 and docs/plans/security-php-rce-2026-10.md." >&2
    exit 1
fi
echo "PHP $(php -r 'echo PHP_VERSION;') satisfies the minimum $MIN_PHP_VERSION"

# pecl.php.net and the Alpine mirrors fail now and then ("does not have REST xml available"); a failed
# download should not fail the whole image publish, so network steps are retried.
retry() {
    n=1
    until "$@"; do
        if [ "$n" -ge 4 ]; then
            return 1
        fi
        n=$((n + 1))
        echo "retrying ($n/4): $*" >&2
        sleep $((n * 10))
    done
}

# Runtime tools used by the app and by the container scripts:
# - bash: init.sh, queue.sh, scheduler.sh, broadcast.sh, domains.sh
# - supervisor: runs php-fpm, Horizon, queue workers and the scheduler
# - ffmpeg (ffmpeg + ffprobe): HLS processing in packages/video (php-ffmpeg)
# - jpegoptim, optipng, pngquant, gifsicle: spatie/image-optimizer in packages/images
# - unzip: composer dist installs
# - git: the `git-cli` Living Course connector (read-only shallow clones of repositories on any server)
retry apk add --no-cache \
    bash \
    ffmpeg \
    git \
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
retry install-php-extensions \
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

# Exact versions of every Alpine package in the image: part of the written GPL source offer in
# NOTICE (the offer names this file). Written last, after install-php-extensions removed its
# build dependencies, so the list is what the image really contains.
mkdir -p /usr/share/doc/ulams
apk info -v | sort > /usr/share/doc/ulams/apk-packages.txt

rm -rf /tmp/* /var/cache/apk/* /root/.cache "$SRC_DIR"
