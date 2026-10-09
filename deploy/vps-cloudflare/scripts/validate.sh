#!/usr/bin/env bash
# Offline checks of the deployment files: compose (tunnel, origin and local), the Caddyfile in both
# host styles and both origin modes, the cloudflared ingress and shellcheck. Needs Docker; pulls the
# caddy, cloudflared and shellcheck images once. Touches nothing on the machine and starts no service.
#   deploy/vps-cloudflare/scripts/validate.sh
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib.sh
. "$root/scripts/lib.sh"

caddy_image="$(sed -n 's/^ *image: \(caddy:.*\)$/\1/p' "$root/compose.yml" | head -1)"
cloudflared_image="$(sed -n 's/^ *image: \(cloudflare\/cloudflared:.*\)$/\1/p' "$root/compose.yml" | head -1)"
failed=0
ok() { printf 'ok    %s\n' "$1"; }
bad() { printf 'FAIL  %s\n' "$1"; failed=1; }

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# --- compose -----------------------------------------------------------------------------------
for files in "compose.yml" "compose.yml compose.origin.yml" "compose.yml compose.local.yml"; do
  args=()
  for f in $files; do args+=(-f "$root/$f"); done
  if docker compose --env-file "$root/.env.example" "${args[@]}" config -q 2>"$tmp/err"; then
    ok "docker compose config ($files)"
  else
    bad "docker compose config ($files): $(cat "$tmp/err")"
  fi
done

# every variable the Caddyfile reads must reach the container (an unset one only fails at runtime)
caddy_env="$(docker compose --env-file "$root/.env.example" -f "$root/compose.yml" config --format json | jq -r '.services.caddy.environment | keys[]')"
missing=""
while IFS= read -r name; do
  grep -qx "$name" <<<"$caddy_env" || missing="$missing $name"
done < <(grep -oE '\{\$[A-Z_]+' "$root/Caddyfile" | tr -d '{$' | sort -u)
if [[ -z "$missing" ]]; then ok "Caddyfile variables are all passed by compose.yml"; else bad "Caddyfile reads variables compose.yml does not pass to caddy:$missing"; fi

# --- Caddyfile ---------------------------------------------------------------------------------
for style in flat nested; do
  for mode in tunnel origin_ca; do
    cp "$root/.env.example" "$tmp/env"
    ulams_write_derived "$tmp/env" ulams.app "$style" "$mode" https ""
    mkdir -p "$tmp/certs"
    # throw-away material so that `caddy validate` can load the origin_ca certificate
    if [[ "$mode" == origin_ca && ! -f "$tmp/certs/origin.pem" ]]; then
      openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=ulams.app" \
        -keyout "$tmp/certs/origin.key" -out "$tmp/certs/origin.pem" 2>/dev/null
      cp "$tmp/certs/origin.pem" "$tmp/certs/authenticated_origin_pull_ca.pem"
    fi
    envargs=()
    while IFS= read -r name; do envargs+=(-e "$name"); done < <(grep -oE '\{\$[A-Z_]+' "$root/Caddyfile" | tr -d '{$' | sort -u)
    if (
      set -a
      # shellcheck disable=SC1090
      . "$tmp/env"
      set +a
      ULAMS_ORIGIN_MODE="$mode"
      export ULAMS_ORIGIN_MODE
      docker run --rm "${envargs[@]}" \
        -v "$root/Caddyfile:/etc/caddy/Caddyfile:ro" -v "$tmp/certs:/certs:ro" \
        "$caddy_image" caddy validate --config /etc/caddy/Caddyfile >"$tmp/out" 2>&1
    ); then
      ok "caddy validate (style $style, mode $mode)"
    else
      bad "caddy validate (style $style, mode $mode): $(tail -5 "$tmp/out")"
    fi
  done
done

# --- cloudflared -------------------------------------------------------------------------------
cp "$root/cloudflared/config.yml" "$tmp/config.yml"
if docker run --rm -v "$tmp:/etc/cloudflared:ro" "$cloudflared_image" \
  tunnel --config /etc/cloudflared/config.yml ingress validate >"$tmp/out" 2>&1; then
  ok "cloudflared ingress validate"
else
  bad "cloudflared ingress validate: $(tail -5 "$tmp/out")"
fi

# --- shell scripts -----------------------------------------------------------------------------
if docker run --rm -v "$root:/mnt:ro" -w /mnt koalaman/shellcheck:stable -x -S warning scripts/*.sh >"$tmp/out" 2>&1; then
  ok "shellcheck scripts/*.sh"
else
  bad "shellcheck: $(cat "$tmp/out")"
fi

exit "$failed"
