#!/bin/sh
# OpenID Connect sign-in against Keycloak (identity-keycloak.yml). Run from the repository root with Keycloak up
# (compose.yml, profile sso) and the ulams API serving http://ulams.test:18000 (see the workflow for its environment).
#
#   ULAMS_EXEC   command prefix that runs PHP in api/ (default: run on the host, cd api)
#   NODE_PATH    must resolve `playwright`
set -eu

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

if [ -n "${ULAMS_EXEC:-}" ]; then
    $ULAMS_EXEC php ../.github/conformance/keycloak/ulams-setup.php admin > "$WORK/admin.json"
else
    (cd api && php ../.github/conformance/keycloak/ulams-setup.php admin) > "$WORK/admin.json"
fi
ADMIN_TOKEN=$(node -e "console.log(JSON.parse(require('fs').readFileSync(process.argv[1],'utf8')).token)" "$WORK/admin.json")
export ADMIN_TOKEN

node .github/conformance/keycloak/run-oidc.cjs
