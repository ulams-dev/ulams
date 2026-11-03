#!/bin/sh
# Local proof of supply-chain.sh without GitHub or Sigstore: a throw-away registry, a throw-away
# buildx builder, a throw-away key pair. It builds a tiny multi-arch image with the same SBOM and
# provenance flags as publish.yml, then signs, attests and verifies it, and checks that a tampered
# reference and a wrong key fail. Needs Docker with buildx and network access to pull small images.
#
#   sh .github/scripts/test-supply-chain.sh
set -eu
HERE="$(cd "$(dirname "$0")" && pwd)"
S="$HERE/supply-chain.sh"
COSIGN_IMAGE="${COSIGN_IMAGE:-ghcr.io/sigstore/cosign/cosign:v3.1.3}"
tmp="$(mktemp -d)"; fails=0
name="sc-test-$$"
cleanup() {
  docker rm -f "$name-registry" >/dev/null 2>&1 || true
  docker buildx rm "$name-builder" >/dev/null 2>&1 || true
  rm -rf "$tmp"
}
trap cleanup EXIT

rc() { "$@" >/dev/null 2>&1 && echo 0 || echo 1; }
check() { # name expected actual
  if [ "$2" = "$3" ]; then echo "ok    $1"; else echo "FAIL  $1: wanted '$2', got '$3'"; fails=$((fails + 1)); fi
}

# cosign in a container on the host network, so localhost:<port> is the registry
mkdir -p "$tmp/bin" "$tmp/keys"
cat > "$tmp/bin/cosign" <<WRAP
#!/bin/sh
exec docker run --rm --network host -e COSIGN_PASSWORD= -v "$tmp/keys:/keys" -v "$(dirname "$tmp"):$(dirname "$tmp")" -w /keys $COSIGN_IMAGE "\$@"
WRAP
chmod +x "$tmp/bin/cosign"
PATH="$tmp/bin:$PATH"; export PATH

port="$(awk 'BEGIN{srand(); print 5100 + int(rand()*800)}')"
docker run -d --rm --name "$name-registry" -p "$port:5000" registry:2 >/dev/null
docker buildx create --name "$name-builder" --driver docker-container --driver-opt network=host >/dev/null
printf 'FROM alpine:3.22\nRUN echo ok > /ok\n' > "$tmp/Dockerfile"
docker buildx build --builder "$name-builder" --platform linux/amd64,linux/arm64 \
  --provenance mode=max --sbom true --progress quiet --push -t "localhost:$port/ulams/test:1" \
  --metadata-file "$tmp/meta.json" "$tmp" >/dev/null
digest="$(jq -r '."containerimage.digest"' "$tmp/meta.json")"
ref="localhost:$port/ulams/test@$digest"
echo "built $ref"

cosign generate-key-pair >/dev/null 2>&1
export COSIGN_SIGN_ARGS="--key /keys/cosign.key"
export COSIGN_VERIFY_ARGS="--key /keys/cosign.pub"

sh "$S" sbom "$ref" "$tmp/sbom.json" >/dev/null
check "sbom export has packages" true "$(jq '(.packages | length) > 0' "$tmp/sbom.json")"
check "sbom is SPDX" true "$(jq '.spdxVersion | startswith("SPDX-")' "$tmp/sbom.json")"
PLATFORM=linux/arm64 sh "$S" sbom "$ref" "$tmp/sbom-arm.json" >/dev/null
check "sbom export for arm64" true "$(jq '(.packages | length) > 0' "$tmp/sbom-arm.json")"
check "sbom of a missing platform fails" 1 "$(rc env PLATFORM=linux/s390x sh "$S" sbom "$ref" "$tmp/none.json")"

check "verify fails before signing" 1 "$(rc sh "$S" verify "$ref")"
sh "$S" sign "$ref" >/dev/null
check "sign then verify" 0 "$(rc sh "$S" verify "$ref")"
sh "$S" attest "$ref" >/dev/null
check "attest then verify-attestation" 0 "$(rc env VERIFY_ATTESTATION=1 sh "$S" verify "$ref")"

# release assets: verified digest, SBOM per image, checksums that match
export IMAGE_PREFIX="localhost:$port/ulams" IMAGES=test SKIP_SOURCE_SBOM=1
sh "$HERE/release-assets.sh" v1 "$tmp/assets" >/dev/null
check "release assets: SBOM file" true "$(jq '(.packages | length) > 0' "$tmp/assets/test-1.spdx.json")"
check "release assets: digest recorded" "test $digest" "$(cat "$tmp/assets/digests.txt")"
check "release assets: checksums match" 0 "$(cd "$tmp/assets" && rc shasum -a 256 -c SHA256SUMS)"

# another image under the same name is not covered by the signature
docker buildx build --builder "$name-builder" --platform linux/amd64 --progress quiet --push -t "localhost:$port/ulams/test:2" \
  --metadata-file "$tmp/meta2.json" --build-arg X=1 - >/dev/null <<DF
FROM alpine:3.22
RUN echo different > /ok
DF
other="localhost:$port/ulams/test@$(jq -r '."containerimage.digest"' "$tmp/meta2.json")"
check "an unsigned digest does not verify" 1 "$(rc sh "$S" verify "$other")"
check "release assets: an unsigned image is refused" 1 "$(rc env IMAGE_PREFIX="localhost:$port/ulams" IMAGES=test SKIP_SOURCE_SBOM=1 sh "$HERE/release-assets.sh" v2 "$tmp/assets2")"
unset IMAGE_PREFIX IMAGES SKIP_SOURCE_SBOM

# a different key must not verify
mkdir -p "$tmp/keys2"
( cd "$tmp/keys2" && docker run --rm -e COSIGN_PASSWORD= -v "$tmp/keys2:/keys" -w /keys "$COSIGN_IMAGE" generate-key-pair >/dev/null 2>&1 )
cp "$tmp/keys2/cosign.pub" "$tmp/keys/other.pub"
check "a different key does not verify" 1 "$(rc env COSIGN_VERIFY_ARGS='--key /keys/other.pub' sh "$S" verify "$ref")"

[ "$fails" -eq 0 ] || { echo "$fails failure(s)"; exit 1; }
echo "all supply-chain checks passed"
