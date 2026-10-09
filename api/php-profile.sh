#!/bin/bash
# PHP runtime profile of the api container, called by init.sh / init_multidomains.sh.
#
#   php-profile.sh prepare   before the first artisan call
#   php-profile.sh cache     after migrations and tenant env files are in place
#
# Profiles:
#  - development (default): opcache re-checks file timestamps, no framework caches, so edits on
#    the bind mount apply at once. A previous demo run's caches are removed.
#  - demo (DEMO_PERF=1, `make demo-up`): production PHP settings (no timestamp checks, JIT,
#    APP_DEBUG=false), cached config/routes/events per domain, vendor/ in a named volume
#    installed without dev dependencies. Apply edits with `make dev-reload`.
#  - production image: ULAMS_OPTIMIZE=true (set in Dockerfile) builds the caches at start;
#    its PHP settings are baked into the image.
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"
CONF_D=/usr/local/etc/php/conf.d
FPM_D=/usr/local/etc/php-fpm.d
demo() { [ "${DEMO_PERF:-0}" = "1" ] || [ "${DEMO_PERF:-}" = "true" ]; }

# The package manifest (packages.php, services.php) lists the providers of the installed vendor
# packages. It must always be built from the vendor/ this container runs: a manifest from
# another vendor (e.g. one with dev packages) breaks every request and artisan itself. So it is
# deleted and rebuilt on every start and every reload, and in the demo profile it lives inside
# the vendor volume (APP_PACKAGES_CACHE / APP_SERVICES_CACHE, docker-compose.demo.yml), never
# in the bind-mounted bootstrap/cache.
rebuild_manifest() {
  local packages="${APP_PACKAGES_CACHE:-$DIR/bootstrap/cache/packages.php}"
  local services="${APP_SERVICES_CACHE:-$DIR/bootstrap/cache/services.php}"
  mkdir -p "$(dirname "$packages")" "$(dirname "$services")"
  rm -f "$packages" "$services"
  php artisan package:discover --no-interaction >/dev/null
  echo "php-profile: package manifest rebuilt ($packages)"
}

prepare() {
  if demo; then
    echo "php-profile: demo (DEMO_PERF=1)"
    cp docker/conf/php/ulams-production-php.ini "$CONF_D/zz-ulams-production-php.ini"
    cp docker/conf/php/ulams-demo-php.ini "$CONF_D/zz-ulams-demo-php.ini"
    mkdir -p /tmp/opcache
    printf '[www]\nenv[APP_DEBUG] = 0\nenv[LOG_LEVEL] = warning\nenv[APP_PACKAGES_CACHE] = %s\nenv[APP_SERVICES_CACHE] = %s\n' \
      "${APP_PACKAGES_CACHE:?set in docker-compose.demo.yml}" "${APP_SERVICES_CACHE:?set in docker-compose.demo.yml}" > "$FPM_D/zz-ulams-demo.conf"
    # vendor/ is a named volume here (docker-compose.demo.yml): (re)install when composer.lock changed
    lock_hash="$(sha1sum composer.lock | cut -d' ' -f1)"
    if [ ! -f vendor/autoload.php ] || [ "$(cat vendor/.ulams-lock-hash 2>/dev/null)" != "$lock_hash" ]; then
      echo "php-profile: installing vendor/ (no dev dependencies, authoritative classmap)"
      if composer install --no-dev --no-scripts --no-interaction --no-progress --classmap-authoritative; then
        echo "$lock_hash" > vendor/.ulams-lock-hash
      else
        echo "php-profile: composer install failed; keeping the vendor/ that is there" >&2
      fi
    fi
  else
    rm -f "$CONF_D/zz-ulams-production-php.ini" "$CONF_D/zz-ulams-demo-php.ini" "$FPM_D/zz-ulams-demo.conf"
  fi
  rebuild_manifest
}

cache() {
  if demo || [ "${ULAMS_OPTIMIZE:-false}" = "true" ]; then
    ./optimize.sh
  else
    ./optimize.sh --clear
  fi
}

case "${1:-}" in
  prepare) prepare ;;
  manifest) rebuild_manifest ;;
  cache) cache ;;
  *) echo "usage: $0 prepare|manifest|cache" >&2; exit 2 ;;
esac
