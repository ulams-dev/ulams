#!/bin/bash
# Queue workers for every tenant domain (see domains.sh). The domain list is read again on
# every pass, so tenants provisioned at runtime are picked up without a restart. The
# platform queue itself is served by Horizon (single domain mode).
DIR="$(cd "$(dirname "$0")" && pwd)"
while true; do
  mapfile -t domains < <("$DIR/domains.sh" | shuf)
  for domain in "${domains[@]}"; do
    php "$DIR/artisan" queue:work --queue=default,broadcast,video --max-jobs=20 --stop-when-empty --domain="$domain"
  done
  sleep "${QUEUE_IDLE_SLEEP:-3}"
done
