#!/bin/sh
# Adds one host name as a PHP site on MyDevil whose document root is the shared app (api/public).
# This is the "many domains, one app directory" setup: every host gets its own vhost, and the app picks
# the tenant by the Host header (laravel-multidomain, .env.<host>).
#
#   sh add-vhost.sh <host>
#
# Optional: ULAMS_ORIGIN_CERT and ULAMS_ORIGIN_KEY (a Cloudflare Origin CA certificate that covers the
# host) are installed for the host with SNI. Without them, issue Let's Encrypt with the DNS record
# pointing at MyDevil (not proxied) or use Cloudflare SSL mode Full (not strict).
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu

HOST="${1:-}"
[ -n "$HOST" ] || die "usage: sh add-vhost.sh <host>"
need devil
SITE="$HOME/domains/$HOST"

if devil www list 2>/dev/null | grep -qF "$HOST"; then
  say "vhost $HOST exists"
else
  devil www add "$HOST" php
fi

# the site's document root is a symlink to the shared app, so every host runs the same code
if [ ! -L "$SITE/public_html" ]; then
  rm -rf "$SITE/public_html"
  ln -s "$ULAMS_API/public" "$SITE/public_html"
fi

# PHP 8.4 for this site, and room for the app (the default open_basedir is the site directory)
if ! grep -q 'x-httpd-php84' "$SITE/.htaccess" 2>/dev/null; then
  printf 'AddType application/x-httpd-php84 .php\n' >> "$SITE/.htaccess"
fi
cat > "$SITE/.user.ini" <<INI
memory_limit = 768M
max_execution_time = 120
upload_max_filesize = 128M
post_max_size = 130M
display_errors = off
log_errors = on
error_log = $SITE/phperror.log
INI
# the home directory is reachable as /usr/home/LOGIN and /home/LOGIN: list both (PHP compares real paths)
devil www options "$HOST" php_openbasedir "$SITE/public_html:/tmp:/usr/share:/usr/local/share:/dev:$ULAMS_HOME:$(echo "$ULAMS_HOME" | sed 's#^/usr/home#/home#')" || say "warning: php_openbasedir not set; set it in the panel (Websites, Details)"
# TenantProvisioner and the artisan calls of the app use proc_open/exec
devil www options "$HOST" php_exec on || say "warning: php_exec not set"
devil www options "$HOST" sslonly on || true

if [ -n "${ULAMS_ORIGIN_CERT:-}" ] && [ -n "${ULAMS_ORIGIN_KEY:-}" ]; then
  IP="$(devil vhost list public 2>/dev/null | awk '/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/ {print $1; exit}')"
  [ -n "$IP" ] || die "no public IP found in 'devil vhost list public'"
  devil ssl www add "$IP" "$ULAMS_ORIGIN_CERT" "$ULAMS_ORIGIN_KEY" "$HOST"
fi
say "vhost $HOST ready (check: curl -sI https://$HOST/api/health)"
