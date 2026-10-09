#!/bin/sh
# Builds the release tarball to upload to MyDevil. Run it on your machine or in CI, never on the host:
# MyDevil has no Docker, and the production vendor/ tree (composer install --no-dev, class map) is
# plain PHP, so one built on Linux or macOS runs on FreeBSD.
#
#   deploy/mydevil/bin/build-release.sh [output-dir]      (needs git and docker)
#
# Writes <output-dir>/ulams-<commit>.tar.gz (default ./dist) with api/ (app and vendor/), bin/ (these
# scripts), crontab and .env.example. It never contains an .env file or storage/.
set -eu

REPO="$(git rev-parse --show-toplevel)"
OUT="${1:-$REPO/dist}"
REV="$(git -C "$REPO" rev-parse --short HEAD)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT INT TERM

mkdir -p "$OUT"
# tracked files only, so local .env files and caches cannot leak into the tarball
git -C "$REPO" archive HEAD api deploy/mydevil | tar -x -C "$WORK"

# the same Composer image as api/Dockerfile; --ignore-platform-reqs because the build image has no
# pgsql/intl/... extensions (the server's PHP has them, bin/check-host.sh verifies it). The LiaScript
# player (pinned version and SHA-256, not kept in git) is fetched in the same container, so the
# script does not depend on the host's sha256sum (macOS has none).
docker run --rm -v "$WORK/api:/app" -w /app composer:2.10 sh -c '
  composer install --no-dev --no-scripts --no-interaction --classmap-authoritative --ignore-platform-reqs &&
  sh packages/liascript/bin/fetch-player.sh'

mkdir -p "$WORK/release"
mv "$WORK/api" "$WORK/release/api"
mv "$WORK/deploy/mydevil/bin" "$WORK/release/bin"
cp "$WORK/deploy/mydevil/crontab" "$WORK/deploy/mydevil/.env.example" "$WORK/release/"
rm -rf "$WORK/release/api/tests" "$WORK/release/api/bootstrap/cache"/*.php

tar -czf "$OUT/ulams-$REV.tar.gz" -C "$WORK/release" .
echo "built $OUT/ulams-$REV.tar.gz"
