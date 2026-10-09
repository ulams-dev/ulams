#!/bin/sh
# Step 2 of the install: keys, migrations, caches and the first tenant sync. Safe to run again.
# Needs the database from README.md section 3 and a filled-in api/.env.
#
#   sh setup.sh
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu
PATH="$HOME/bin:$PATH"; export PATH

[ -f "$ULAMS_API/.env" ] || die "$ULAMS_API/.env is missing: run install.sh first"
if grep -Eq '^(APP_KEY|DB_PASSWORD|DB_DATABASE)=CHANGE' "$ULAMS_API/.env"; then
  die "$ULAMS_API/.env still has CHANGE values (APP_KEY, DB_*)"
fi

cd "$ULAMS_API"
say "-- package manifest"
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
"$ULAMS_PHP" artisan package:discover --no-interaction

say "-- Passport keys (APP_KEY is only generated when empty, ADR 0066)"
sh ./init-keys.sh

say "-- migrations and one-off steps for the platform and every tenant"
"$ULAMS_PHP" artisan migrate --force --no-interaction
"$ULAMS_PHP" artisan ulams:tenant:sync-env
"$ULAMS_PHP" artisan ulams:upgrade

post_deploy
say "Done. Next: README.md sections 5 and 6 (cron, first tenant)."
