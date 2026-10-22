#!/bin/sh
# Tests storage-migrate.sh against a real MinIO and a real SeaweedFS in throwaway containers: dry run writes
# nothing, the copy matches byte for byte and keeps metadata, a second run copies nothing, the source and
# unrelated objects at the destination are never touched, a damaged copy is found and repaired, the keys stay
# out of the output, and the policy and identity step runs in order. Needs Docker and network access for the
# images; takes a few minutes. Run by the `storage` job of .github/workflows/storage-compat.yml.
#
#   test-storage-migrate.sh
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
migrate="$here/storage-migrate.sh"
seaweed_image="${SEAWEEDFS_IMAGE:-chrislusf/seaweedfs:4.37}"
minio_image="${MINIO_IMAGE:-bitnamilegacy/minio:latest}"
rclone_image="${RCLONE_IMAGE:-rclone/rclone:1.75.2}"

id="storage-migrate-test-$$"
network="$id"
work="$(mktemp -d "${TMPDIR:-/tmp}/$id.XXXXXX")"
from_key="from$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"
from_secret="$(od -An -N20 -tx1 /dev/urandom | tr -d ' \n')"
to_key="to$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"
to_secret="$(od -An -N20 -tx1 /dev/urandom | tr -d ' \n')"
passed=0

say() { printf '[test-storage-migrate] %s\n' "$*"; }
fail() { printf '[test-storage-migrate] FAIL: %s\n' "$*" >&2; exit 1; }
ok() { passed=$((passed + 1)); say "ok: $*"; }

cleanup() {
  docker rm -f "$id-minio" "$id-seaweed" "$id-api" >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
  docker run --rm -v "$work:/work" alpine:3.22 chmod -R a+rwX /work >/dev/null 2>&1 || true
  rm -rf "$work"
}
trap cleanup EXIT INT TERM

# rclone against the two stores or the local work directory; remotes by name only, keys through the environment
rc() {
  docker run --rm --network "$network" -v "$work:/work" \
    -e RCLONE_CONFIG_SRC_TYPE=s3 -e RCLONE_CONFIG_SRC_PROVIDER=Minio -e RCLONE_CONFIG_SRC_ENV_AUTH=false \
    -e RCLONE_CONFIG_SRC_ACCESS_KEY_ID="$from_key" -e RCLONE_CONFIG_SRC_SECRET_ACCESS_KEY="$from_secret" -e RCLONE_CONFIG_SRC_ENDPOINT="http://$id-minio:9000" -e RCLONE_CONFIG_SRC_REGION=us-east-1 \
    -e RCLONE_CONFIG_DST_TYPE=s3 -e RCLONE_CONFIG_DST_PROVIDER=SeaweedFS -e RCLONE_CONFIG_DST_ENV_AUTH=false \
    -e RCLONE_CONFIG_DST_ACCESS_KEY_ID="$to_key" -e RCLONE_CONFIG_DST_SECRET_ACCESS_KEY="$to_secret" -e RCLONE_CONFIG_DST_ENDPOINT="http://$id-seaweed:8333" -e RCLONE_CONFIG_DST_REGION=us-east-1 \
    -e RCLONE_CONFIG=/dev/null "$rclone_image" "$@"
}

migrate() {
  FROM_S3_KEY="$from_key" FROM_S3_SECRET="$from_secret" TO_S3_KEY="${TO_KEY_OVERRIDE:-$to_key}" TO_S3_SECRET="$to_secret" \
    "$migrate" --network "$network" --to-endpoint "http://$id-seaweed:8333" "$@"
}

wait_for() {
  _n=0
  until rc lsd "$1:" >/dev/null 2>&1; do
    _n=$((_n + 1))
    [ "$_n" -lt 60 ] || { docker logs "$2" >&2 || true; fail "$1 did not become ready"; }
    sleep 2
  done
}

say "starting MinIO and SeaweedFS"
docker network create "$network" >/dev/null
mkdir -p "$work/minio-data"
chmod 777 "$work/minio-data"
docker run -d --name "$id-minio" --network "$network" -e MINIO_ROOT_USER="$from_key" -e MINIO_ROOT_PASSWORD="$from_secret" \
  -v "$work/minio-data:/bitnami/minio/data" "$minio_image" >/dev/null
