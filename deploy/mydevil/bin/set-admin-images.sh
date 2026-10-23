#!/bin/sh
# Sets how the static admin builds image URLs (ADR 0193, IMG-6b) without rebuilding it: writes REACT_APP_IMAGE_SERVICE and
# REACT_APP_IMAGE_SOURCES into <admin dir>/runtime-config.json, which the admin loads before it boots (ADR 0056). Other keys of an
# existing file are kept; so is every key that is not REACT_APP_IMAGE_*.
#
#   sh set-admin-images.sh cloudflare|noop|php [sources]    [admin dir defaults to $ULAMS_HOME/admin]
#
#   cloudflare  /cdn-cgi/image/<options>/<storage URL> on the admin's own host (the host must be proxied by the zone with
#               Image Transformations on; run `sh check-images.sh <admin host>` first)
#   noop        the original files (the kill switch)
#   php         the API resizer /api/images/img (the default when the key is absent)
# sources: comma-separated origins whose files may be transformed. Default https://staging-storage.ulams.app
# (the storage Worker); production names use the ULAMS_IMAGE_SOURCES you already set for the fronts.
# The admin reads the file on page load: a reload picks it up, no restart. Rollback: `noop`, or delete the two keys.
set -eu

ULAMS_HOME="${ULAMS_HOME:-$HOME/ulams}"
say() { printf '%s\n' "$*"; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }
command -v node >/dev/null 2>&1 || die "node not found in PATH ($PATH)"

MODE="${1:-}"
SOURCES="${2:-https://staging-storage.ulams.app}"
ADMIN="${ULAMS_ADMIN_DIR:-$ULAMS_HOME/admin}"
case "$MODE" in cloudflare|noop|php) ;; *) die "usage: sh set-admin-images.sh cloudflare|noop|php [sources]" ;; esac
[ -d "$ADMIN" ] || die "admin directory $ADMIN not found (set ULAMS_ADMIN_DIR)"

FILE="$ADMIN/runtime-config.json"
node -e '
const fs = require("fs");
const [file, mode, sources] = process.argv.slice(1);
let config = {};
if (fs.existsSync(file)) {
  try { config = JSON.parse(fs.readFileSync(file, "utf8")); } catch (e) { console.error("error: " + file + " is not valid JSON; fix or remove it"); process.exit(1); }
  if (config === null || typeof config !== "object" || Array.isArray(config)) { console.error("error: " + file + " is not a JSON object"); process.exit(1); }
}
config.REACT_APP_IMAGE_SERVICE = mode;
config.REACT_APP_IMAGE_SOURCES = sources;
fs.writeFileSync(file + ".tmp", JSON.stringify(config, null, 2) + "\n");
fs.renameSync(file + ".tmp", file);
' "$FILE" "$MODE" "$SOURCES"
say "admin images: $MODE (sources $SOURCES) in $FILE"
