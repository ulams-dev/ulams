#!/bin/sh
# Fetches esbuild.wasm (MIT) for the code exercises runner (ADR 0170), pinned by version and checksum, into
# resources/runtime/code-runner/<version>/. Not kept in git (14 MB). Run at image build, like the LiaScript player:
#   sh packages/code-exercises/bin/fetch-esbuild.sh
# VERSION must match esbuild-wasm in front/code-runner (yarn.lock); SHA512 is the hex of the npm tarball
# (the integrity field of the lock file is the same digest in base64).
set -eu

VERSION="0.28.2"
SHA512="19c715c21bf79a63945501c2426d8ec7fadbcbc9ff12a530bd9c44e8f8df8a4aeafe55b0f60f565ffbbd1329169e9a2ddcaa3a8f8dba0e041d79adb158a20040"
URL="https://registry.npmjs.org/esbuild-wasm/-/esbuild-wasm-${VERSION}.tgz"
RUNNER_VERSION="1"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${ROOT}/resources/runtime/code-runner/${RUNNER_VERSION}"

if [ -s "${TARGET}/esbuild.wasm" ] && [ -f "${TARGET}/.esbuild-version" ] && [ "$(cat "${TARGET}/.esbuild-version")" = "${VERSION}" ]; then
  echo "esbuild.wasm ${VERSION} already present"
  exit 0
fi

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

curl -fsSL --retry 3 -o "${TMP}/esbuild-wasm.tgz" "${URL}"
echo "${SHA512}  ${TMP}/esbuild-wasm.tgz" | sha512sum -c -

tar -xzf "${TMP}/esbuild-wasm.tgz" -C "${TMP}" package/esbuild.wasm
mkdir -p "${TARGET}"
cp "${TMP}/package/esbuild.wasm" "${TARGET}/esbuild.wasm"
echo "${VERSION}" > "${TARGET}/.esbuild-version"
echo "esbuild.wasm ${VERSION} installed in ${TARGET}"
