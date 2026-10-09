#!/usr/bin/env bash
# Upgrades a running install to a new image tag with as little downtime as the architecture allows.
#
#   scripts/upgrade.sh [TAG|latest] [--skip-backup] [--dry-run]
#
# Order: backup, pin the new tag, pull every image while the old ones keep serving, recreate the stateless
# services one by one (pdf, mjml, web, admin, h5p), then the api (its start runs the platform and tenant
# migrations; requests to the API answer 502 for the length of that restart, typically 30 to 90 seconds,
# while Cloudflare keeps serving cached catalogue responses), then `ulams:upgrade` (the idempotent step
# registry, ADR 0081), the H5P configuration export and the same checks as install.sh. Postgres, Valkey,
# Caddy and cloudflared are only touched when their image changed. Rolling back is `upgrade.sh <previous tag>`
# (ULAMS_VERSION_PREVIOUS in .env); database migrations are not reverted, so restore a backup if a
# migration must be undone.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"
ulams_require_env

tag="" skip_backup=0 dry=0
while (($#)); do
  case "$1" in
    --skip-backup) skip_backup=1 ;;
    --dry-run) dry=1 ;;
    -h | --help) sed -n '2,15p' "${BASH_SOURCE[0]}"; exit 0 ;;
    -*) ulams_die "unknown option '$1'" ;;
    *) tag="$1" ;;
  esac
  shift
done

current="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_VERSION)"
ulams_log "current release: ${current:-unknown}"
if ((dry)); then
  echo "    dry run: would back up, pin ${tag:-latest}, pull, recreate web/admin/pdf/mjml/h5p, then api, then run ulams:upgrade"
  exit 0
fi

if ((!skip_backup)); then
  if [[ -n "$(ulams_env_get "$ULAMS_ENV_FILE" BACKUP_BUCKET)" ]]; then
    "$here/backup.sh"
  else
    ulams_warn "BACKUP_BUCKET is not set: no backup before the upgrade"
  fi
fi

ulams_resolve_version "${tag:-latest}"
new="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_VERSION)"
if [[ "$new" == "$current" ]]; then
  echo "    already on $new; pulling and re-applying (ulams:upgrade is idempotent)"
fi

ulams_log "pulling images (the running containers keep serving)"
ulams_compose pull -q

ulams_log "recreating the stateless services"
for svc in pdf mjml web admin h5p; do
  ulams_compose up -d --no-deps "$svc"
  ulams_wait_healthy "$svc" 36 || ulams_warn "$svc is not healthy yet"
done

ulams_log "recreating the api (migrations run on start)"
ulams_compose up -d --no-deps api
ulams_wait_healthy api 120 || ulams_die "the api did not become healthy: docker compose logs api. Roll back with: scripts/upgrade.sh $(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_VERSION_PREVIOUS)"

ulams_log "everything else (only what changed is recreated)"
ulams_compose up -d --remove-orphans

ulams_log "ulams:upgrade"
ulams_artisan ulams:upgrade
ulams_artisan ulams:h5p:export-config

ulams_log "checks"
fail=0
domain="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_DOMAIN)"
for pair in "api.$domain:/api/health" "api.$domain:/h5p/health" "$domain:/healthz"; do
  good=0
  for _ in 1 2 3 4 5 6 7 8 9 10 11 12; do
    if ulams_compose exec -T caddy wget -q -O /dev/null --header "Host: ${pair%%:*}" "http://127.0.0.1:80${pair#*:}"; then good=1; break; fi
    sleep 5
  done
  if ((good)); then printf '    ok    %s%s\n' "${pair%%:*}" "${pair#*:}"; else printf '    FAIL  %s%s\n' "${pair%%:*}" "${pair#*:}"; fail=1; fi
done
((fail == 0)) || ulams_die "checks failed; roll back with: scripts/upgrade.sh $(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_VERSION_PREVIOUS)"
ulams_log "now on $new"
