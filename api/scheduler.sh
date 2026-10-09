#!/bin/bash
# Runs `schedule:run` every minute for the platform and for every tenant domain (see
# domains.sh). The domain list is read again every minute. In MULTI_DOMAINS mode the
# platform itself is skipped, as before.
DIR="$(cd "$(dirname "$0")" && pwd)"
while true; do
  if [ -z "$MULTI_DOMAINS" ]; then
    php "$DIR/artisan" schedule:run --no-interaction &
  fi
  while read -r domain; do
    php "$DIR/artisan" schedule:run --no-interaction --domain="$domain" &
  done < <("$DIR/domains.sh")
  sleep 60
done
