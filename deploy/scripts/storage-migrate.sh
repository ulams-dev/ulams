#!/bin/sh
# Copies every ulams bucket from an old MinIO to the new object store (SeaweedFS, or any S3 store), checks the
# copy, and rebuilds what lives outside the buckets: bucket policies and per-tenant identities
# (docs/plans/phase-8-self-hosting.md 4.3, ADR 0041).
#
#   FROM_S3_KEY=... FROM_S3_SECRET=... TO_S3_KEY=... TO_S3_SECRET=... \
#   storage-migrate.sh (--from-endpoint URL | --from-minio-dir DIR) --to-endpoint URL [options]
#
#   --from-endpoint URL    the running MinIO (or any S3 store) to read, for example http://minio:9000
#   --from-minio-dir DIR   start a throwaway MinIO on this data directory instead (the old
#                          api/docker/minio_storage or the VPS minio volume path) and read from it
#   --to-endpoint URL      the new store, for example http://storage:8333
#   --network NAME         Docker network of both stores and of the copy (default ulams)
#   --prefix PREFIX        copy the buckets named PREFIX or PREFIX-* (default ulams)
#   --dry-run              list what would be copied; nothing is created or written anywhere
#   --verify-only          do not copy, only compare and exit non-zero on any difference
#   --verify MODE          download (default, compares the contents) or hash (ETag and size, quick)
#   --api-container NAME   the api container: afterwards runs `ulams:tenant:ensure-buckets` (bucket policies)
#                          and `ulams:tenant:sync-env --rotate-storage-keys` (per-tenant identities)
#   --transfers N          parallel transfers (default 4)
#
# Keys come from the environment, never from arguments (they would show in `ps` and the shell history).
# The source is only read: the copy uses `rclone copy` and `rclone check`, never sync, move or delete, and
# re-running it copies only what is new or changed. Objects the new store already has and the source does not
# are left alone. The old data stays where it is until you remove it yourself.
set -eu

image="${RCLONE_IMAGE:-rclone/rclone:1.75.2}"
minio_image="${MINIO_IMAGE:-bitnamilegacy/minio:latest}"
network="ulams"
prefix="ulams"
from_endpoint=""
from_dir=""
to_endpoint=""
dry_run=0
verify_only=0
verify="download"
api_container=""
transfers=4

log() { printf '[storage-migrate] %s\n' "$*" >&2; }
die() { printf '[storage-migrate] ERROR: %s\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
  case "$1" in
    --from-endpoint) from_endpoint="${2:?}"; shift ;;
    --from-minio-dir) from_dir="${2:?}"; shift ;;
    --to-endpoint) to_endpoint="${2:?}"; shift ;;
    --network) network="${2:?}"; shift ;;
    --prefix) prefix="${2:?}"; shift ;;
    --dry-run) dry_run=1 ;;
    --verify-only) verify_only=1 ;;
    --verify) verify="${2:?}"; shift ;;
    --api-container) api_container="${2:?}"; shift ;;
    --transfers) transfers="${2:?}"; shift ;;
    -h | --help) sed -n "2,28p" "$0"; exit 0 ;;
    *) die "unknown argument '$1'" ;;
  esac
  shift
done

command -v docker >/dev/null 2>&1 || die "docker is required"
[ -n "$to_endpoint" ] || die "give --to-endpoint"
[ -n "$from_endpoint" ] || [ -n "$from_dir" ] || die "give --from-endpoint or --from-minio-dir"
[ -z "$from_endpoint" ] || [ -z "$from_dir" ] || die "give --from-endpoint or --from-minio-dir, not both"
[ -n "${FROM_S3_KEY:-}" ] && [ -n "${FROM_S3_SECRET:-}" ] || die "set FROM_S3_KEY and FROM_S3_SECRET in the environment"
[ -n "${TO_S3_KEY:-}" ] && [ -n "${TO_S3_SECRET:-}" ] || die "set TO_S3_KEY and TO_S3_SECRET in the environment"
case "$verify" in download | hash) ;; *) die "--verify is download or hash" ;; esac
case "$prefix" in *[!A-Za-z0-9-]* | "") die "--prefix may only hold letters, digits and dashes" ;; esac
[ -z "$from_dir" ] || [ -d "$from_dir" ] || die "$from_dir is not a directory"