docker run -d --name "$id-seaweed" --network "$network" -e AWS_ACCESS_KEY_ID="$to_key" -e AWS_SECRET_ACCESS_KEY="$to_secret" \
  "$seaweed_image" mini -dir=/data -s3.iam.readOnly=false >/dev/null
wait_for src "$id-minio"
wait_for dst "$id-seaweed"
sleep 3

say "seeding the old store"
mkdir -p "$work/seed/ulams/images" "$work/seed/ulams-alpha/course/1/topic/2" "$work/seed/ulams-beta" "$work/seed/other-bucket"
printf 'logo' >"$work/seed/ulams/images/logo.png"
printf '<svg xmlns="http://www.w3.org/2000/svg"/>' >"$work/seed/ulams-alpha/course/1/topic/2/diagram.svg"
printf 'zażółć' >"$work/seed/ulams-alpha/course/1/topic/2/zażółć gęślą (1).txt"
: >"$work/seed/ulams-alpha/empty.txt"
printf 'beta' >"$work/seed/ulams-beta/readme.md"
printf 'not ours' >"$work/seed/other-bucket/skip-me.txt"
# 22 MB: uploaded in parts, so its ETag is not an MD5
dd if=/dev/urandom of="$work/seed/ulams-alpha/course/1/video.bin" bs=1048576 count=22 2>/dev/null
for bucket in ulams ulams-alpha ulams-beta other-bucket; do
  rc mkdir "src:$bucket"
  rc copy "/work/seed/$bucket" "src:$bucket" --s3-upload-cutoff 5M --s3-chunk-size 5M --header-upload "Content-Disposition: inline; filename=original" >/dev/null
done
rc md5sum src:ulams-alpha | LC_ALL=C sort >"$work/source-before.md5"

say "static guard: the script never deletes, syncs or moves"
if grep -nE 'rclone_run +(sync|move|moveto|delete|deletefile|purge|rmdir|rmdirs|cleanup|dedupe|bisync)|docker run .* (sync|purge) ' "$migrate"; then
  fail "storage-migrate.sh must only copy and check"
fi
ok "only copy, mkdir, check and listing verbs"

say "dry run"
migrate --from-endpoint "http://$id-minio:9000" --dry-run >"$work/dry.log" 2>&1 || { cat "$work/dry.log" >&2; fail "dry run failed"; }
[ -z "$(rc lsd dst: | awk '{print $NF}')" ] || fail "the dry run created buckets in the new store"
grep -q "dry run finished, nothing was written" "$work/dry.log" || fail "dry run did not say it wrote nothing"
ok "a dry run writes nothing"

say "copy"
mkdir -p "$work/bin"
cat >"$work/bin/php" <<'SH'
#!/bin/sh
echo "$*" >>/calls/php.log
SH
chmod +x "$work/bin/php"
mkdir -p "$work/calls"
docker run -d --name "$id-api" --network "$network" -v "$work/bin/php:/usr/local/bin/php:ro" -v "$work/calls:/calls" alpine:3.22 sleep 600 >/dev/null
migrate --from-endpoint "http://$id-minio:9000" --api-container "$id-api" >"$work/copy.log" 2>&1 || { cat "$work/copy.log" >&2; fail "copy failed"; }
buckets="$(rc lsd dst: | awk '{print $NF}' | LC_ALL=C sort | tr '\n' ' ')"
[ "$buckets" = "ulams ulams-alpha ulams-beta " ] || fail "expected ulams, ulams-alpha, ulams-beta in the new store, got: $buckets"
ok "every ulams bucket was copied, other buckets were not"

rc copy dst:ulams-alpha /work/check-alpha >/dev/null
rc copy dst:ulams /work/check-ulams >/dev/null
rc copy dst:ulams-beta /work/check-beta >/dev/null
diff -r "$work/seed/ulams-alpha" "$work/check-alpha" || fail "ulams-alpha differs after the copy"
diff -r "$work/seed/ulams" "$work/check-ulams" || fail "ulams differs after the copy"
diff -r "$work/seed/ulams-beta" "$work/check-beta" || fail "ulams-beta differs after the copy"
ok "contents are identical, including the 22 MB multipart object, an empty file and a unicode name"

