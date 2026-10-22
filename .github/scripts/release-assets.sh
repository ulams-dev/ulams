#!/bin/sh
# Collects the release assets of a version: the SPDX SBOM of every published image, the SBOM of the
# source tree and a SHA256SUMS file over them. Used by .github/workflows/release.yml.
#
#   release-assets.sh VERSION OUTDIR
#
# VERSION is the image tag (v1.2.3 gives the tag 1.2.3). Each image is resolved from its tag to a
# digest, the signature of that digest is verified first (an unsigned image never gets an SBOM
# published as if it were released), then its linux/amd64 SBOM is written as <image>-<version>.spdx.json.
#
# Environment: IMAGE_PREFIX (default ghcr.io/ulams-dev), IMAGES (default: the seven published images),
# SKIP_SOURCE_SBOM=1 (tests), SKIP_VERIFY=1 (tests), plus whatever supply-chain.sh reads.
set -eu
HERE="$(cd "$(dirname "$0")" && pwd)"
version="${1:?version, for example v1.2.3}"; out="${2:?output directory}"
tag="${version#v}"
prefix="${IMAGE_PREFIX:-ghcr.io/ulams-dev}"
images="${IMAGES:-php api h5p pdf admin front mjml web}"
mkdir -p "$out"

for image in $images; do
  digest="$(docker buildx imagetools inspect "$prefix/$image:$tag" --format '{{ json .Manifest }}' | jq -r .digest)"
  ref="$prefix/$image@$digest"
  [ "${SKIP_VERIFY:-0}" = 1 ] || sh "$HERE/supply-chain.sh" verify "$ref"
  sh "$HERE/supply-chain.sh" sbom "$ref" "$out/$image-$tag.spdx.json"
  echo "$image $digest" >> "$out/digests.txt"
done

if [ "${SKIP_SOURCE_SBOM:-0}" != 1 ]; then
  # Written under the working directory (the Trivy container only sees that), then moved.
  sh "$HERE/trivy.sh" source-sbom "source-$tag.spdx.json"
  mv "source-$tag.spdx.json" "$out/"
fi

if command -v sha256sum >/dev/null 2>&1; then sum="sha256sum"; else sum="shasum -a 256"; fi
# shellcheck disable=SC2086
(cd "$out" && $sum -- *.spdx.json digests.txt > SHA256SUMS)
echo "release assets in $out:"; ls -1 "$out"
