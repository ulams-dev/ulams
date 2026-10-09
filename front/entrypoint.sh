#!/bin/sh
# Runs from /docker-entrypoint.d/ of nginx-unprivileged, as the nginx user, before nginx starts.
# Writes /usr/share/nginx/html/runtime-config.json from the environment: every variable whose
# name starts with VITE_APP_ (an allow-list by prefix; nothing else from the environment is exposed).
# The app loads that file before it boots (ADR 0056).
set -eu

ROOT=/usr/share/nginx/html
RELEASE=""
if [ -f "$ROOT/version.html" ]; then
  RELEASE="front@$(tr -d '\n' < "$ROOT/version.html")"
fi

awk -v prefix="VITE_APP_" -v release_key="VITE_APP_SENTRY_RELEASE" -v release="$RELEASE" '
function esc(v,    out, i, c, n) {
  out = ""
  n = length(v)
  for (i = 1; i <= n; i++) {
    c = substr(v, i, 1)
    if (c == "\\") out = out "\\\\"
    else if (c == "\"") out = out "\\\""
    else if (c == "\n") out = out "\\n"
    else if (c == "\r") out = out "\\r"
    else if (c == "\t") out = out "\\t"
    else out = out c
  }
  return out
}
BEGIN {
  n = 0
  printf "{"
  for (k in ENVIRON) {
    if (index(k, prefix) == 1) {
      printf "%s\"%s\":\"%s\"", (n++ ? "," : ""), k, esc(ENVIRON[k])
    }
  }
  if (release != "" && !(release_key in ENVIRON)) {
    printf "%s\"%s\":\"%s\"", (n++ ? "," : ""), release_key, esc(release)
  }
  printf "}\n"
}' < /dev/null > "$ROOT/runtime-config.json"

echo "wrote $ROOT/runtime-config.json"
