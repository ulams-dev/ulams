#!/bin/sh
# Adds the three MyDevil hosts of one tenant in the flat staging layout of STAGING-NOTES.md:
#   <slug>-staging-api.<zone>    PHP site on the shared app (add-vhost.sh)
#   <slug>-staging-admin.<zone>  PHP site whose document root is the static admin build (~/ulams/admin)
#   <slug>-staging.<zone>        Node site (Passenger, one process) running the Astro front in ~/ulams/web
# The content host (<slug>-staging-content) is a Cloudflare Worker domain, not a MyDevil site.
# Needs the Cloudflare Origin CA certificate in ~/ulams/tls (origin.pem, origin.key; covers *.<zone>).
# Safe to re-run: existing sites are kept.
#
#   sh add-flat-hosts.sh <slug> [zone]        (zone defaults to ulams.app)
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
exists() { devil www list 2>/dev/null | grep -qF "$1 "; }
cert() { devil ssl www add "$IP" "$CERT" "$KEY" "$1" 2>&1 | grep -v '^$' || true; }

API="$SLUG-staging-api.$ZONE"; ADMIN="$SLUG-staging-admin.$ZONE"; FRONT="$SLUG-staging.$ZONE"

say "-- api $API"
ULAMS_ORIGIN_CERT="$CERT" ULAMS_ORIGIN_KEY="$KEY" sh "$DIR/add-vhost.sh" "$API"

say "-- admin $ADMIN"
exists "$ADMIN" || devil www add "$ADMIN" php
rm -rf "$HOME/domains/$ADMIN/public_html"
ln -s "$ULAMS_HOME/admin" "$HOME/domains/$ADMIN/public_html"
devil www options "$ADMIN" sslonly on || true
cert "$ADMIN"

say "-- front $FRONT"
exists "$FRONT" || devil www add "$FRONT" nodejs /usr/local/bin/node22 production
APP="$HOME/domains/$FRONT/public_nodejs"
rm -f "$APP/public/index.html"
# the settings only (no secrets): the host patterns cover every tenant, the same file as the first front
cp "$HOME/domains/staging.$ZONE/public_nodejs/app.js" "$APP/app.js"
devil www options "$FRONT" processes 1 || true
devil www options "$FRONT" sslonly on || true
cert "$FRONT"
devil www restart "$FRONT" || true
say "hosts of $SLUG ready"
