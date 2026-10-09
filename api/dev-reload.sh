#!/bin/bash
# Applies code and config edits to a running container (`make dev-reload`):
#  - rebuilds the class map (composer dump-autoload -o --no-scripts) and the package manifest, so
#    packages merged since the last start are found without manual steps;
#  - rebuilds the config/route/event/view caches per domain (optimize.sh) when they are in use
#    (DEMO_PERF=1, production), and clears stale ones in development;
#  - resets opcache: php-fpm reloads gracefully (USR2), the CLI file cache is emptied;
#  - restarts the long-lived queue workers, Horizon and scheduler loops (workers.sh starts them again).
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR" || exit 1
set -e
# 1. class map and package manifest from the vendor/ this container has (after any composer change)
./php-profile.sh autoload
./php-profile.sh manifest
# 2. framework caches per domain: rebuilt when this profile uses them, otherwise any stale
#    route/config cache (left by an earlier demo run or a manual optimize) is removed
if ls bootstrap/cache/config*.php bootstrap/cache/routes*.php >/dev/null 2>&1; then
  ./optimize.sh
else
  ./optimize.sh --clear
fi
# 3. opcache: CLI file cache and php-fpm (graceful reload, no dropped requests)
rm -rf /tmp/opcache/* 2>/dev/null || true
if master=$(pgrep -f 'php-fpm: master'); then
  kill -USR2 "$master" && echo "dev-reload: php-fpm reloaded (opcache reset)"
fi
set +e
php artisan queue:restart >/dev/null 2>&1
while read -r domain; do
  php artisan queue:restart --domain="$domain" >/dev/null 2>&1
done < <(./domains.sh)
php artisan horizon:terminate >/dev/null 2>&1
pkill -TERM -f 'ulams:tenant:schedule-loop' 2>/dev/null
echo "dev-reload: queue workers, Horizon and scheduler loops restart with the new code"
