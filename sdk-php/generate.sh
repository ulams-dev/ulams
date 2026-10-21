#!/bin/sh
# Regenerates lib/ from the committed OpenAPI document with openapi-generator (Docker, pinned version).
# CI runs this and fails when the result differs from what is committed, so the SDK always matches the API.
# Usage: sdk-php/generate.sh   (from anywhere)
set -eu
cd "$(dirname "$0")"
GENERATOR_IMAGE="${GENERATOR_IMAGE:-openapitools/openapi-generator-cli:v7.10.0}"
SPEC_DIR="$(cd ../front/cli/spec && pwd)"

rm -rf lib
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$SPEC_DIR:/spec:ro" \
  -v "$PWD:/out" \
  "$GENERATOR_IMAGE" generate \
  -i /spec/openapi.json \
  -g php \
  -o /out \
  -p invokerPackage='Ulams\Sdk' \
  -p packageName=UlamsSdk \
  -p artifactVersion="$(grep -m1 '"version"' composer.json | sed 's/.*: "\(.*\)".*/\1/')" \
  --global-property apiTests=false,modelTests=false,apiDocs=false,modelDocs=false \
  >/tmp/ulams-sdk-php-generate.log 2>&1 || { tail -30 /tmp/ulams-sdk-php-generate.log; exit 1; }
rm -f .openapi-generator/FILES
echo "sdk-php: generated $(find lib -name '*.php' | wc -l) files in lib/"
