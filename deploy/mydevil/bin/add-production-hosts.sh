#!/bin/sh
# Adds the production host names next to the staging ones on the same MyDevil account. Nothing is removed or
# changed for the staging hosts: every new host is a vhost on the same apps, databases and directories.
#
#   api.<zone>  admin.<zone>  h5p.<zone>  pdf.<zone>      (platform)
#   <slug>-api.<zone>  <slug>-admin.<zone>  <slug>.<zone>  (each tenant)
#
# The platform landing (<zone> itself) is a Cloudflare Pages site and the content and storage hosts are Cloudflare
# Workers, so none of them is created here. A new Node site gets the app.js of its staging twin with the host
# names rewritten, the PHP sites get the shared app (add-vhost.sh) and the admin sites the static admin build.
# Safe to re-run.
#
#   sh add-production-hosts.sh [zone] [slug ...]     (zone defaults to ulams.app, slugs default to the six demos)
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu

ZONE="${1:-ulams.app}"; [ $# -gt 0 ] && shift
SLUGS="${*:-coffee oncall nightsky gravity poland ulam}"
need devil
CERT="$ULAMS_HOME/tls/origin.pem"; KEY="$ULAMS_HOME/tls/origin.key"
[ -f "$CERT" ] && [ -f "$KEY" ] || die "$CERT / $KEY not found"
IP="$(public_ip)"
[ -n "$IP" ] || die "no public IP in 'devil vhost list public'"
exists() { devil www list 2>/dev/null | awk -v h="$1" '$1 == h { found = 1 } END { exit !found }'; }
cert() { devil ssl www add "$IP" "$CERT" "$KEY" "$1" 2>&1 | grep -v '^$' || true; }

# rewrites the host names of a staging app.js to the production names
production_names() {
  sed -e "s#{slug}-staging-#{slug}-#g" \
      -e "s#{slug}-staging\\.$ZONE#{slug}.$ZONE#g" \
      -e "s#staging-storage\\.$ZONE#storage.$ZONE#g" \
      -e "s#staging-api\\.$ZONE#api.$ZONE#g" \
      -e "s#staging-admin\\.$ZONE#admin.$ZONE#g" \
      -e "s#https://staging\\.$ZONE#https://$ZONE#g" \
      -e "s#'staging\\.$ZONE'#'$ZONE'#g" \
      -e "s#ulams staging #ulams #"
}

node_site() { # <host> <staging twin host>
  exists "$1" || devil www add "$1" nodejs /usr/local/bin/node22 production
  APP="$HOME/domains/$1/public_nodejs"
  rm -f "$APP/public/index.html"
  production_names < "$HOME/domains/$2/public_nodejs/app.js" > "$APP/app.js"
  devil www options "$1" processes 1 || true
  devil www options "$1" sslonly on || true
  cert "$1"
  devil www restart "$1" || true
}

say "-- platform api api.$ZONE"
ULAMS_ORIGIN_CERT="$CERT" ULAMS_ORIGIN_KEY="$KEY" sh "$DIR/add-vhost.sh" "api.$ZONE"

say "-- platform admin admin.$ZONE"
exists "admin.$ZONE" || devil www add "admin.$ZONE" php
rm -rf "$HOME/domains/admin.$ZONE/public_html"
ln -s "$ULAMS_HOME/admin" "$HOME/domains/admin.$ZONE/public_html"
devil www options "admin.$ZONE" sslonly on || true
cert "admin.$ZONE"

say "-- h5p h5p.$ZONE"
node_site "h5p.$ZONE" "staging-h5p.$ZONE"
say "-- pdf pdf.$ZONE"
node_site "pdf.$ZONE" "staging-pdf.$ZONE"

for slug in $SLUGS; do
  say "-- tenant $slug"
  ULAMS_HOST_LABEL= sh "$DIR/add-flat-hosts.sh" "$slug" "$ZONE"
  # the generic copy above used the platform front's file; take the tenant twin's, rewritten
  production_names < "$HOME/domains/$slug-staging.$ZONE/public_nodejs/app.js" > "$HOME/domains/$slug.$ZONE/public_nodejs/app.js"
  devil www restart "$slug.$ZONE" || true
done
say "production hosts ready; they answer 404 until the tenants are registered under the new names (cutover step)"