minio_container=""
cleanup() {
  [ -z "$minio_container" ] || docker rm -f "$minio_container" >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

# The remotes are configured through the environment of the rclone container, with the keys passed by
# name only: they appear in neither the command line nor the output.
export RCLONE_CONFIG_SRC_TYPE=s3 RCLONE_CONFIG_SRC_PROVIDER=Minio RCLONE_CONFIG_SRC_ENV_AUTH=false
export RCLONE_CONFIG_SRC_ACCESS_KEY_ID="$FROM_S3_KEY" RCLONE_CONFIG_SRC_SECRET_ACCESS_KEY="$FROM_S3_SECRET"
export RCLONE_CONFIG_DST_TYPE=s3 RCLONE_CONFIG_DST_PROVIDER=SeaweedFS RCLONE_CONFIG_DST_ENV_AUTH=false
export RCLONE_CONFIG_DST_ACCESS_KEY_ID="$TO_S3_KEY" RCLONE_CONFIG_DST_SECRET_ACCESS_KEY="$TO_S3_SECRET"
export RCLONE_CONFIG_DST_ENDPOINT="$to_endpoint" RCLONE_CONFIG_DST_ACL=public-read RCLONE_CONFIG_DST_REGION=us-east-1
export RCLONE_CONFIG_SRC_REGION=us-east-1 RCLONE_CONFIG_SRC_ENDPOINT="$from_endpoint"

rclone_run() {
  docker run --rm --network "$network" \
    -e RCLONE_CONFIG_SRC_TYPE -e RCLONE_CONFIG_SRC_PROVIDER -e RCLONE_CONFIG_SRC_ENV_AUTH \
    -e RCLONE_CONFIG_SRC_ACCESS_KEY_ID -e RCLONE_CONFIG_SRC_SECRET_ACCESS_KEY -e RCLONE_CONFIG_SRC_ENDPOINT -e RCLONE_CONFIG_SRC_REGION \
    -e RCLONE_CONFIG_DST_TYPE -e RCLONE_CONFIG_DST_PROVIDER -e RCLONE_CONFIG_DST_ENV_AUTH \
    -e RCLONE_CONFIG_DST_ACCESS_KEY_ID -e RCLONE_CONFIG_DST_SECRET_ACCESS_KEY -e RCLONE_CONFIG_DST_ENDPOINT \
    -e RCLONE_CONFIG_DST_ACL -e RCLONE_CONFIG_DST_REGION \
    -e RCLONE_CONFIG=/dev/null "$image" "$@"
}

if [ -n "$from_dir" ]; then
  minio_container="storage-migrate-minio-$$"
  from_endpoint="http://$minio_container:9000"
  RCLONE_CONFIG_SRC_ENDPOINT="$from_endpoint"
  export RCLONE_CONFIG_SRC_ENDPOINT
  log "starting a throwaway MinIO on $from_dir (it only serves reads)"
  docker run -d --name "$minio_container" --network "$network" \
    -e MINIO_ROOT_USER="$FROM_S3_KEY" -e MINIO_ROOT_PASSWORD="$FROM_S3_SECRET" \
    -v "$(cd "$from_dir" && pwd):/bitnami/minio/data" -v "$(cd "$from_dir" && pwd):/data" \
    "$minio_image" >/dev/null
fi

log "waiting for the old store at $from_endpoint"
n=0
until rclone_run lsd src: >/dev/null 2>&1; do
  n=$((n + 1))
  [ "$n" -lt 45 ] || die "cannot list the buckets of $from_endpoint with FROM_S3_KEY (is it running, on network $network?)"
  sleep 2
done
log "checking the new store at $to_endpoint"
rclone_run lsd dst: >/dev/null 2>&1 || die "cannot list the buckets of $to_endpoint with TO_S3_KEY (is it running, on network $network, and are the keys right?)"

buckets="$(rclone_run lsd src: | awk '{print $NF}' | grep -E "^${prefix}(-.*)?\$" | LC_ALL=C sort || true)"
[ -n "$buckets" ] || die "no bucket named $prefix or $prefix-* in $from_endpoint, nothing to copy"
log "buckets: $(printf '%s' "$buckets" | tr '\n' ' ')"

failed=0
for bucket in $buckets; do
  if [ "$verify_only" -eq 0 ]; then
    if [ "$dry_run" -eq 1 ]; then
      log "$bucket: dry run, listing what would be copied"
      rclone_run copy "src:$bucket" "dst:$bucket" --dry-run --checksum --metadata --transfers "$transfers" -v 2>&1 | grep -E "Skipped copy|NOTICE|ERROR|Transferred" || true
      continue
    fi
    log "$bucket: copying"
    rclone_run mkdir "dst:$bucket"
    rclone_run copy "src:$bucket" "dst:$bucket" --checksum --metadata --transfers "$transfers" --stats-one-line --stats 30s -v 2>&1 | grep -E "Copied|Transferred|ERROR|NOTICE" || true
  fi

  log "$bucket: verifying ($verify)"
  if [ "$verify" = download ]; then
    set -- --download
  else
    set --
  fi
  if rclone_run check "src:$bucket" "dst:$bucket" --one-way --transfers "$transfers" "$@" >/dev/null 2>&1; then
    log "$bucket: every object of the old store is in the new one and matches"
  else
    failed=1
    log "$bucket: DIFFERENCES FOUND, details:"
    rclone_run check "src:$bucket" "dst:$bucket" --one-way --transfers "$transfers" "$@" 2>&1 | grep -E "ERROR|NOTICE" | head -20 >&2 || true
  fi
done

[ "$failed" -eq 0 ] || die "the copy does not match the source; run again (it only copies what is missing) or investigate"
[ "$dry_run" -eq 0 ] || { log "dry run finished, nothing was written"; exit 0; }
[ "$verify_only" -eq 0 ] || { log "verified"; exit 0; }

if [ -n "$api_container" ]; then
  log "re-applying bucket policies"
  docker exec "$api_container" php artisan ulams:tenant:ensure-buckets
  log "issuing per-tenant storage identities"
  docker exec "$api_container" php artisan ulams:tenant:sync-env --rotate-storage-keys
  log "restart the queue workers and the H5P service so they pick up the new keys, then check the platform and a tenant"
else
  log "next, from the api container: php artisan ulams:tenant:ensure-buckets && php artisan ulams:tenant:sync-env --rotate-storage-keys"
fi
log "done; the old store was only read and still holds everything"
