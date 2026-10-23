#!/bin/sh
# Release channels of the container images (docs/plans/phase-8-self-hosting.md, 4.6):
#   stable  the newest release (vX.Y.Z without a pre-release suffix)
#   lts     the newest patch of a declared LTS line (`X.Y` listed in .github/release-channels.json)
# The tags move to the digest of the release tag, so the cosign signature of that digest covers them.
#
#   release-channels.sh decide vX.Y.Z           prints the channels this tag gets, one per line
#   release-channels.sh apply vX.Y.Z            moves those channel tags on every image (needs a registry login)
# Inputs: TAGS (newline-separated release tags; default: git tag --list 'v*'), LTS_LINES (space-separated
# X.Y; default: the "lts" array of .github/release-channels.json), IMAGE_PREFIX, IMAGES.
set -eu

HERE="$(cd "$(dirname "$0")" && pwd)"
IMAGE_PREFIX="${IMAGE_PREFIX:-ghcr.io/ulams-dev}"
IMAGES="${IMAGES:-php api h5p pdf admin front mjml web}"

die() { echo "release-channels: $*" >&2; exit 1; }

# release tags only (vX.Y.Z), as "X Y Z" for sort
releases() {
  printf '%s\n' "$1" | sed -n 's/^v\([0-9][0-9]*\)\.\([0-9][0-9]*\)\.\([0-9][0-9]*\)$/\1 \2 \3/p' | sort -n -k1,1 -k2,2 -k3,3
}

decide() {
  tag="$1"
  version="$(printf '%s\n' "$tag" | sed -n 's/^v\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)$/\1/p')"
  # a pre-release (v1.2.0-rc.1) or another ref gets no channel
  [ -n "$version" ] || return 0
  all="$(printf '%s\n%s\n' "${TAGS-$(git tag --list 'v*')}" "$tag")"
  newest="$(releases "$all" | tail -1 | tr ' ' .)"
  [ "$version" = "$newest" ] && echo stable
  line="${version%.*}"
  lts="${LTS_LINES-$(jq -r '.lts // [] | .[]' "$HERE/../release-channels.json" 2>/dev/null || true)}"
  for l in $lts; do
    if [ "$l" = "$line" ]; then
      newest_patch="$(releases "$all" | awk -v m="${line%.*}" -v n="${line#*.}" '$1 == m && $2 == n' | tail -1 | tr ' ' .)"
      [ "$version" = "$newest_patch" ] && echo lts
    fi
  done
  return 0
}

case "${1:-}" in
  decide)
    [ $# -eq 2 ] || die "usage: decide vX.Y.Z"
    decide "$2"
    ;;
  apply)
    [ $# -eq 2 ] || die "usage: apply vX.Y.Z"
    channels="$(decide "$2")"
    if [ -z "$channels" ]; then
      echo "$2 gets no channel tag (a newer release exists, or it is not a release)"
      exit 0
    fi
    version="${2#v}"
    for image in $IMAGES; do
      for channel in $channels; do
        echo "$IMAGE_PREFIX/$image:$channel -> $IMAGE_PREFIX/$image:$version"
        docker buildx imagetools create --tag "$IMAGE_PREFIX/$image:$channel" "$IMAGE_PREFIX/$image:$version"
      done
    done
    ;;
  *) die "usage: release-channels.sh decide|apply vX.Y.Z" ;;
esac
