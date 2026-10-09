#!/bin/bash
# Long-lived per-domain processes, one supervisor program per kind:
#
#   workers.sh queue      per tenant: default,broadcast,video queue; the builder queue (Course
#                         Builder, Living Course, Adapt build; --timeout 1800); the long-job queue
#                         (video, course clone; --timeout 18000). Each queue has its own connection
#                         whose retry_after is above that timeout (config/queue.php, ADR 0083).
#   workers.sh broadcast  broadcast queue, per tenant (MULTI_DOMAINS mode)
#   workers.sh scheduler  `ulams:tenant:schedule-loop` per tenant, plus the platform unless
#                         MULTI_DOMAINS is set
#
# Each process boots Laravel once and keeps running (queue:work polls Redis, the schedule loop
# runs the scheduler in-process every minute), instead of a cold `php artisan` boot per domain
# every few seconds. The domain list (domains.sh) is read again every WORKERS_CHECK_INTERVAL
# seconds: new tenants get their processes without a restart, removed tenants lose them, and a
# process that exits (crash, --max-time, `queue:restart`) is started again.
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
KIND="${1:-queue}"
INTERVAL="${WORKERS_CHECK_INTERVAL:-10}"
MAX_TIME="${WORKERS_MAX_TIME:-3600}"
PHP="${PHP_BINARY:-php}"
# `database` or `redis`: the driver of the default connection (the long queues come in both variants)
DRIVER="$([ "${QUEUE_CONNECTION:-redis}" = database ] && echo database || echo redis)"

declare -A pids=()

# key = "<name>|<domain>"; prints the command for one key
command_for() {
  local name="${1%%|*}" domain="${1#*|}" dom=()
  [ -n "$domain" ] && dom=(--domain="$domain")
  case "$name" in
    default) echo "$PHP" "$DIR/artisan" queue:work --queue=default,broadcast,video --sleep=3 --max-time="$MAX_TIME" --memory=256 "${dom[@]}" ;;
    builder) echo "$PHP" "$DIR/artisan" queue:work "${COURSE_BUILDER_QUEUE_CONNECTION:-$DRIVER-builder}" --queue="${COURSE_BUILDER_QUEUE:-builder}" --sleep=3 --timeout=1800 --max-time="$MAX_TIME" --memory=512 "${dom[@]}" ;;
    long) echo "$PHP" "$DIR/artisan" queue:work "${LONG_JOB_CONNECTION:-$DRIVER-long-job}" --queue="${LONG_JOB_QUEUE:-queue-long-job}" --sleep=5 --timeout=18000 --max-time="$MAX_TIME" --memory=512 "${dom[@]}" ;;
    broadcast) echo "$PHP" "$DIR/artisan" queue:work --queue=broadcast --sleep=3 --max-time="$MAX_TIME" "${dom[@]}" ;;
    schedule) echo "$PHP" "$DIR/artisan" ulams:tenant:schedule-loop --max-time="$MAX_TIME" "${dom[@]}" ;;
  esac
}

wanted() {
  local domain
  if [ "$KIND" = scheduler ] && [ -z "${MULTI_DOMAINS:-}" ]; then
    echo "schedule|"
  fi
  while read -r domain; do
    case "$KIND" in
      queue) echo "default|$domain"; echo "builder|$domain"; echo "long|$domain" ;;
      broadcast) echo "broadcast|$domain" ;;
      scheduler) echo "schedule|$domain" ;;
    esac
  done < <("$DIR/domains.sh")
}

stop_all() {
  for key in "${!pids[@]}"; do kill -TERM "${pids[$key]}" 2>/dev/null; done
  wait
  exit 0
}
trap stop_all TERM INT

while true; do
  mapfile -t want < <(wanted)
  for key in "${want[@]}"; do
    pid="${pids[$key]:-}"
    if [ -z "$pid" ] || ! kill -0 "$pid" 2>/dev/null; then
      # shellcheck disable=SC2046
      $(command_for "$key") &
      pids[$key]=$!
      echo "workers.sh $KIND: started ${key%%|*} for ${key#*|} (pid ${pids[$key]})"
    fi
  done
  for key in "${!pids[@]}"; do
    case " ${want[*]} " in
      *" $key "*) ;;
      *) kill -TERM "${pids[$key]}" 2>/dev/null; echo "workers.sh $KIND: stopped ${key%%|*} for ${key#*|}"; unset "pids[$key]" ;;
    esac
  done
  sleep "$INTERVAL" &
  wait $!
done
