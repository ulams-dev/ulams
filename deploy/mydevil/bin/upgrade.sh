#!/bin/sh
# Upgrades the installed app from a new tarball (build-release.sh): backup, swap the code, keep the
# state (.env files, tenant registry, storage/), migrate every tenant, rebuild caches.
#
#   sh upgrade.sh /path/to/ulams-<commit>.tar.gz
#
# The previous code stays in ~/ulams/api.previous; to roll back, swap the two directories back (the
# README, "Upgrade") and restore the databases from backups/ if a migration ran.
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu
PATH="$HOME/bin:$PATH"; export PATH

TARBALL="${1:-}"
[ -f "$TARBALL" ] || die "usage: sh upgrade.sh <ulams-release.tar.gz>"
[ -d "$ULAMS_API" ] || die "$ULAMS_API not found: use install.sh"

say "-- backup"
sh "$DIR/backup.sh"

say "-- unpack"
NEW="$ULAMS_HOME/.upgrade.$$"
rm -rf "$NEW"
mkdir -p "$NEW"
tar -xzf "$TARBALL" -C "$NEW"

say "-- keep the state of this installation"
# .env files and config/domain.php are written by ulams:tenant:create; storage/ holds keys and logs
for f in "$ULAMS_API"/.env "$ULAMS_API"/.env.*; do
  [ -e "$f" ] && cp -p "$f" "$NEW/api/"
done
[ -f "$ULAMS_API/config/domain.php" ] && cp -p "$ULAMS_API/config/domain.php" "$NEW/api/config/domain.php"
rm -rf "$NEW/api/storage"
cp -Rp "$ULAMS_API/storage" "$NEW/api/storage"
mkdir -p "$NEW/api/bootstrap/cache"
# demo seeding assets (H5P samples, the interactive package zips uploaded by hand): not in the tarball, needed by the hourly demo reset
DEMO_CACHE=database/seeds/Demo/assets/cache
if [ -d "$ULAMS_API/$DEMO_CACHE" ]; then
  mkdir -p "$NEW/api/$DEMO_CACHE"
  cp -Rp "$ULAMS_API/$DEMO_CACHE/." "$NEW/api/$DEMO_CACHE/"
fi

say "-- swap"
# the cron lines keep running scripts from bin/, so replace it first (a script already running is not affected)
cp -p "$NEW"/bin/*.sh "$ULAMS_HOME/bin/"
cp -p "$NEW/.env.example" "$NEW/crontab" "$ULAMS_HOME/" 2>/dev/null || true
rm -rf "$ULAMS_HOME/api.previous"
mv "$ULAMS_API" "$ULAMS_HOME/api.previous"
mv "$NEW/api" "$ULAMS_API"
rm -rf "$NEW"
chmod -R 0775 "$ULAMS_API/storage" "$ULAMS_API/bootstrap/cache"
# league/oauth2-server refuses key files that are group or world accessible: the recursive chmod above made them 775
find "$ULAMS_API/storage" -name 'oauth-*.key' -exec chmod 600 {} +

say "-- migrate and rebuild caches"
cd "$ULAMS_API"
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
"$ULAMS_PHP" artisan package:discover --no-interaction
"$ULAMS_PHP" artisan ulams:tenant:sync-env
"$ULAMS_PHP" artisan ulams:upgrade
# long-running PHP processes (none here, cron starts fresh ones) would pick up the new code on their own
post_deploy
say "Upgraded. Compare crontab in $ULAMS_HOME/crontab with 'crontab -l' if the release changed it."
