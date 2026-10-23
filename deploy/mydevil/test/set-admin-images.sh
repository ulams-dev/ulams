#!/bin/sh
# Offline test of bin/set-admin-images.sh:   sh deploy/mydevil/test/set-admin-images.sh
HERE="$(cd "$(dirname "$0")" && pwd)"
SCRIPT="$HERE/../bin/set-admin-images.sh"
fails=0
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT

check() { # <label> <condition exit code>
  if [ "$2" -eq 0 ]; then echo "ok    $1"; else echo "FAIL  $1"; fails=$((fails + 1)); fi
}
same() { # <label> <expected> <actual>
  if [ "$2" = "$3" ]; then check "$1" 0; else echo "      expected '$2', got '$3'"; check "$1" 1; fi
}
rejects() { # <label> <admin dir> <args...>
  label="$1"; dir="$2"; shift 2
  if ULAMS_ADMIN_DIR="$dir" sh "$SCRIPT" "$@" >/dev/null 2>&1; then check "$label" 1; else check "$label" 0; fi
}
json() { node -e 'const c=JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")); process.stdout.write(String(c[process.argv[2]]))' "$tmp/admin/runtime-config.json" "$1"; }

if ! command -v node >/dev/null 2>&1; then echo "skip  set-admin-images.sh (node not found)"; exit 0; fi
mkdir "$tmp/admin"

ULAMS_ADMIN_DIR="$tmp/admin" sh "$SCRIPT" cloudflare >/dev/null; check "creates the file" $?
same "sets the mode" cloudflare "$(json REACT_APP_IMAGE_SERVICE)"
same "default source is the storage Worker" https://staging-storage.ulams.app "$(json REACT_APP_IMAGE_SOURCES)"

printf '{"REACT_APP_API_URL":"https://api.example.test","OTHER":"x"}\n' > "$tmp/admin/runtime-config.json"
ULAMS_ADMIN_DIR="$tmp/admin" sh "$SCRIPT" noop "https://a.example,https://b.example" >/dev/null; check "updates an existing file" $?
same "keeps the other keys" "https://api.example.test x" "$(json REACT_APP_API_URL) $(json OTHER)"
same "noop and custom sources" "noop https://a.example,https://b.example" "$(json REACT_APP_IMAGE_SERVICE) $(json REACT_APP_IMAGE_SOURCES)"
same "leaves no temporary file" "runtime-config.json" "$(cd "$tmp/admin" && echo *)"

rejects "rejects an unknown mode" "$tmp/admin" sharp
rejects "rejects a missing admin directory" "$tmp/none" noop
printf 'not json' > "$tmp/admin/runtime-config.json"
rejects "refuses to overwrite invalid JSON" "$tmp/admin" noop
same "invalid file untouched" "not json" "$(cat "$tmp/admin/runtime-config.json")"

if [ "$fails" -eq 0 ]; then echo "all passed"; else echo "$fails failed"; exit 1; fi
