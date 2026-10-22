#!/bin/sh
# Runs api/tests/S3Compat (the S3 operations ulams uses) against an object store in a throwaway
# container (docs/plans/phase-8-self-hosting.md 4.3, ADR 0041).
#
#   s3-compat.sh [--store STORE] [--docker-php IMAGE [--vendor DIR]] [-- PHPUNIT_ARGS...]
#
# STORE (default seaweedfs):
#   seaweedfs         chrislusf/seaweedfs `weed mini`, the way the launcher and the dev stack run it
#   seaweedfs-server  `weed server -s3 -s3.config`, the way an operator with an identity file runs it
#   rustfs            rustfs/rustfs, the recorded fallback
#   minio             bitnamilegacy/minio, the baseline
# The tests run with the PHP and vendor/ of api/ on this machine. Without PHP here, give --docker-php
# IMAGE (for example ghcr.io/ulams-dev/php:8.4): api/ is mounted into a container on the store's network;
# --vendor DIR mounts an installed vendor/ over api/vendor (read only).
# SEAWEEDFS_IMAGE overrides the SeaweedFS image (default the tag the launcher pins).
# Exit status is the test run's. Container and network are removed on exit.
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
api="$here/../../api"
store="seaweedfs"
php_image=""
vendor=""
seaweed_image="${SEAWEEDFS_IMAGE:-chrislusf/seaweedfs:4.37}"
rustfs_image="${RUSTFS_IMAGE:-rustfs/rustfs:latest}"
minio_image="${MINIO_IMAGE:-bitnamilegacy/minio:latest}"

while [ $# -gt 0 ]; do
  case "$1" in
    --store) store="${2:?}"; shift ;;
    --docker-php) php_image="${2:?}"; shift ;;
    --vendor) vendor="${2:?}"; shift ;;
    -h | --help) sed -n "2,19p" "$0"; exit 0 ;;
    --) shift; break ;;
    *) echo "s3-compat: unknown argument '$1'" >&2; exit 2 ;;
  esac
  shift
done

command -v docker >/dev/null 2>&1 || { echo "s3-compat: docker is required" >&2; exit 2; }

id="s3compat-$$"
network="$id"
container="$id-store"
key="compat$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"
secret="$(od -An -N20 -tx1 /dev/urandom | tr -d ' \n')"
work="$(mktemp -d "${TMPDIR:-/tmp}/s3-compat.XXXXXX")"

cleanup() {
  docker rm -f "$container" >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
  rm -rf "$work"
}
trap cleanup EXIT INT TERM

docker network create "$network" >/dev/null

# the store is only reachable on its network; without a PHP container, publish its port on loopback
host_port="$((20000 + $$ % 20000))"
run_store() {
  if [ -n "$php_image" ]; then
    docker run -d --name "$container" --network "$network" "$@" >/dev/null
  else
    docker run -d --name "$container" --network "$network" -p "127.0.0.1:$host_port:$port" "$@" >/dev/null
  fi
}

case "$store" in
  seaweedfs)
    port=8333; health=/healthz; flavor=seaweedfs
    run_store -e AWS_ACCESS_KEY_ID="$key" -e AWS_SECRET_ACCESS_KEY="$secret" \
      "$seaweed_image" mini -dir=/data -s3.iam.readOnly=false
    ;;
  seaweedfs-server)
    port=8333; health=/healthz; flavor=seaweedfs
    cat >"$work/s3.json" <<JSON
{"identities":[{"name":"admin","credentials":[{"accessKey":"$key","secretKey":"$secret"}],"actions":["Admin","Read","Write","List","Tagging"]}]}
JSON
    chmod 644 "$work/s3.json"
    run_store -v "$work/s3.json:/etc/seaweedfs/s3.json:ro" \
      "$seaweed_image" server -dir=/data -s3 -s3.port=8333 -s3.config=/etc/seaweedfs/s3.json -s3.iam.readOnly=false
    ;;
  rustfs)
    port=9000; health=/health; flavor=rustfs
    run_store -e RUSTFS_ACCESS_KEY="$key" -e RUSTFS_SECRET_KEY="$secret" \
      -e RUSTFS_VOLUMES=/data "$rustfs_image"
    ;;
  minio)
    port=9000; health=/minio/health/live; flavor=minio
    run_store -e MINIO_ROOT_USER="$key" -e MINIO_ROOT_PASSWORD="$secret" \
      "$minio_image"
    ;;
  *) echo "s3-compat: unknown store '$store' (seaweedfs, seaweedfs-server, rustfs, minio)" >&2; exit 2 ;;
esac

echo "s3-compat: waiting for $store" >&2
n=0
until docker run --rm --network "$network" curlimages/curl:8.14.1 -fsS -o /dev/null "http://$container:$port$health" >/dev/null 2>&1; do
  n=$((n + 1))
  if [ "$n" -ge 60 ]; then
    docker logs "$container" >&2 || true
    echo "s3-compat: $store did not become ready" >&2
    exit 1
  fi
  sleep 2
done
# the S3 gateway of `weed mini` starts after the master and the filer
sleep 3

if [ -n "$php_image" ]; then
  mounts="-v $(cd "$api" && pwd):/var/www/html"
  [ -z "$vendor" ] || mounts="$mounts -v $(cd "$vendor" && pwd):/var/www/html/vendor:ro"
  # shellcheck disable=SC2086
  docker run --rm --network "$network" $mounts -w /var/www/html \
    -e S3_COMPAT=1 -e "S3_COMPAT_ENDPOINT=http://$container:$port" -e "S3_COMPAT_KEY=$key" -e "S3_COMPAT_SECRET=$secret" -e "S3_COMPAT_FLAVOR=$flavor" \
    "$php_image" php -d memory_limit=512M ./vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --display-skipped --do-not-cache-result "$@" tests/S3Compat
else
  cd "$api"
  S3_COMPAT=1 S3_COMPAT_ENDPOINT="http://127.0.0.1:$host_port" S3_COMPAT_KEY="$key" S3_COMPAT_SECRET="$secret" S3_COMPAT_FLAVOR="$flavor" \
    php -d memory_limit=512M ./vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php --display-skipped --do-not-cache-result "$@" tests/S3Compat
fi