rc lsjson -M --files-only -R dst:ulams-alpha >"$work/dest-meta.json"
grep -q '"MimeType":"image/svg+xml"' "$work/dest-meta.json" || fail "the content type was not kept"
grep -q 'inline; filename=original' "$work/dest-meta.json" || fail "the Content-Disposition header was not kept"
ok "content type and disposition are kept"

grep -q "ensure-buckets" "$work/calls/php.log" || fail "the bucket policy step did not run"
grep -q "sync-env --rotate-storage-keys" "$work/calls/php.log" || fail "the identity step did not run"
[ "$(sed -n 1p "$work/calls/php.log")" = "artisan ulams:tenant:ensure-buckets" ] || fail "bucket policies must come before identities"
ok "bucket policies, then identities, were requested from the api container"

say "idempotent re-run, and nothing is removed"
rc copyto "/work/seed/other-bucket/skip-me.txt" dst:ulams-alpha/only-in-new-store.txt >/dev/null
migrate --from-endpoint "http://$id-minio:9000" >"$work/again.log" 2>&1 || { cat "$work/again.log" >&2; fail "second run failed"; }
if grep -q "Copied" "$work/again.log"; then cat "$work/again.log" >&2; fail "the second run copied objects again"; fi
rc cat dst:ulams-alpha/only-in-new-store.txt | grep -q "not ours" || fail "an object only the new store has was removed"
rc md5sum src:ulams-alpha | LC_ALL=C sort >"$work/source-after.md5"
cmp "$work/source-before.md5" "$work/source-after.md5" || fail "the source changed"
[ "$(rc lsf src:other-bucket | wc -l | tr -d ' ')" = 1 ] || fail "the source lost objects"
ok "a second run copies nothing; the source and unrelated objects are untouched"

say "a damaged copy is found and repaired"
printf 'XXXX' >"$work/damaged.png"
rc copyto /work/damaged.png dst:ulams/images/logo.png
if migrate --from-endpoint "http://$id-minio:9000" --verify-only >"$work/verify.log" 2>&1; then
  cat "$work/verify.log" >&2
  fail "--verify-only accepted a damaged copy"
fi
grep -q "logo.png" "$work/verify.log" || fail "the report does not name the damaged object"
migrate --from-endpoint "http://$id-minio:9000" >"$work/repair.log" 2>&1 || { cat "$work/repair.log" >&2; fail "the repair run failed"; }
rc cat dst:ulams/images/logo.png | grep -q '^logo$' || fail "the damaged object was not repaired"
ok "damage is detected (non-zero exit, object named) and repaired by a normal run"

say "reading the old data directory of a stopped MinIO"
docker stop "$id-minio" >/dev/null
docker rm -f "$id-minio" >/dev/null
# make the new store lose a bucket, to see it come back from the data directory alone
rc purge dst:ulams-beta >/dev/null
migrate --from-minio-dir "$work/minio-data" >"$work/dir.log" 2>&1 || { cat "$work/dir.log" >&2; fail "reading from a data directory failed"; }
rc cat dst:ulams-beta/readme.md | grep -q '^beta$' || fail "the bucket was not copied from the data directory"
ok "a MinIO data directory can be the source"

say "failures are clear"
if TO_KEY_OVERRIDE="wrong" migrate --from-minio-dir "$work/minio-data" >"$work/wrong.log" 2>&1; then fail "wrong destination keys were accepted"; fi
grep -q "cannot list the buckets of" "$work/wrong.log" || fail "a wrong destination key gives no clear error"
ok "a wrong key stops the run with a clear message"

say "secrets stay out of the output"
for secret in "$from_secret" "$to_secret" "$from_key" "$to_key"; do
  if grep -rq "$secret" "$work"/*.log; then fail "a key appeared in the output"; fi
done
ok "no key in any output"

say "all $passed checks passed"
