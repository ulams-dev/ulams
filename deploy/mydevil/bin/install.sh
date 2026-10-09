#!/bin/sh
# Step 1 of the install on a MyDevil account (over SSH): unpack a tarball built by build-release.sh.
#
#   sh install.sh /path/to/ulams-<commit>.tar.gz
#
# Unpacks into ~/ulams (override with ULAMS_HOME), points `php` at PHP 8.4 and creates .env from the
# template. Then fill in .env and run bin/setup.sh (step 2). Run check-host.sh first. Nothing here
# creates domains, databases or certificates: those are `devil` commands in README.md, because they
# need your names.
set -eu

TARBALL="${1:-}"
[ -f "$TARBALL" ] || { echo "usage: sh install.sh <ulams-release.tar.gz>" >&2; exit 1; }
ULAMS_HOME="${ULAMS_HOME:-$HOME/ulams}"
ULAMS_PHP="${ULAMS_PHP:-php84}"

die() { printf 'error: %s\n' "$*" >&2; exit 1; }
command -v "$ULAMS_PHP" >/dev/null 2>&1 || die "$ULAMS_PHP not found; see README.md, section 1"
[ ! -d "$ULAMS_HOME/api" ] || die "$ULAMS_HOME/api exists: it is installed already, use upgrade.sh"

mkdir -p "$ULAMS_HOME/logs" "$ULAMS_HOME/run" "$ULAMS_HOME/backups" "$ULAMS_HOME/releases"
chmod 711 "$ULAMS_HOME"   # the web server must traverse it to reach api/public
tar -xzf "$TARBALL" -C "$ULAMS_HOME"
chmod +x "$ULAMS_HOME"/bin/*.sh

# `php` in cron and in the scripts must be PHP 8.4 (the account default is 8.3)
mkdir -p "$HOME/bin"
ln -sf "$(command -v "$ULAMS_PHP")" "$HOME/bin/php"
if ! grep -q 'HOME/bin' "$HOME/.bash_profile" 2>/dev/null; then
  echo 'export PATH=$HOME/bin:$PATH' >> "$HOME/.bash_profile"
fi

cd "$ULAMS_HOME/api"
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/app storage/logs bootstrap/cache
chmod -R 0775 storage bootstrap/cache
if [ ! -f .env ]; then
  cp "$ULAMS_HOME/.env.example" .env
fi
chmod 600 .env

echo "Unpacked into $ULAMS_HOME."
echo "Next: fill in $ULAMS_HOME/api/.env (every CHANGE value), then run: sh $ULAMS_HOME/bin/setup.sh"
