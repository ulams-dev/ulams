#!/bin/sh
# Runs Trivy from one pinned container image, the same way in CI and on a laptop (needs Docker only).
#
#   trivy.sh fs                 repository scan: lockfiles, Dockerfiles, Compose (vulnerabilities + misconfiguration)
#   trivy.sh source-sbom FILE   SPDX SBOM of the source tree (for people who build their own images), no gate
#   trivy.sh image REF          image scan (vulnerabilities); REF is a registry reference, or a local image
#                               with IMAGE_SRC=docker
#
# Environment (all optional):
#   SEVERITY    comma list, default CRITICAL,HIGH for fs and CRITICAL for image
#   EXIT_CODE   exit status when something is found: 1 (default) fails, 0 only reports
#   FORMAT      table (default), sarif or json; with OUTPUT=<file> the report goes to that file
#   OUTPUT      file under the working directory (the container sees the directory as /src)
#   IGNOREFILE  default .trivyignore.yaml
#   IMAGE_SRC   remote (default) or docker
#   TRIVY_CACHE host directory for the vulnerability database, default ~/.cache/trivy-ulams
#   TRIVY_USERNAME, TRIVY_PASSWORD  registry credentials for private images
#
# Only vulnerabilities with a fix available count (--ignore-unfixed): the patch policy gates on what
# we can actually update (docs: Operators > Hardening, SECURITY.md).
set -eu

# Bump together with the version in the comment. Pinned by digest so a moved tag cannot change what we run.
TRIVY_IMAGE="${TRIVY_IMAGE:-ghcr.io/aquasecurity/trivy:0.76.0@sha256:2416d1f74045b57274df1a340eee6b510cacd73c66a6ccb8da092b117eb7d222}"
# Either host rate-limits shared CI runners now and then, so Trivy gets two sources, tried in this order.
export TRIVY_DB_REPOSITORY="${TRIVY_DB_REPOSITORY:-ghcr.io/aquasecurity/trivy-db:2,public.ecr.aws/aquasecurity/trivy-db:2}"

mode="${1:-}"; [ "$#" -gt 0 ] && shift
cache="${TRIVY_CACHE:-$HOME/.cache/trivy-ulams}"
mkdir -p "$cache"

args="--skip-version-check --skip-db-update --ignore-unfixed --exit-code ${EXIT_CODE:-1} --format ${FORMAT:-table}"
[ -n "${OUTPUT:-}" ] && args="$args --output /src/$OUTPUT"
[ -f "${IGNOREFILE:-.trivyignore.yaml}" ] && args="$args --ignorefile /src/${IGNOREFILE:-.trivyignore.yaml}"

# The container runs as root; hand the cache back to the caller so actions/cache can read it afterwards.
fix_cache_owner() {
  [ "$(id -u)" = 0 ] || docker run --rm -v "$cache:/c" --entrypoint chown "$TRIVY_IMAGE" -R "$(id -u):$(id -g)" /c >/dev/null 2>&1 || true
}

# The vulnerability database is downloaded without registry credentials (they are for the image under scan and
# would be refused by the second mirror), with retries, and the scan itself then skips the update.
update_db() {
  attempt=1
  while ! docker run --rm -e TRIVY_DB_REPOSITORY -v "$cache:/root/.cache" "$TRIVY_IMAGE" \
      image --skip-version-check --quiet --download-db-only; do
    [ "$attempt" -lt 3 ] || { echo "could not download the Trivy vulnerability database" >&2; fix_cache_owner; exit 1; }
    attempt=$((attempt + 1)); sleep 10
  done
}

run() {
  status=0
  # shellcheck disable=SC2086  # $args is a deliberate word list without spaces inside values
  docker run --rm \
    -e TRIVY_USERNAME -e TRIVY_PASSWORD \
    -v "$cache:/root/.cache" -v "$PWD:/src" -w /src \
    ${DOCKER_SOCKET:+-v /var/run/docker.sock:/var/run/docker.sock} \
    "$TRIVY_IMAGE" "$@" || status=$?
  fix_cache_owner
  return "$status"
}

update_db
case "$mode" in
  fs)
    # shellcheck disable=SC2086
    run fs $args --scanners vuln,misconfig --severity "${SEVERITY:-CRITICAL,HIGH}" \
      --skip-dirs node_modules --skip-dirs vendor --skip-dirs .git .
    ;;
  source-sbom)
    out="${1:?output file}"
    run fs --skip-version-check --skip-db-update --format spdx-json --output "/src/$out" --skip-dirs node_modules --skip-dirs vendor --skip-dirs .git .
    ;;
  image)
    ref="${1:?image reference}"
    src="${IMAGE_SRC:-remote}"
    [ "$src" = docker ] && DOCKER_SOCKET=1
    export DOCKER_SOCKET="${DOCKER_SOCKET:-}"
    # shellcheck disable=SC2086
    run image $args --scanners vuln --severity "${SEVERITY:-CRITICAL}" --image-src "$src" "$ref"
    ;;
  *)
    echo "usage: trivy.sh fs | source-sbom FILE | image REF" >&2
    exit 2
    ;;
esac
