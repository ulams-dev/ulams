#!/bin/bash
# Queue workers for every tenant domain (see domains.sh). The domain list is read again on
# every pass, so tenants provisioned at runtime are picked up without a restart. The
# platform queue itself is served by Horizon (single domain mode).
DIR="$(cd "$(dirname "$0")" && pwd)"
while true; do
  mapfile -t domains < <("$DIR/domains.sh" | shuf)
  for domain in "${domains[@]}"; do
    php "$DIR/artisan" queue:work --queue=default,broadcast,video --max-jobs=20 --stop-when-empty --domain="$domain"
    # Long jobs (video processing) use their own connection and queue (packages/video config).
    php "$DIR/artisan" queue:work "${LONG_JOB_CONNECTION:-redis-long-job}" --queue="${LONG_JOB_QUEUE:-queue-long-job}" --max-jobs=5 --stop-when-empty --timeout=18000 --domain="$domain"
  done
  sleep "${QUEUE_IDLE_SLEEP:-3}"
done
