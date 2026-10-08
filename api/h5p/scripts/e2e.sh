#!/bin/sh
# End-to-end smoke test against a running service (default: published on
# localhost:18080) and the Laravel API (for a real Passport token).
#   H5P=http://localhost:18080 API=http://api.localhost sh scripts/e2e.sh [sample.h5p]
set -eu
H5P="${H5P:-http://localhost:18080}"
API="${API:-http://api.localhost}"
EMAIL="${EMAIL:-admin@escolalms.com}"
PASSWORD="${PASSWORD:-secret}"
SAMPLE="${1:-}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

fail() { echo "FAIL: $*" >&2; exit 1; }
code() { curl -s -o "$WORK/body" -w '%{http_code}' "$@"; }
json() { node -e "const b=JSON.parse(require('fs').readFileSync('$WORK/body','utf8'));console.log($1)"; }
expect() { [ "$1" = "$2" ] || { echo "--- body:"; head -c 600 "$WORK/body"; echo; fail "$3: expected $2, got $1"; }; echo "ok  $3 -> $1"; }

# a) health
expect "$(code "$H5P/h5p/health")" 200 "GET /h5p/health"
json 'JSON.stringify(b)'

# token
printf '{"email":"%s","password":"%s"}' "$EMAIL" "$PASSWORD" > "$WORK/login.json"
expect "$(code -X POST "$API/api/auth/login" -H 'Content-Type: application/json' -H 'Accept: application/json' -d @"$WORK/login.json")" 200 "POST /api/auth/login"
TOKEN="$(json 'b.data.token')"
AUTH="Authorization: Bearer $TOKEN"

# b) install a content type from the Hub (editor AJAX endpoint, token as ?_token=)
expect "$(code -X POST "$H5P/h5p/ajax?action=library-install&id=H5P.MultiChoice&_token=$TOKEN" \
  -H 'Content-Type: application/json' -d '{}')" 200 "POST /h5p/ajax?action=library-install&id=H5P.MultiChoice"
json '"    installed: "+JSON.stringify((b.data&&b.data.libraries||[]).filter(l=>l.machineName==="H5P.MultiChoice").map(l=>l.machineName+" "+l.localMajorVersion+"."+l.localMinorVersion))'

# upload a sample .h5p
if [ -z "$SAMPLE" ]; then
  SAMPLE="$WORK/multiple-choice-713.h5p"
  curl -fsSL -o "$SAMPLE" https://h5p.org/sites/default/files/h5p/exports/multiple-choice-713.h5p
fi
expect "$(code -X POST "$H5P/h5p/contents/upload" -H "$AUTH" -F "h5p_file=@$SAMPLE")" 201 "POST /h5p/contents/upload"
ID="$(json 'b.data.contentId')"
json '"    contentId="+b.data.contentId+" title="+JSON.stringify(b.data.metadata.title)+" installedLibraries="+b.data.installedLibraries.length'

# play model with script/style URLs under /h5p/
expect "$(code "$H5P/h5p/contents/$ID/play" -H "$AUTH")" 200 "GET /h5p/contents/$ID/play (admin)"
json '(()=>{const c=b.data.integration.contents["cid-"+b.data.contentId];const urls=[...b.data.scripts,...b.data.styles];const bad=urls.filter(u=>!u.startsWith("/h5p/"));if(bad.length)throw new Error("URLs outside /h5p: "+bad.slice(0,3));return "    "+urls.length+" script/style URLs, all under /h5p/ (e.g. "+b.data.scripts[0]+"); ajaxPath has _token: "+/_token=/.test(b.data.integration.ajaxPath)+"; library="+c.library})()'
USERDATA_URL="$(json 'b.data.integration.ajax.contentUserData')"
SCRIPT="$(json 'b.data.scripts.find(u=>u.includes("/libraries/"))')"
expect "$(code "$H5P$SCRIPT")" 200 "GET library file $SCRIPT"

# user state save + read back with the token from the model URL
STATE_URL="$(echo "$USERDATA_URL" | sed "s/:contentId/$ID/; s/:dataType/state/; s/:subContentId/0/")"
expect "$(code -X POST "$H5P$STATE_URL" -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'data={"answers":[0]}' --data 'preload=1&invalidate=1')" 200 "POST contentUserData (state save via ?_token=)"
expect "$(code "$H5P$STATE_URL")" 200 "GET contentUserData"
json '"    state: "+JSON.stringify(b.data)'

# list
expect "$(code "$H5P/h5p/contents?q=&perPage=5" -H "$AUTH")" 200 "GET /h5p/contents"
json '"    total="+b.meta.total+" first="+JSON.stringify(b.data[0])'
json 'b.data.some(c=>c.id==="'"$ID"'")' | grep -q true || fail "uploaded content not listed"

# download
expect "$(code "$H5P/h5p/contents/$ID/download" -H "$AUTH" -D "$WORK/headers")" 200 "GET /h5p/contents/$ID/download"
head -c 2 "$WORK/body" | grep -q PK || fail "download is not a zip"
echo "    $(grep -i content-disposition "$WORK/headers" | tr -d '\r'), $(wc -c < "$WORK/body" | tr -d ' ') bytes, zip magic PK"

# c) anonymous
expect "$(code "$H5P/h5p/contents/$ID/play")" 200 "GET /h5p/contents/$ID/play (anonymous)"
json '"    user="+JSON.stringify(b.data.integration.user)+" saveFreq="+b.data.integration.saveFreq+" postUserStatistics="+b.data.integration.postUserStatistics'
expect "$(code -X POST "$H5P/h5p/contents" -H 'Content-Type: application/json' \
  -d '{"library":"H5P.MultiChoice 1.16","params":{"params":{},"metadata":{"title":"x"}}}')" 401 "POST /h5p/contents (anonymous)"
expect "$(code "$H5P/h5p/contents")" 401 "GET /h5p/contents (anonymous)"
expect "$(code -X POST "$H5P$(echo "$STATE_URL" | sed 's/_token=[^&]*/_token=invalid/')" --data 'data=x&preload=1&invalidate=1')" 403 "POST contentUserData (anonymous)"

echo "E2E passed (contentId $ID)"
