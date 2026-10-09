# Shared by the scripts in this directory (sourced, POSIX sh: works with FreeBSD /bin/sh and bash).
# Layout under $ULAMS_HOME (default ~/ulams): api/ (the Laravel app), bin/ (these scripts),
# run/ (locks), logs/, backups/, releases/ (uploaded tarballs).

ULAMS_HOME="${ULAMS_HOME:-$HOME/ulams}"
ULAMS_API="$ULAMS_HOME/api"
ULAMS_PHP="${ULAMS_PHP:-php84}"

say() { printf '%s\n' "$*"; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

need() {
  command -v "$1" >/dev/null 2>&1 || die "$1 not found in PATH ($PATH)"
}

# Prints the host names that have their own workers: the platform (as a single `-`) when MULTI_DOMAINS is
# unset, then every tenant listed by api/domains.sh (reads config/domain.php and the .env.<host> files;
# it calls `php`, so ~/bin/php must point at php84, which install.sh arranges).
ulams_domains() {
  if [ -z "${MULTI_DOMAINS:-}" ]; then
    printf -- '-\n'
  fi
  ( cd "$ULAMS_API" && sh ./domains.sh 2>/dev/null )
}

artisan() {
  ( cd "$ULAMS_API" && "$ULAMS_PHP" artisan "$@" )
}

# Rebuilds the config, route, event and view caches of the platform and every tenant
# (`optimize` per domain, each with its own .env.<host>), like api/optimize.sh in the image.
post_deploy() {
  ( cd "$ULAMS_API" && "$ULAMS_PHP" artisan optimize --no-interaction ) || say "warning: optimize failed for the platform"
  ulams_domains | while read -r domain; do
    [ -n "$domain" ] && [ "$domain" != - ] || continue
    ( cd "$ULAMS_API" && "$ULAMS_PHP" artisan optimize --no-interaction --domain="$domain" ) || say "warning: optimize failed for $domain"
  done
}
