#!/bin/bash
# Applies code and config edits to a running container (`make dev-reload`):
#  - rebuilds the config/route/event/view caches when they are in use (DEMO_PERF=1, production);
#  - resets opcache: php-fpm reloads gracefully (USR2), the CLI file cache is emptied;
#  - restarts the long-lived queue workers, Horizon and scheduler loops (workers.sh starts them again).
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR" || exit 1
set -e
# 1. package manifest from the vendor/ this container has (after any composer change)
./php-profile.sh manifest
# 2. framework caches, when this profile uses them
if ls bootstrap/cache/config*.php >/dev/null 2>&1; then
  ./optimize.sh
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
