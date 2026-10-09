#!/bin/bash
# Caches config, routes, events and views for the platform and every tenant domain
# (domains.sh): `php artisan optimize` per domain, each with its own .env.<host>.
#   optimize.sh          build the caches (production image, DEMO_PERF=1)
#   optimize.sh --clear  remove them (development: env and code edits apply at once)
# `optimize:clear` is not used: it also runs cache:clear, which on Redis flushes the cache
# database shared by every tenant.
DIR="$(cd "$(dirname "$0")" && pwd)"
PHP="${PHP_BINARY:-php}"
if [ "${1:-}" = "--clear" ]; then
  cmds=(config:clear route:clear event:clear view:clear)
else
  cmds=(optimize)
fi
run() {
  for cmd in "${cmds[@]}"; do
    "$PHP" "$DIR/artisan" "$cmd" --no-interaction "$@" >/dev/null || echo "optimize.sh: $cmd failed for ${1:-platform}" >&2
  done
}
if [ -z "${MULTI_DOMAINS:-}" ]; then
  run
fi
while read -r domain; do
  run --domain="$domain"
done < <("$DIR/domains.sh")
echo "optimize.sh: ${cmds[*]} done for the platform and $("$DIR/domains.sh" | wc -l | tr -d ' ') domain(s)"
