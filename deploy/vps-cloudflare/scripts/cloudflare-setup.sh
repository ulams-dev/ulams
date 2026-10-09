#!/usr/bin/env bash
# Configures Cloudflare for an ulams install through the API: SSL mode, tunnel, DNS records, R2
# buckets with their custom domain and CORS, and the cache rules. Idempotent: every step looks
# before it writes, so it is safe to run again. The manual checklist of the same steps is in the
# guide (Operators, "Install on a VPS with Cloudflare", "Cloudflare setup").
#
#   export CLOUDFLARE_API_TOKEN=...          # never in a file, never printed by this script
#   scripts/cloudflare-setup.sh [options] all
#   scripts/cloudflare-setup.sh --dry-run all   # prints the plan; needs no token and no network
#
# Steps:  check  ssl  tunnel  dns  r2  cache  all   and  bucket (with --slug <tenant>: public domain of ulams-<slug>)
# Options:
#   --dry-run            print what would change, call nothing
#   --origin-ip IP       origin_ca mode: proxied A records to this address instead of the tunnel
#   --docs-target HOST   also create docs.<domain> as a proxied CNAME to HOST (GitHub Pages:
#                        <org>.github.io; Cloudflare Pages: <project>.pages.dev)
#   --slug SLUG          step `bucket`: attach <slug>-files.<domain> to the bucket ulams-<slug>
#   --total-tls          nested host style: enable Total TLS (needs the Advanced Certificate Manager add-on)
#
# Token permissions (create it at dash.cloudflare.com/profile/api-tokens, scope it to this zone and account):
#   Zone: Zone Read, DNS Edit, Zone Settings Edit, SSL and Certificates Edit, Cache Rules Edit
#   Account: Cloudflare Tunnel Edit, Workers R2 Storage Edit
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"

dry=0 origin_ip="" docs_target="" total_tls=0 slug="" steps=()
while (($#)); do
  case "$1" in
    --dry-run) dry=1 ;;
    --origin-ip) origin_ip="${2:?--origin-ip needs an address}"; shift ;;
    --docs-target) docs_target="${2:?--docs-target needs a host}"; shift ;;
    --total-tls) total_tls=1 ;;
    --slug) slug="${2:?--slug needs a tenant slug}"; shift ;;
    -h | --help) sed -n '2,28p' "${BASH_SOURCE[0]}"; exit 0 ;;
    check | ssl | tunnel | dns | r2 | cache | bucket | all) steps+=("$1") ;;
    *) ulams_die "unknown argument '$1' (see --help)" ;;
  esac
  shift
