#!/bin/sh
# Adds one tenant on MyDevil: its PostgreSQL database, its API vhost (a PHP site whose document root
# is the shared app) and the tenant itself. Run on the account over SSH, interactive (devil asks for
# the database password). Re-running after a failure is fine.
#
#   sh add-tenant.sh <slug> "<Display name>" [theme]
#
# Reads TENANCY_API_HOST and TENANCY_DATABASE from api/.env: the host is `<slug>.api.example.com`
# and the database `m1234_<slug>` in the template. Only the API host is created on MyDevil: the front,
# admin and content hosts are on Cloudflare (README, section 7).
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu
PATH="$HOME/bin:$PATH"; export PATH

SLUG="${1:-}"; NAME="${2:-}"; THEME="${3:-}"
[ -n "$SLUG" ] && [ -n "$NAME" ] || die 'usage: sh add-tenant.sh <slug> "<Display name>" [theme]'
case "$SLUG" in *[!a-z0-9]* ) die "the slug is lowercase letters and digits only" ;; esac
need devil

envval() { sed -n "s/^$1=//p" "$ULAMS_API/.env" | tail -1 | tr -d "\"'"; }
API_HOST="$(envval TENANCY_API_HOST | sed "s/{slug}/$SLUG/")"
DB_NAME="$(envval TENANCY_DATABASE | sed "s/{slug}/$SLUG/")"
[ -n "$API_HOST" ] && [ -n "$DB_NAME" ] || die "TENANCY_API_HOST or TENANCY_DATABASE is not set in .env"

DBPASS="${ULAMS_TENANT_DB_PASSWORD:-}"
if [ -z "$DBPASS" ]; then
  DBPASS="$(openssl rand -hex 16)"
  say "Generated database password for $DB_NAME (shown once, also stored encrypted in the tenant registry): $DBPASS"
fi

say "-- 1/3 database $DB_NAME"
if devil pgsql list 2>/dev/null | grep -q "$DB_NAME"; then
  say "exists"
else
  say "devil asks for the password: paste the one above"
  devil pgsql db add "$DB_NAME"
fi

say "-- 2/3 vhost $API_HOST"
sh "$DIR/add-vhost.sh" "$API_HOST"

say "-- 3/3 tenant"
cd "$ULAMS_API"
if [ -n "$THEME" ]; then
  "$ULAMS_PHP" artisan ulams:tenant:create "$SLUG" --name="$NAME" --theme="$THEME" --db-password="$DBPASS"
else
  "$ULAMS_PHP" artisan ulams:tenant:create "$SLUG" --name="$NAME" --db-password="$DBPASS"
fi
post_deploy
say "Tenant $SLUG: https://$API_HOST. Now add its Cloudflare hosts (README, section 7)."
