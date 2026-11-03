#!/bin/sh
# Signing, SBOM and verification steps shared by publish.yml and release.yml (and runnable by hand).
# REF is always an image reference by digest: ghcr.io/ulams-dev/api@sha256:...
#
#   supply-chain.sh scan REF         Trivy gate: fixable CRITICAL findings fail (unsigned = not released)
#   supply-chain.sh sign REF         cosign sign, keyless (GitHub OIDC); signs the index, so the BuildKit
#                                    SBOM and provenance attestation manifests it lists are covered too
#   supply-chain.sh attest REF       cosign attest the SPDX SBOM (linux/amd64) so `cosign verify-attestation
#                                    --type spdxjson` works without BuildKit tooling
#   supply-chain.sh verify REF       verify the signature (and the SBOM attestation when VERIFY_ATTESTATION=1)
#                                    against the identity of this repository's publish workflow
#   supply-chain.sh sbom REF FILE    write the SPDX JSON SBOM of one platform to FILE (PLATFORM, default linux/amd64)
#
# Environment (optional): COSIGN_SIGN_ARGS and COSIGN_VERIFY_ARGS replace the keyless defaults (the tests
# in test-supply-chain.sh use a throw-away key pair and a local registry). COSIGN_IDENTITY_REGEXP and
# COSIGN_OIDC_ISSUER override the expected workflow identity.
set -eu

repo="${GITHUB_REPOSITORY:-ulams-dev/ulams}"
identity="${COSIGN_IDENTITY_REGEXP:-^https://github.com/${repo}/.github/workflows/publish.yml@}"
issuer="${COSIGN_OIDC_ISSUER:-https://token.actions.githubusercontent.com}"
platform="${PLATFORM:-linux/amd64}"

cmd_sbom() {
  ref="${1:?image reference}"; out="${2:?output file}"
  # .SBOM is a map keyed by platform for a multi-arch index: {"linux/amd64": {"SPDX": {...}}, ...}
  docker buildx imagetools inspect "$ref" --format '{{ json .SBOM }}' > "$out.raw"
  if ! jq -e --arg p "$platform" '.[$p].SPDX // .SPDX' "$out.raw" > "$out" 2>/dev/null || [ "$(jq -r type "$out")" != object ]; then
    rm -f "$out" "$out.raw"
    echo "no SPDX SBOM for $platform on $ref" >&2
    return 1
  fi
  rm -f "$out.raw"
  echo "SBOM of $ref ($platform): $(jq '.packages | length' "$out") packages"
}

cmd_scan() {
  ref="${1:?image reference}"
  sh "$(dirname "$0")/trivy.sh" image "$ref"
}

cmd_sign() {
  ref="${1:?image reference}"
  # shellcheck disable=SC2086
  cosign sign --yes ${COSIGN_SIGN_ARGS:-} "$ref"
}

cmd_attest() {
  ref="${1:?image reference}"
  tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
  cmd_sbom "$ref" "$tmp/sbom.spdx.json"
  # shellcheck disable=SC2086
  cosign attest --yes ${COSIGN_SIGN_ARGS:-} --type spdxjson --predicate "$tmp/sbom.spdx.json" "$ref"
}

cmd_verify() {
  ref="${1:?image reference}"
  # shellcheck disable=SC2086
  verify_args="${COSIGN_VERIFY_ARGS:---certificate-identity-regexp $identity --certificate-oidc-issuer $issuer}"
  # shellcheck disable=SC2086
  cosign verify $verify_args "$ref" > /dev/null
  echo "signature verified: $ref"
  if [ "${VERIFY_ATTESTATION:-0}" = 1 ]; then
    # shellcheck disable=SC2086
    cosign verify-attestation --type spdxjson $verify_args "$ref" > /dev/null
    echo "SBOM attestation verified: $ref"
  fi
}

mode="${1:-}"; [ "$#" -gt 0 ] && shift
case "$mode" in
  scan) cmd_scan "$@" ;;
  sign) cmd_sign "$@" ;;
  attest) cmd_attest "$@" ;;
  verify) cmd_verify "$@" ;;
  sbom) cmd_sbom "$@" ;;
  *) echo "usage: supply-chain.sh scan|sign|attest|verify REF | sbom REF FILE" >&2; exit 2 ;;
esac
