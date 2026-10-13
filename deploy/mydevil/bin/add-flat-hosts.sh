#!/bin/sh
# Adds the three MyDevil hosts of one tenant in the flat layout of STAGING-NOTES.md (one level under the zone,
# because Cloudflare's free Universal SSL covers only one):
#   <slug><label>-api.<zone>    PHP site on the shared app (add-vhost.sh)
#   <slug><label>-admin.<zone>  PHP site whose document root is the static admin build (~/ulams/admin)
#   <slug><label>.<zone>        Node site (Passenger, one process) running the Astro front in ~/ulams/web
# <label> is ULAMS_HOST_LABEL: `-staging` by default, empty in production (gravity-api.ulams.app, gravity.ulams.app).
# The content host (<slug><label>-content) is a Cloudflare Worker domain, not a MyDevil site.
# Needs the Cloudflare Origin CA certificate in ~/ulams/tls (origin.pem, origin.key; covers *.<zone>).
# Safe to re-run: existing sites are kept.
#
#   [ULAMS_HOST_LABEL=] [ULAMS_FRONT_TEMPLATE=<host>] sh add-flat-hosts.sh <slug> [zone]   (zone defaults to ulams.app)
#   ULAMS_FRONT_TEMPLATE is the host whose app.js a new front copies (default staging.<zone>).
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu

SLUG="${1:-}"; ZONE="${2:-ulams.app}"
[ -n "$SLUG" ] || die "usage: sh add-flat-hosts.sh <slug> [zone]"
case "$SLUG" in *[!a-z0-9]* ) die "the slug is lowercase letters and digits only" ;; esac
need devil
CERT="$ULAMS_HOME/tls/origin.pem"; KEY="$ULAMS_HOME/tls/origin.key"
[ -f "$CERT" ] && [ -f "$KEY" ] || die "$CERT / $KEY not found"
IP="$(public_ip)"
[ -n "$IP" ] || die "no public IP in 'devil vhost list public'"
exists() { devil www list 2>/dev/null | awk -v h="$1" '$1 == h { found = 1 } END { exit !found }'; }
cert() { devil ssl www add "$IP" "$CERT" "$KEY" "$1" 2>&1 | grep -v '^$' || true; }

LABEL="${ULAMS_HOST_LABEL--staging}"
API="$SLUG$LABEL-api.$ZONE"; ADMIN="$SLUG$LABEL-admin.$ZONE"; FRONT="$SLUG$LABEL.$ZONE"

say "-- api $API"
ULAMS_ORIGIN_CERT="$CERT" ULAMS_ORIGIN_KEY="$KEY" sh "$DIR/add-vhost.sh" "$API"

say "-- admin $ADMIN"
exists "$ADMIN" || devil www add "$ADMIN" php
rm -rf "$HOME/domains/$ADMIN/public_html"
ln -s "$ULAMS_HOME/admin" "$HOME/domains/$ADMIN/public_html"
[ -f "$ULAMS_HOME/admin/.htaccess" ] || cp "$DIR/../admin.htaccess" "$ULAMS_HOME/admin/.htaccess" 2>/dev/null || true
devil www options "$ADMIN" sslonly on || true
cert "$ADMIN"

say "-- front $FRONT"
exists "$FRONT" || devil www add "$FRONT" nodejs /usr/local/bin/node22 production
APP="$HOME/domains/$FRONT/public_nodejs"
rm -f "$APP/public/index.html"
# the settings only (no secrets): the host patterns cover every tenant, the same file as the first front
cp "$HOME/domains/${ULAMS_FRONT_TEMPLATE:-staging.$ZONE}/public_nodejs/app.js" "$APP/app.js"
devil www options "$FRONT" processes 1 || true
devil www options "$FRONT" sslonly on || true
cert "$FRONT"
devil www restart "$FRONT" || true
say "hosts of $SLUG ready"
