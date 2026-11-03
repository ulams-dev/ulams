#!/usr/bin/env bash
# Offline checks of the single-server example that the docs site publishes
# (front/docs-site/examples/production): docker compose config, the Caddyfile in both content-domain
# layouts (a separate registrable domain, and a subdomain such as content.<ULAMS_DOMAIN>) and that
# compose passes every variable the Caddyfile reads. Needs Docker; uses the caddy image that
# deploy/vps-cloudflare/compose.yml pins. Touches nothing on the machine and starts no service.
#   deploy/vps-cloudflare/scripts/validate-docs-example.sh
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
example="$(cd "$root/../../front/docs-site/examples/production" && pwd)"
caddy_image="$(sed -n 's/^ *image: \(caddy:.*\)$/\1/p' "$root/compose.yml" | head -1)"
failed=0
ok() { printf 'ok    %s\n' "$1"; }
bad() { printf 'FAIL  %s\n' "$1"; failed=1; }

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

if docker compose --env-file "$example/.env.example" -f "$example/docker-compose.yml" config -q 2>"$tmp/err"; then
  ok "docs example: docker compose config"
else
  bad "docs example: docker compose config: $(cat "$tmp/err")"
fi

# every variable the Caddyfile reads must reach the container (an unset one only fails at runtime)
caddy_env="$(docker compose --env-file "$example/.env.example" -f "$example/docker-compose.yml" config --format json | jq -r '.services.caddy.environment | keys[]')"
vars="$(grep -oE '\{\$[A-Z_]+' "$example/Caddyfile" | tr -d '{$' | sort -u)"
missing=""
while IFS= read -r name; do
  grep -qx "$name" <<<"$caddy_env" || missing="$missing $name"
done <<<"$vars"
if [[ -z "$missing" ]]; then ok "docs example: Caddyfile variables are all passed by docker-compose.yml"; else bad "docs example: Caddyfile reads variables docker-compose.yml does not pass to caddy:$missing"; fi

# the two layouts of ULAMS_CONTENT_DOMAIN the Caddyfile header describes
validate() {
  local layout="$1" domain="$2" content="$3"
  local envargs=()
  while IFS= read -r name; do envargs+=(-e "$name"); done <<<"$vars"
  if docker run --rm "${envargs[@]}" -e "ULAMS_DOMAIN=$domain" -e "ULAMS_CONTENT_DOMAIN=$content" -e ACME_EMAIL=ops@example.com \
    -e ULAMS_CSP_HEADER=Content-Security-Policy-Report-Only \
    -v "$example/Caddyfile:/etc/caddy/Caddyfile:ro" "$caddy_image" caddy validate --config /etc/caddy/Caddyfile >"$tmp/out" 2>&1; then
    ok "docs example: caddy validate ($layout)"
  else
    bad "docs example: caddy validate ($layout): $(tail -5 "$tmp/out")"
  fi
}
validate "content on a separate domain" lms.example.com example-content.net
validate "content on a subdomain" lms.example.com content.lms.example.com

exit "$failed"
