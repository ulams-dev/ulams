#!/bin/sh
# Client Side postMessage Storage round trip in a browser (nightly-conformance.yml, after the Moodle
# round trips). Run from the repository root with the ulams API serving http://ulams.test:18000
# (LTI_ALLOW_INSECURE_URLS=true) and a fake platform reachable by the API at PLATFORM_JWKS_HOST.
#
#   ULAMS_EXEC       command prefix that runs PHP in api/ (default: run on the host, cd api)
#   PLATFORM_JWKS_HOST  how the API reaches the fake platform (default 127.0.0.1)
#   NODE_PATH        must resolve `playwright`
set -eu

PLATFORM_PORT="${PLATFORM_PORT:-18090}"
PLATFORM_HOST="${PLATFORM_HOST:-platform.test}"
PLATFORM_JWKS_HOST="${PLATFORM_JWKS_HOST:-127.0.0.1}"
CLIENT_ID="${PLATFORM_CLIENT_ID:-storage-platform}"
DEPLOYMENT_ID="${PLATFORM_DEPLOYMENT_ID:-dep-storage}"

ulams() {
    if [ -n "${ULAMS_EXEC:-}" ]; then
        $ULAMS_EXEC php ../.github/conformance/lti/ulams-setup.php "$@"
    else
        (cd api && php ../.github/conformance/lti/ulams-setup.php "$@")
    fi
}
field() { node -e "const d=JSON.parse(require('fs').readFileSync(0,'utf8'));console.log(d[process.argv[1]])" "$1"; }

COURSE_ID=$(ulams seed | field course_id)
ulams platform "{\"issuer\":\"http://$PLATFORM_HOST:$PLATFORM_PORT\",\"client_id\":\"$CLIENT_ID\",\"deployment_id\":\"$DEPLOYMENT_ID\",\"auth_login_url\":\"http://$PLATFORM_HOST:$PLATFORM_PORT/authorize\",\"auth_token_url\":\"http://$PLATFORM_HOST:$PLATFORM_PORT/token\",\"jwks_url\":\"http://$PLATFORM_JWKS_HOST:$PLATFORM_PORT/jwks\"}"

ULAMS_COURSE_ID="$COURSE_ID" PLATFORM_PORT="$PLATFORM_PORT" PLATFORM_HOST="$PLATFORM_HOST" \
    PLATFORM_CLIENT_ID="$CLIENT_ID" PLATFORM_DEPLOYMENT_ID="$DEPLOYMENT_ID" \
    node .github/conformance/lti/client-side-storage.cjs
