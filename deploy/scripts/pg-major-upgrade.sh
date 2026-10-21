#!/bin/sh
# Upgrades a ulams PostgreSQL cluster to a new major version by dump and restore (ADR 0040).
# Works for every compose install: the developer stack, deploy/stack and the VPS example.
#
#   pg-major-upgrade.sh --old-data DIR|VOLUME --new-data DIR|VOLUME [options]
#   pg-major-upgrade.sh --old-container NAME   --new-data DIR|VOLUME [options]
#
# Input (one of):
#   --old-data X        data directory (a path such as ./docker/postgres-data) or Docker volume of the
#                       OLD server. The old server must be stopped. The script copies it to a scratch
#                       volume and starts the old version on the copy, so the original is never touched.
#   --old-container N   a running old server (any version >= 9.6) to dump from; it is only read.
# Output:
#   --new-data X        EMPTY directory or Docker volume that becomes the new cluster (use it as the
#                       data volume of the new compose service).
# Options:
#   --user U            cluster superuser (default: $POSTGRES_USER)
#   --password P        its password (default: $POSTGRES_PASSWORD)
#   --old-image I       image of the old server for --old-data (default postgres:12)
#   --new-image I       image of the new server (default postgres:17.11-alpine)
#   --dump-dir D        where the dumps are kept (default ./pg-upgrade-dumps-<timestamp>)
#
# Steps: dump the roles and every database with the new version's pg_dump, restore them into a fresh
# cluster, analyze, run pg-verify.sh (tables, row counts, sequences, indexes, schemas), stop the new
# server cleanly. Nothing is deleted. Afterwards point compose at the new data and run
# `php artisan ulams:upgrade`.
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=pg-lib.sh
. "$here/pg-lib.sh"

old_data="" old_container="" new_data=""
PG_USER="${POSTGRES_USER:-}" PG_PASSWORD="${POSTGRES_PASSWORD:-}"
old_image="postgres:12" new_image="postgres:17.11-alpine" dump_dir=""
while [ $# -gt 0 ]; do
  case "$1" in
    --old-data) old_data="${2:?}"; shift ;;
    --old-container) old_container="${2:?}"; shift ;;
    --new-data) new_data="${2:?}"; shift ;;
    --user) PG_USER="${2:?}"; shift ;;
    --password) PG_PASSWORD="${2:?}"; shift ;;
    --old-image) old_image="${2:?}"; shift ;;
    --new-image) new_image="${2:?}"; shift ;;
    --dump-dir) dump_dir="${2:?}"; shift ;;
    -h | --help) sed -n "2,26p" "$0"; exit 0 ;;
    *) pg_die "unknown argument '$1'" ;;
  esac
  shift
done
[ -n "$new_data" ] || pg_die "give --new-data"
if [ -n "$old_data" ] && [ -n "$old_container" ]; then pg_die "give --old-data or --old-container, not both"; fi
[ -n "$old_data" ] || [ -n "$old_container" ] || pg_die "give --old-data or --old-container"
[ -n "$PG_USER" ] && [ -n "$PG_PASSWORD" ] || pg_die "give --user and --password (or POSTGRES_USER and POSTGRES_PASSWORD)"
export PG_USER PG_PASSWORD
pg_need docker