done
((${#steps[@]})) || steps=(all)

command -v jq >/dev/null || ulams_die "jq is required"
command -v curl >/dev/null || ulams_die "curl is required"

env_file="$ULAMS_ENV_FILE"
[[ -f "$env_file" ]] || env_file="$ULAMS_DEPLOY_DIR/.env.example"
domain="$(ulams_env_get "$env_file" ULAMS_DOMAIN)"
style="$(ulams_env_get "$env_file" ULAMS_HOST_STYLE)"
mode="$(ulams_env_get "$env_file" ULAMS_ORIGIN_MODE)"
platform_bucket="$(ulams_env_get "$env_file" S3_PLATFORM_BUCKET)"
backup_bucket="$(ulams_env_get "$env_file" BACKUP_BUCKET)"
account_id="${CLOUDFLARE_ACCOUNT_ID:-$(ulams_env_get "$env_file" CLOUDFLARE_ACCOUNT_ID)}"
ulams_is_placeholder "$account_id" && account_id=""
: "${platform_bucket:=ulams}" "${backup_bucket:=ulams-backups}"
tunnel_name="ulams-${domain//./-}"
api="https://api.cloudflare.com/client/v4"
zone_id="<zone-id>"

if ((dry)); then
  : "${account_id:=<account-id>}"
  ulams_log "dry run: nothing is sent to Cloudflare"
else
  [[ -n "${CLOUDFLARE_API_TOKEN:-}" ]] || ulams_die "CLOUDFLARE_API_TOKEN is not set (export it in this shell; do not put it in .env)"
fi

# cf METHOD PATH [JSON]: prints the response body, stops on an API error. The token goes to curl
# through a config file on a pipe, so it never appears in the process list or in the output.
cf() {
  local method="$1" path="$2" body="${3:-}" out
  if ((dry)); then
    if [[ "$method" == GET ]]; then echo '{"success":true,"result":[]}'; return; fi
    printf '    DRY  %s %s %s\n' "$method" "$path" "$(jq -c 'del(.tunnel_secret)' <<<"${body:-{\}}" 2>/dev/null | cut -c1-200)" >&2
    echo '{"success":true,"result":{"id":"<id>"}}'
    return
  fi
  out="$(curl -sS --fail-with-body -X "$method" "$api$path" \
    -K <(printf 'header = "Authorization: Bearer %s"\n' "$CLOUDFLARE_API_TOKEN") \
    -H 'Content-Type: application/json' ${body:+--data-binary @-} <<<"$body" 2>&1)" || {
    [[ -n "${CF_QUIET:-}" ]] && return 1
    printf '%s\n' "$out" | jq -r '.errors[]? | "  cloudflare error \(.code): \(.message)"' >&2 2>/dev/null || printf '%s\n' "$out" >&2
    ulams_die "$method $path failed"
  }
  printf '%s' "$out"
}

resolve_zone() {
  ((dry)) && return 0
  local res
  res="$(cf GET "/zones?name=$domain")"
  zone_id="$(jq -r '.result[0].id // empty' <<<"$res")"
  [[ -n "$zone_id" ]] || ulams_die "zone $domain not found in this token's scope"
  [[ -n "$account_id" ]] || account_id="$(jq -r '.result[0].account.id // empty' <<<"$res")"
}

do_check() {
  ulams_log "token and zone"
  if ((dry)); then echo "    would verify the token and look up the zone $domain"; return; fi
  cf GET /user/tokens/verify | jq -r '"    token: " + .result.status'
  echo "    zone: $domain ($zone_id), account: $account_id"
}

do_ssl() {
  ulams_log "SSL/TLS: Full (strict), HTTPS only, TLS 1.2+"
  cf PATCH "/zones/$zone_id/settings/ssl" '{"value":"strict"}' >/dev/null
  cf PATCH "/zones/$zone_id/settings/always_use_https" '{"value":"on"}' >/dev/null
  cf PATCH "/zones/$zone_id/settings/min_tls_version" '{"value":"1.2"}' >/dev/null
  cf PATCH "/zones/$zone_id/settings/tls_1_3" '{"value":"on"}' >/dev/null
  cf PATCH "/zones/$zone_id/settings/automatic_https_rewrites" '{"value":"on"}' >/dev/null
  if [[ "$mode" == origin_ca ]]; then
    ulams_log "Authenticated Origin Pulls (zone level)"
    cf PUT "/zones/$zone_id/origin_tls_client_auth/settings" '{"enabled":true}' >/dev/null
  fi
  if [[ "$style" == nested ]]; then
    if ((total_tls)); then
      ulams_log "Total TLS (nested host layout; needs Advanced Certificate Manager)"
      cf POST "/zones/$zone_id/acm/total_tls" '{"enabled":true,"certificate_authority":"lets_encrypt"}' >/dev/null
    else
      ulams_warn "ULAMS_HOST_STYLE=nested: hosts such as acme.admin.$domain are not covered by Universal SSL. Order Advanced Certificate Manager and re-run with --total-tls, or use ULAMS_HOST_STYLE=flat."
    fi
  fi
}

tunnel_id=""
do_tunnel() {
  [[ "$mode" == tunnel ]] || { echo "    ULAMS_ORIGIN_MODE is $mode: no tunnel needed"; return; }
  ulams_log "Cloudflare Tunnel '$tunnel_name'"
  local res secret creds="$ULAMS_DEPLOY_DIR/cloudflared/credentials.json"
  res="$(cf GET "/accounts/$account_id/cfd_tunnel?name=$tunnel_name&is_deleted=false")"
  tunnel_id="$(jq -r '.result[0].id // empty' <<<"$res")"
  if [[ -n "$tunnel_id" ]]; then
    echo "    exists: $tunnel_id"
    [[ -f "$creds" ]] || ulams_warn "the tunnel exists but $creds is missing; Cloudflare shows the secret only at creation. Delete the tunnel in Zero Trust > Networks > Tunnels and run this step again, or copy credentials.json from the machine that created it."
  else
    secret="$(openssl rand -base64 32)"
    res="$(cf POST "/accounts/$account_id/cfd_tunnel" "$(jq -nc --arg n "$tunnel_name" --arg s "$secret" '{name:$n,tunnel_secret:$s,config_src:"local"}')")"
    tunnel_id="$(jq -r '.result.id' <<<"$res")"
    if ((!dry)); then
      (umask 077 && jq -n --arg a "$account_id" --arg s "$secret" --arg t "$tunnel_id" '{AccountTag:$a,TunnelSecret:$s,TunnelID:$t}' >"$creds")
      echo "    created $tunnel_id, credentials written to cloudflared/credentials.json (mode 600)"
    fi
  fi
  if ((!dry)) && [[ -f "$ULAMS_ENV_FILE" ]]; then
    ulams_env_set "$ULAMS_ENV_FILE" CLOUDFLARE_TUNNEL_ID "$tunnel_id"
    ulams_env_set "$ULAMS_ENV_FILE" CLOUDFLARE_ACCOUNT_ID "$account_id"
  fi
}

# dns_upsert TYPE NAME CONTENT
dns_upsert() {
  local type="$1" name="$2" content="$3" res id body
  body="$(jq -n --arg t "$type" --arg n "$name" --arg c "$content" '{type:$t,name:$n,content:$c,proxied:true,ttl:1}')"
  res="$(cf GET "/zones/$zone_id/dns_records?name=$name")"
  id="$(jq -r '.result[0].id // empty' <<<"$res")"
  if [[ -n "$id" ]]; then
    cf PUT "/zones/$zone_id/dns_records/$id" "$body" >/dev/null
    echo "    updated  $type $name -> $content"
  else
    cf POST "/zones/$zone_id/dns_records" "$body" >/dev/null
    echo "    created  $type $name -> $content"
  fi
}

do_dns() {
  ulams_log "DNS records (proxied)"
  local names=("@" "*") type content
  [[ "$style" == nested ]] && names+=("*.admin" "*.api" "*.content")
  if [[ "$mode" == tunnel ]]; then
    [[ -n "$tunnel_id" ]] || tunnel_id="$(ulams_env_get "$ULAMS_ENV_FILE" CLOUDFLARE_TUNNEL_ID)"
    ((dry)) && : "${tunnel_id:=<tunnel-id>}"
    [[ -n "$tunnel_id" && "$tunnel_id" != GENERATE ]] || ulams_die "no tunnel id: run the tunnel step first"
    type=CNAME content="$tunnel_id.cfargotunnel.com"
  else
    [[ -n "$origin_ip" ]] || ulams_die "origin_ca mode needs --origin-ip"
    type=A content="$origin_ip"
    [[ "$origin_ip" == *:* ]] && type=AAAA
  fi
  local n fqdn
  for n in "${names[@]}"; do
    fqdn="$n.$domain"
    [[ "$n" == "@" ]] && fqdn="$domain"
    dns_upsert "$type" "$fqdn" "$content"
  done
  if [[ -n "$docs_target" ]]; then
    dns_upsert CNAME "docs.$domain" "$docs_target"
  else
    echo "    docs.$domain: not created (pass --docs-target <org>.github.io or <project>.pages.dev); the wildcard would send it to this host"
  fi
  echo "    files.$domain is created by the r2 step (custom domain of the platform bucket)"
}

r2_bucket() {
  local b="$1" res
  res="$(CF_QUIET=1 cf GET "/accounts/$account_id/r2/buckets/$b" || true)"
  if ((!dry)) && jq -e '.success == true' <<<"$res" >/dev/null 2>&1; then
    echo "    bucket $b exists"
  else
    cf POST "/accounts/$account_id/r2/buckets" "$(jq -n --arg n "$b" '{name:$n}')" >/dev/null
    echo "    bucket $b created"
  fi
}

# r2_public_domain BUCKET HOST: public read through a custom domain (also creates the DNS record)
r2_public_domain() {
  local b="$1" host="$2" res
  res="$(cf GET "/accounts/$account_id/r2/buckets/$b/domains/custom")"
  if jq -e --arg h "$host" '.result.domains[]? | select(.domain == $h)' <<<"$res" >/dev/null 2>&1; then
    echo "    domain $host already attached to $b"
  else
    cf POST "/accounts/$account_id/r2/buckets/$b/domains/custom" \
      "$(jq -n --arg d "$host" --arg z "$zone_id" '{domain:$d,zoneId:$z,enabled:true,minTLS:"1.2"}')" >/dev/null
    echo "    domain $host attached to $b"
  fi
  # Public files are read cross-origin by the front and the players: GET and HEAD from anywhere
  cf PUT "/accounts/$account_id/r2/buckets/$b/cors" \
    '{"rules":[{"allowed":{"origins":["*"],"methods":["GET","HEAD"],"headers":["*"]},"maxAgeSeconds":3600}]}' >/dev/null
  echo "    CORS on $b: GET/HEAD from any origin"
}

do_r2() {
  ulams_log "R2: $platform_bucket (public, files.$domain) and $backup_bucket (private)"
  r2_bucket "$platform_bucket"
  r2_public_domain "$platform_bucket" "files.$domain"
  r2_bucket "$backup_bucket"
  echo "    Tenant buckets (ulams-<slug>) and their <slug>-files.$domain domains are made by scripts/create-tenant.sh."
  echo "    The S3 access key for the app is created in the dashboard (R2 > Manage API tokens > Admin Read & Write); it cannot be created with this token."
}

do_cache() {
  ulams_log "Cache rules (rules named 'ulams: ...' are replaced, other rules are kept)"
  local api_hosts
  if [[ "$style" == nested ]]; then
    api_hosts="ends_with(http.host, \".api.$domain\") or http.host eq \"api.$domain\""
  else
    api_hosts="ends_with(http.host, \"-api.$domain\") or http.host eq \"api.$domain\""
  fi
  local rules current kept
  rules="$(jq -n --arg api "$api_hosts" '[
    {
      description: "ulams: hashed front assets (immutable, 1 year)",
      expression: "starts_with(http.request.uri.path, \"/_astro/\")",
      action: "set_cache_settings",
      action_parameters: { cache: true, edge_ttl: { mode: "override_origin", default: 31536000 }, browser_ttl: { mode: "respect_origin" } }
    },
    {
      description: "ulams: public catalogue (origin s-maxage; anonymous GET only)",
      expression: ("(" + $api + ") and http.request.method eq \"GET\" and starts_with(http.request.uri.path, \"/api/\") and not any(http.request.headers.names[*] eq \"authorization\") and not http.request.uri.query contains \"_token=\""),
      action: "set_cache_settings",
      action_parameters: {
        cache: true,
        edge_ttl: { mode: "bypass_by_default" },
        browser_ttl: { mode: "respect_origin" },
        vary: { default: { action: "passthrough" } }
      }
    }
  ]')"
  # 404 = no entry point ruleset yet
  current="$(CF_QUIET=1 cf GET "/zones/$zone_id/rulesets/phases/http_request_cache_settings/entrypoint" || echo '{"result":{"rules":[]}}')"
  kept="$(jq -c '[.result.rules[]? | select((.description // "") | startswith("ulams: ") | not) | del(.id, .version, .last_updated, .ref)]' <<<"$current" 2>/dev/null || echo '[]')"
  [[ -n "$kept" ]] || kept='[]'
  cf PUT "/zones/$zone_id/rulesets/phases/http_request_cache_settings/entrypoint" \
    "$(jq -n --argjson a "$kept" --argjson b "$rules" '{rules: ($a + $b)}')" >/dev/null
  echo "    2 rules applied (static assets, public catalogue)"
}

do_bucket() {
  [[ "$slug" =~ ^[a-z][a-z0-9]{1,29}$ ]] || ulams_die "bucket: pass --slug <tenant slug>"
  ulams_log "R2: public domain of the bucket ulams-$slug"
  r2_public_domain "ulams-$slug" "$slug-files.$domain"
}

resolve_zone
for s in "${steps[@]}"; do
  case "$s" in
    all) do_check; do_ssl; do_tunnel; do_dns; do_r2; do_cache ;;
    check) do_check ;;
    ssl) do_ssl ;;
    tunnel) do_tunnel ;;
    dns) do_dns ;;
    r2) do_r2 ;;
    cache) do_cache ;;
    bucket) do_bucket ;;
  esac
done
ulams_log "done"
