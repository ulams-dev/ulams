#!/bin/bash
# Broadcast queue workers for every tenant domain (see domains.sh), re-reading the domain
# list on every pass.
DIR="$(cd "$(dirname "$0")" && pwd)"
while true; do
  mapfile -t domains < <("$DIR/domains.sh" | shuf)
  for domain in "${domains[@]}"; do
    php "$DIR/artisan" queue:work --queue=broadcast --max-jobs=20 --stop-when-empty --domain="$domain"
  done
  sleep "${QUEUE_IDLE_SLEEP:-3}"
done
