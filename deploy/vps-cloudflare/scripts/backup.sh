#!/usr/bin/env bash
# Backs up an ulams install to a private R2 (S3) bucket and rotates old backups.
#
# What goes in one backup (prefix <domain>/<UTC timestamp>/ in BACKUP_BUCKET):
#   globals.sql           PostgreSQL roles (one per tenant, with password hashes)
#   <database>.dump       pg_dump -Fc of the platform database and of every tenant database (ulams_<slug>)
#   volumes.tar.gz        the api_storage volume (keys, local disks, logs) and h5p_libraries
#   secrets.tar.gz.enc    .env, the tunnel credentials and the Origin CA material, AES-256 encrypted
#                         with BACKUP_PASSPHRASE (keep that passphrase and APP_KEY in a password manager:
#                         without them a restored database cannot be read)
# Not included: the objects in the R2 buckets of the tenants (R2 keeps them durably, but a deleted or
# overwritten object is gone; copy them with rclone if you need point-in-time recovery).
#
# Run by the systemd timer ulams-backup.timer (daily); safe to run by hand. Exit status is not zero
# when anything failed, so systemd and any monitor notice.
#   scripts/backup.sh [--keep-days N] [--local-only]
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"
ulams_require_env

keep_days="$(ulams_env_get "$ULAMS_ENV_FILE" BACKUP_KEEP_DAYS)"
local_only=0
while (($#)); do
  case "$1" in
    --keep-days) keep_days="${2:?}"; shift ;;
    --local-only) local_only=1 ;;
    -h | --help) sed -n '2,19p' "${BASH_SOURCE[0]}"; exit 0 ;;
    *) ulams_die "unknown argument '$1'" ;;
  esac
  shift
done
: "${keep_days:=14}"

lock="${TMPDIR:-/tmp}/ulams-backup.lock"
if ! mkdir "$lock" 2>/dev/null; then
  # a lock older than 3 hours is a crashed run
  [[ -n "$(find "$lock" -maxdepth 0 -mmin +180 2>/dev/null)" ]] && rmdir "$lock" && mkdir "$lock" || ulams_die "another backup is running ($lock)"
fi

pg_user="$(ulams_env_get "$ULAMS_ENV_FILE" POSTGRES_USER)"
pg_db="$(ulams_env_get "$ULAMS_ENV_FILE" POSTGRES_DB)"
domain="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_DOMAIN)"
bucket="$(ulams_env_get "$ULAMS_ENV_FILE" BACKUP_BUCKET)"
passphrase="$(ulams_env_get "$ULAMS_ENV_FILE" BACKUP_PASSPHRASE)"
ts="$(date -u +%Y%m%dT%H%M%SZ)"
stage="$ULAMS_DEPLOY_DIR/backups/$ts"
umask 077
mkdir -p "$stage"
trap 'rm -rf "$stage"; rmdir "$lock" 2>/dev/null || true' EXIT

pg() { ulams_compose exec -T postgres "$@"; }

ulams_log "PostgreSQL roles and databases"
pg pg_dumpall -U "$pg_user" --globals-only >"$stage/globals.sql"
dbs=()
while IFS= read -r line; do [[ -n "$line" ]] && dbs+=("$line"); done < <(pg psql -U "$pg_user" -d postgres -Atc "select datname from pg_database where datname = '$pg_db' or datname like 'ulams\\_%' order by 1")
((${#dbs[@]})) || ulams_die "no database found"
for db in "${dbs[@]}"; do
  db="${db%$'\r'}"
  pg pg_dump -U "$pg_user" -Fc "$db" >"$stage/$db.dump"
  printf '    %-28s %s\n' "$db" "$(du -h "$stage/$db.dump" | cut -f1)"
done

ulams_log "volumes (api_storage, h5p_libraries)"
docker run --rm -v "${ULAMS_PROJECT}_api_storage:/v/api_storage:ro" -v "${ULAMS_PROJECT}_h5p_libraries:/v/h5p_libraries:ro" \
  -v "$stage:/out" alpine:3.22 tar czf /out/volumes.tar.gz -C /v api_storage h5p_libraries

ulams_log "secrets (encrypted)"
if [[ -z "$passphrase" ]]; then
  ulams_warn "BACKUP_PASSPHRASE is not set in .env: the secrets archive is skipped. Add one (openssl rand -hex 24) and store it in your password manager."
else
  files=(.env)
  for f in cloudflared/credentials.json certs/origin.pem certs/origin.key; do [[ -f "$ULAMS_DEPLOY_DIR/$f" ]] && files+=("$f"); done
  tar czf - -C "$ULAMS_DEPLOY_DIR" "${files[@]}" \
    | BACKUP_PASSPHRASE_ENV="$passphrase" openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass env:BACKUP_PASSPHRASE_ENV -out "$stage/secrets.tar.gz.enc"
fi

if ((local_only)); then
  trap 'rmdir "$lock" 2>/dev/null || true' EXIT
  ulams_log "kept locally: $stage"
  exit 0
fi

[[ -n "$bucket" ]] || ulams_die "BACKUP_BUCKET is not set"
ulams_log "upload to s3:$bucket/$domain/$ts/"
ULAMS_RCLONE_DIR="$stage" ulams_rclone copy /data "s3:$bucket/$domain/$ts" --s3-no-check-bucket --stats-one-line --stats 0
ulams_log "rotation: delete backups older than $keep_days days"
ulams_rclone delete "s3:$bucket/$domain" --min-age "${keep_days}d" --s3-no-check-bucket
ulams_rclone rmdirs "s3:$bucket/$domain" --leave-root --s3-no-check-bucket || true
ulams_log "backup $ts done: ${#dbs[@]} databases"