# A path (absolute or starting with ./ or ../) is a bind mount, anything else a Docker volume.
mount_spec() {
  case "$1" in
    /* | ./* | ../*)
      mkdir -p "$1"
      # resolve the parent only: a PostgreSQL data directory is mode 0700 for another user
      printf '%s/%s:%s' "$(cd "$(dirname "$1")" && pwd)" "$(basename "$1")" "$2"
      ;;
    *) printf '%s:%s' "$1" "$2" ;;
  esac
}

id="$$"
net="ulams-pgup-$id" new="ulams-pgup-new-$id" old_scratch="ulams-pgup-old-$id"
old="${old_container:-ulams-pgup-old-$id}"
[ -n "$dump_dir" ] || dump_dir="./pg-upgrade-dumps-$(date +%Y%m%d%H%M%S)"
mkdir -p "$dump_dir"

started=""
cleanup() {
  code=$?
  if [ -n "$old_container" ]; then
    docker network disconnect "$net" "$old_container" >/dev/null 2>&1 || true
  else
    docker rm -f "$old" >/dev/null 2>&1 || true
  fi
  docker rm -f "$new" >/dev/null 2>&1 || true
  docker network rm "$net" >/dev/null 2>&1 || true
  docker volume rm "$old_scratch" >/dev/null 2>&1 || true
  if [ "$code" -ne 0 ] && [ -n "$started" ]; then
    pg_log "FAILED. The old data was not modified. The new data in '$new_data' is incomplete: empty it before trying again. Dumps: $dump_dir"
  fi
}
trap cleanup EXIT INT TERM

# 0. The new data location must be empty.
new_mount="$(mount_spec "$new_data" /d)"
if ! docker run --rm --entrypoint sh -v "$new_mount" "$new_image" -c 'test -z "$(ls -A /d)"'; then
  pg_die "$new_data is not empty: the new cluster needs an empty data directory"
fi

docker network create "$net" >/dev/null
started=1

# 1. The old server.
if [ -n "$old_container" ]; then
  docker inspect "$old_container" >/dev/null 2>&1 || pg_die "container $old_container not found"
  docker network connect "$net" "$old_container"
  pg_log "dumping from the running server $old_container"
else
  old_mount="$(mount_spec "$old_data" /from)"
  pg_log "copying the old data to a scratch volume (the original stays untouched)"
  docker volume create "$old_scratch" >/dev/null
  if docker run --rm --entrypoint sh -v "${old_mount}:ro" "$old_image" -c 'test -f /from/postmaster.pid'; then
    pg_die "the old server is running (postmaster.pid exists): stop it first (docker compose stop postgres), or use --old-container"
  fi
  docker run --rm --entrypoint sh -v "${old_mount}:ro" -v "$old_scratch:/to" "$old_image" -c 'cp -a /from/. /to/'
  docker run -d --name "$old" --network "$net" --network-alias "$old" -v "$old_scratch:/var/lib/postgresql/data" \
    -e POSTGRES_USER="$PG_USER" -e POSTGRES_PASSWORD="$PG_PASSWORD" "$old_image" >/dev/null
fi
pg_wait "$old"
old_version="$(pg_psql "$old" postgres -c 'SHOW server_version')"

# 2. The new server, with no database of its own: every database comes from the dumps.
docker run -d --name "$new" --network "$net" -v "$(mount_spec "$new_data" /var/lib/postgresql/data)" \
  -e POSTGRES_USER="$PG_USER" -e POSTGRES_PASSWORD="$PG_PASSWORD" -e POSTGRES_DB=postgres "$new_image" >/dev/null
pg_wait "$new"
pg_log "old server $old_version, new server $(pg_psql "$new" postgres -c 'SHOW server_version')"

# 3. Roles (the bootstrap role exists already), then every database. The new version's pg_dump reads the old server.
pg_exec "$new" pg_dumpall -h "$old" -U "$PG_USER" --globals-only \
  | grep -Ev "^CREATE ROLE \"?${PG_USER}\"?;\$" >"$dump_dir/globals.sql"
pg_exec_i "$new" psql -U "$PG_USER" -d postgres -X -q -v ON_ERROR_STOP=1 <"$dump_dir/globals.sql" >/dev/null
pg_log "roles restored"
pg_allow_md5_roles "$new"

for db in $(pg_databases "$old"); do
  pg_log "database $db"
  pg_exec "$new" pg_dump -h "$old" -U "$PG_USER" -Fc "$db" >"$dump_dir/$db.dump"
  pg_exec_i "$new" pg_restore -U "$PG_USER" -d postgres --create --exit-on-error <"$dump_dir/$db.dump"
done

# 4. Planner statistics are not part of a dump.
pg_exec "$new" vacuumdb -U "$PG_USER" --all --analyze-only --quiet

# 5. Verify while both servers are up.
sh "$here/pg-verify.sh" --old "$old" --new "$new"

# 6. Leave the new data directory cleanly shut down.
docker stop -t 120 "$new" >/dev/null
started=""
pg_log "done. New data: $new_data (dumps kept in $dump_dir)."
pg_log "Next: point the postgres service at the new data and image, start the stack, run 'php artisan ulams:upgrade'."
