#!/bin/sh
# Fetches the LiaScript SCORM 1.2 player (BSD-3-Clause) at a pinned version and checksum into
# packages/liascript/resources/player/build. Run at image build time (Dockerfile, Dockerfile.develop)
# or once by hand in development: sh packages/liascript/bin/fetch-player.sh
# The build is not kept in git (about 12 MB, 450 files).
set -eu

VERSION="3.4.2--2.1.0"
SHA256="c983dc987d730c08ab44ecf025642a0126f51d8786a9b3b9c5e7f5d42da20468"
URL="https://registry.npmjs.org/@liascript/exporter/-/exporter-${VERSION}.tgz"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${ROOT}/resources/player/build"

if [ -f "${TARGET}/.version" ] && [ "$(cat "${TARGET}/.version")" = "${VERSION}" ]; then
  echo "LiaScript player ${VERSION} already present"
  exit 0
fi

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

curl -fsSL -o "${TMP}/exporter.tgz" "${URL}"
echo "${SHA256}  ${TMP}/exporter.tgz" | sha256sum -c -

tar -xzf "${TMP}/exporter.tgz" -C "${TMP}" package/LICENSE package/dist/assets/scorm1.2 package/dist/assets/common

rm -rf "${TARGET}"
mkdir -p "${TARGET}"
cp -R "${TMP}/package/dist/assets/scorm1.2/." "${TARGET}/"
cp -R "${TMP}/package/dist/assets/common/." "${TARGET}/"
cp "${TMP}/package/LICENSE" "${TARGET}/LICENSE"
echo "${VERSION}" > "${TARGET}/.version"
echo "LiaScript player ${VERSION} installed in ${TARGET}"
