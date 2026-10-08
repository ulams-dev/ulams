# ulams PHP runtime image

The PHP runtime of the ulams API, built from this repository on top of the official
[`php:8.4-fpm-alpine`](https://hub.docker.com/_/php) image. It replaces the external
`escolalms/php:8.3-alpine` image, so the API no longer depends on a third-party base image
that we do not control.

## What is inside

| Part | Contents |
|---|---|
| Base | `php:8.4-fpm-alpine3.24` (official Docker image, PHP 8.4, php-fpm on port 9000) |
| PHP extensions added | apcu, bcmath, exif, gd (freetype, jpeg, png, webp, avif, xpm), intl, pcntl, pdo_mysql, pdo_pgsql, redis, zip |
| PHP extensions from the base | ctype, curl, dom, fileinfo, iconv, mbstring, opcache, openssl, pdo_sqlite, posix, readline, sodium, tokenizer, xml, xmlreader, xmlwriter, zlib and the rest of the core set |
| Tools | composer 2.10, supervisor, bash, ffmpeg/ffprobe, jpegoptim, optipng, pngquant, gifsicle, unzip, curl |
| php-fpm | `conf/php-fpm.d/00-ulams-base.conf`: `pm.max_requests = 500`, `listen.backlog = 1024`, `request_terminate_timeout = 120s` (the app's `docker/conf/php/php-fpm-custom.conf` raises it to 3600s) |

Why each tool is there:

- **supervisor**: `init.sh` starts php-fpm, Horizon, the tenant queue workers and the scheduler
  through `supervisord`.
- **bash**: `init.sh`, `queue.sh`, `scheduler.sh`, `broadcast.sh` and `domains.sh` are bash scripts.
- **ffmpeg/ffprobe**: HLS conversion in `packages/video` through `php-ffmpeg`.
- **jpegoptim, optipng, pngquant, gifsicle**: `spatie/image-optimizer` in `packages/images`.
  `svgo`, `cwebp` and `avifenc` are not installed (the old image did not have them either);
  the optimizer skips missing binaries.
- **exif**: used by `intervention/image` to read orientation; new compared with the old image.

Not included, same as the old image: Node.js, the `mjml` binary and the PostgreSQL client.
Email templates are rendered through the MJML API (`MJML_API_URL`, the `mjml` service);
`MJML_BINARY_PATH=/usr/bin/mjml` in `docker-compose.yml` points to a file that has never
existed in the image. Compilers and `-dev` headers are not kept either; the old image shipped
them, which is most of the size difference (1.32 GB before, about 430 MB now).

## How the API images use it

`api/Dockerfile` and `api/Dockerfile.develop` contain a `php-base` stage that runs the same
`install.sh` as `docker/php/Dockerfile`. The final stage is `FROM ${BASE_IMAGE}`, which
defaults to that stage, so

```sh
cd api && docker compose up --build
```

works with no prebuilt image and no registry. BuildKit caches the `php-base` stage; it is
rebuilt only when `docker/php/` changes. `Dockerfile.develop` adds the `excimer` profiler
on top, built from the GitHub source because pecl.php.net is unreliable; the base has no
compilers, so that step installs `$PHPIZE_DEPS` and removes them again.

The package list lives in `install.sh` only. When you change the upstream pins (the
`php`, `mlocati/php-extension-installer` or `composer` images), change them in all three
Dockerfiles.

## Building and publishing the base on its own

```sh
docker build -t ulams/php:8.4 api/docker/php
# multi-arch, for a registry
docker buildx build --platform linux/amd64,linux/arm64 \
  -t ghcr.io/ulams-dev/php:8.4 --push api/docker/php
```

To build the API on a published base instead of the inline stage:

```sh
docker build --build-arg BASE_IMAGE=ghcr.io/ulams-dev/php:8.4 -t ulams-api api
```

BuildKit then skips the `php-base` stage entirely.

## Licences

The image is built from the official PHP image (PHP licence 3.01) and unmodified Alpine
packages. The PHP extensions are PHP-3.01 licensed and composer is MIT.
`install-php-extensions` (MIT) is used only during the build and removed afterwards.

Several bundled command-line programs are GPL: ffmpeg (Alpine builds it with
`--enable-gpl --enable-version3`, with x264 and x265), jpegoptim, pngquant, gifsicle and
bash. The application only runs them as separate processes, which keeps the ulams code
outside their licence terms, but anyone who distributes the image (for example by pushing
it to a public registry) must be able to provide the corresponding source. Alpine's aports
repository and the exact package versions in the image (`apk info -v`) cover that. The full
list is in [`NOTICE`](NOTICE), copied into the image as
`/usr/local/share/doc/ulams-php/NOTICE`.
