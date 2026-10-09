#!/usr/bin/env bash
# Creates a tenant: database, role, bucket, env file, migrations, keys, permissions (ulams:tenant:create),
# refreshes the H5P service configuration and, when CLOUDFLARE_API_TOKEN is set, attaches the tenant's
# public file domain to its R2 bucket. Safe to re-run: finished steps are skipped.
#
#   scripts/create-tenant.sh acme --name "Acme Academy" [--users 0] [--demo off] [--theme coffee] [--accent '#C2552D']
#
# Production defaults: demo mode OFF (no password-less login, no hourly reset) and no demo students.
# DNS: with the wildcard records of the install nothing has to be added for a new tenant (the hosts are
# printed below). With ULAMS_HOST_STYLE=nested the certificates must already cover *.admin/api/content
# (Total TLS). The admin user is admin@<slug>.<domain>, its password is TENANT_INITIAL_PASSWORD in .env.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"
ulams_require_env

slug="${1:-}"
[[ -n "$slug" && "$slug" != -* ]] || { sed -n '2,12p' "${BASH_SOURCE[0]}"; exit 2; }
shift
name="" users=0 demo=off theme="" accent=""
while (($#)); do
  case "$1" in
    --name) name="${2:?}"; shift ;;
    --users) users="${2:?}"; shift ;;
    --demo) demo="${2:?}"; shift ;;
    --theme) theme="${2:?}"; shift ;;
    --accent) accent="${2:?}"; shift ;;
    *) ulams_die "unknown option '$1'" ;;
  esac
  shift
done

[[ "$slug" =~ ^[a-z][a-z0-9]{1,29}$ ]] || ulams_die "slug: 2 to 30 lowercase letters and digits, starting with a letter"
case "$slug" in
  api | app | admin | www | storage | minio | ws | metrics | platform | default | test | postgres | docs | files | content | status | mail | cdn | assets)
    ulams_die "'$slug' is reserved (it is, or could become, a platform host)" ;;
esac
[[ "$demo" == on || "$demo" == off ]] || ulams_die "--demo is on or off"

domain="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_DOMAIN)"
scheme="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_SCHEME)"
port="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_PORT_SUFFIX)"
subst() { local v="${1//\{slug\}/$slug}"; printf '%s' "$v"; }
front="$(subst "$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_FRONT_PATTERN)")"
admin="$(subst "$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_ADMIN_PATTERN)")"
api="$(subst "$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_API_PATTERN)")"
content="$(subst "$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_CONTENT_PATTERN)")"
bucket_url_pattern="$(ulams_env_get "$ULAMS_ENV_FILE" TENANCY_BUCKET_PUBLIC_URL)"

args=("$slug" "--users=$users" "--demo=$demo")
[[ -z "$name" ]] || args+=("--name=$name")
[[ -z "$theme" ]] || args+=("--theme=$theme")
[[ -z "$accent" ]] || args+=("--accent=$accent")

ulams_log "ulams:tenant:create $slug"
ulams_artisan ulams:tenant:create "${args[@]}"
ulams_artisan ulams:h5p:export-config >/dev/null

if [[ -n "$bucket_url_pattern" ]]; then
  files_host="$(subst "$bucket_url_pattern")"
  files_host="${files_host#*://}"
  files_host="${files_host%%/*}"
  if [[ -n "${CLOUDFLARE_API_TOKEN:-}" ]]; then
    ulams_log "R2: public domain $files_host for bucket ulams-$slug"
    "$here/cloudflare-setup.sh" --slug "$slug" bucket
  else
    ulams_warn "CLOUDFLARE_API_TOKEN is not set: attach $files_host to the bucket ulams-$slug yourself (R2 > ulams-$slug > Settings > Custom domains), or run: CLOUDFLARE_API_TOKEN=... scripts/cloudflare-setup.sh --slug $slug bucket. Until then the files of this tenant are not publicly readable."
  fi
fi

ulams_log "tenant $slug is ready"
printf '    learner front  %s://%s%s\n    admin panel    %s://%s%s\n    API            %s://%s%s\n    content origin %s://%s%s\n' \
  "$scheme" "$front" "$port" "$scheme" "$admin" "$port" "$scheme" "$api" "$port" "$scheme" "$content" "$port"
echo "    admin user     admin@$slug.$domain  (password: TENANT_INITIAL_PASSWORD in $ULAMS_ENV_FILE; change it at first sign-in)"
echo "    DNS            nothing to add: the wildcard records already cover these hosts"
if ulams_compose exec -T caddy wget -q -O /dev/null --header "Host: $api" "http://127.0.0.1:80/api/health"; then
  echo "    check          $api answers /api/health through Caddy"
else
  ulams_warn "$api did not answer /api/health through Caddy: docker compose logs api caddy"
fi
