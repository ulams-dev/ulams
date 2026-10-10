#!/bin/bash
# PHP runtime profile of the api container, called by init.sh / init_multidomains.sh.
#
#   php-profile.sh prepare   before the first artisan call
#   php-profile.sh autoload  rebuild the optimized class map (composer dump-autoload)
#   php-profile.sh manifest  rebuild the package manifest
#   php-profile.sh cache     after migrations and tenant env files are in place
#
# Profiles:
#  - development (default): opcache re-checks file timestamps, no framework caches, so edits on
#    the bind mount apply at once. A previous demo run's caches are removed.
#  - demo (DEMO_PERF=1, `make demo-up`): production PHP settings (no timestamp checks, JIT,
#    APP_DEBUG=false), cached config/routes/events per domain, vendor/ and bootstrap/cache in
#    named volumes (vendor installed without dev dependencies). Apply edits with `make dev-reload`.
#  Development and demo size php-fpm and opcache for a laptop (local_sizing): pm = ondemand with
#  PHP_FPM_MAX_CHILDREN (default 8) children that exit after PHP_FPM_IDLE_TIMEOUT (default 20s)
#  without a request, instead of the image default of idle children held for ever. The production
#  image keeps its own settings.
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

# Rebuilds the optimized class map from the composer.json and packages on disk, so a package
# merged since the last start (new `Ulams\` namespace, new provider) is found without a manual
# `composer dump-autoload`. --no-scripts: no package discovery here (rebuild_manifest does it).
# The demo profile is authoritative, like its install. Composer infers dev or no-dev from the last
# install, so the class map always matches the vendor/ that is there (forcing --no-dev would drop
# the dev packages' classes while the manifest still lists their providers).
dump_autoload() {
  local flags=(-o --no-scripts --no-interaction)
  if demo; then
    flags=(-a --no-scripts --no-interaction)
  fi
  if composer dump-autoload "${flags[@]}" >/dev/null 2>&1; then
    echo "php-profile: autoload rebuilt (composer dump-autoload ${flags[*]})"
  else
    echo "php-profile: composer dump-autoload failed; keeping the autoloader that is there" >&2
  fi
}

# php-fpm pool and opcache memory for one developer machine (see the header); not for the production image
local_sizing() {
  if [ "${ULAMS_OPTIMIZE:-false}" = "true" ]; then
    return
  fi
  printf '[www]\npm = ondemand\npm.max_children = %s\npm.process_idle_timeout = %s\npm.max_requests = 500\n' \
    "${PHP_FPM_MAX_CHILDREN:-8}" "${PHP_FPM_IDLE_TIMEOUT:-20s}" > "$FPM_D/zz-ulams-sizing.conf"
  if demo; then
    # the production ini above asks for 256 MB opcache, 32 MB interned strings and a 64 MB JIT buffer
    cp docker/conf/php/ulams-demo-sizing-php.ini "$CONF_D/zz-ulams-demo-sizing-php.ini"
  fi
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
    rm -f "$CONF_D/zz-ulams-production-php.ini" "$CONF_D/zz-ulams-demo-php.ini" "$CONF_D/zz-ulams-demo-sizing-php.ini" "$FPM_D/zz-ulams-demo.conf"
  fi
  local_sizing
  dump_autoload
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
  autoload) dump_autoload ;;
  manifest) rebuild_manifest ;;
  cache) cache ;;
  *) echo "usage: $0 prepare|autoload|manifest|cache" >&2; exit 2 ;;
esac
