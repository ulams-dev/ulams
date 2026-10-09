#!/usr/bin/env bash
# Restores an ulams backup made by scripts/backup.sh: roles, every database, the volumes and, on request,
# the secrets. DESTRUCTIVE: the databases in the backup replace the ones on this server.
#
#   scripts/restore.sh --remote latest            newest backup of this domain in BACKUP_BUCKET
#   scripts/restore.sh --remote 20261009T031500Z  a named backup
#   scripts/restore.sh --from /path/to/backup     a directory that holds the files of one backup
# Options:
#   --restore-secrets    also unpack secrets.tar.gz.enc over .env, the tunnel credentials and certs
#                        (needs BACKUP_PASSPHRASE in the environment or --passphrase-file; on a new
#                        server run this first: it brings back APP_KEY, which tenant secrets need)
#   --passphrase-file F  file with the backup passphrase
#   --yes                do not ask for confirmation
# Rehearse on a spare server before you need it: the guide has the drill.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$here/lib.sh"

remote="" from="" restore_secrets=0 passfile="" yes=0
while (($#)); do
  case "$1" in
    --remote) remote="${2:?}"; shift ;;
    --from) from="${2:?}"; shift ;;
    --restore-secrets) restore_secrets=1 ;;
    --passphrase-file) passfile="${2:?}"; shift ;;
    --yes) yes=1 ;;
    -h | --help) sed -n '2,19p' "${BASH_SOURCE[0]}"; exit 0 ;;
    *) ulams_die "unknown argument '$1'" ;;
  esac
  shift
done
[[ -n "$remote" || -n "$from" ]] || ulams_die "give --remote <timestamp|latest> or --from <dir>"

work="$(mktemp -d "${TMPDIR:-/tmp}/ulams-restore.XXXXXX")"
trap 'rm -rf "$work"' EXIT
umask 077

if [[ -n "$remote" ]]; then
  [[ -f "$ULAMS_ENV_FILE" ]] || ulams_die "$ULAMS_ENV_FILE not found: copy the S3_*/BACKUP_* values and ULAMS_DOMAIN into a new .env first"
  domain="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_DOMAIN)"
  bucket="$(ulams_env_get "$ULAMS_ENV_FILE" BACKUP_BUCKET)"
  if [[ "$remote" == latest ]]; then
    remote="$(ulams_rclone lsf "s3:$bucket/$domain" --dirs-only --s3-no-check-bucket | sed 's#/$##' | sort | tail -1)"
    [[ -n "$remote" ]] || ulams_die "no backup under s3:$bucket/$domain"
  fi
  ulams_log "downloading s3:$bucket/$domain/$remote"
  ULAMS_RCLONE_DIR="$work" ulams_rclone copy "s3:$bucket/$domain/$remote" /data --s3-no-check-bucket --stats-one-line --stats 0
  from="$work"
else
  [[ -d "$from" ]] || ulams_die "$from is not a directory"
fi
[[ -f "$from/globals.sql" ]] || ulams_die "$from has no globals.sql: not a backup"

if ((restore_secrets)); then
  [[ -f "$from/secrets.tar.gz.enc" ]] || ulams_die "this backup has no secrets archive"
  if [[ -n "$passfile" ]]; then BACKUP_PASSPHRASE="$(<"$passfile")"; fi
  [[ -n "${BACKUP_PASSPHRASE:-}" ]] || ulams_die "set BACKUP_PASSPHRASE or pass --passphrase-file"
  ulams_log "unpacking secrets into $ULAMS_DEPLOY_DIR"
  BACKUP_PASSPHRASE_ENV="$BACKUP_PASSPHRASE" openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:BACKUP_PASSPHRASE_ENV -in "$from/secrets.tar.gz.enc" \
    | tar xzf - -C "$ULAMS_DEPLOY_DIR"
  chmod 600 "$ULAMS_DEPLOY_DIR/.env"
fi
ulams_require_env

shopt -s nullglob
dumps=("$from"/*.dump)
((${#dumps[@]})) || ulams_die "no database dumps in $from"
if ((!yes)); then
  printf 'This replaces %d database(s) on this server with the backup %s.\nType RESTORE to continue: ' "${#dumps[@]}" "${remote:-$from}"
  read -r answer
  [[ "$answer" == RESTORE ]] || ulams_die "aborted"
fi

pg_user="$(ulams_env_get "$ULAMS_ENV_FILE" POSTGRES_USER)"
pg() { ulams_compose exec -T postgres "$@"; }

ulams_log "starting PostgreSQL only; stopping everything that uses the databases"
ulams_compose up -d postgres valkey
ulams_wait_healthy postgres 60 || ulams_die "postgres is not healthy"
ulams_compose stop api h5p web admin caddy >/dev/null 2>&1 || true

ulams_log "roles (errors for roles that already exist are expected)"
pg psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=0 -q <"$from/globals.sql" >/dev/null 2>&1 || true

for dump in "${dumps[@]}"; do
  db="$(basename "$dump" .dump)"
  ulams_log "database $db"
  pg pg_restore -U "$pg_user" -d postgres --create --clean --if-exists <"$dump" \
    || ulams_warn "pg_restore reported errors for $db (a --clean of a database that did not exist is harmless; check the output above)"
done

if [[ -f "$from/volumes.tar.gz" ]]; then
  ulams_log "volumes"
  docker run --rm -v "${ULAMS_PROJECT}_api_storage:/v/api_storage" -v "${ULAMS_PROJECT}_h5p_libraries:/v/h5p_libraries" \
    -v "$from:/in:ro" alpine:3.22 sh -c 'cd /v && tar xzf /in/volumes.tar.gz'
fi

ulams_log "starting the stack"
ulams_compose up -d
ulams_wait_healthy api 120 || ulams_die "the api did not become healthy: docker compose logs api"
ulams_artisan ulams:tenant:sync-env
ulams_artisan ulams:h5p:export-config
ulams_log "restored. Check the sign-in of the platform and of one tenant, and compare a record count with what you expect."
