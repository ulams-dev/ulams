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
# ULAMS_WORKERS_MODE chooses how that is done (ADR 0083, amendment 2026-10-10):
#
#   per-tenant (default of the script, production)  the layout above: one long-lived process per
#                         tenant, queue kind and scheduler. Lowest latency, but every idle process
#                         holds a booted Laravel (~140 MB each, 29 processes with 7 domains).
#   lean (local dev and the demo profile, set in docker-compose.yml)  no long-lived PHP process:
#                         `workers.sh queue` loops over the platform and every tenant and runs
#                         `ulams:tenant:work-once` (queue:work --stop-when-empty) for the default
#                         queues, and ONE helper process does the same for the builder queue
#                         (--timeout 1800) and the long-job queue (--timeout 18000), one tenant at
#                         a time. `workers.sh scheduler` is one loop that runs the scheduler tick
#                         (`ulams:tenant:schedule-loop --once --lock`) of every domain each minute.
#                         Jobs wait for the pass over the domains (a few seconds, WORKERS_LEAN_INTERVAL) for a worker, and a long
#                         builder run delays the builder queue of the other tenants.
#
# Per-tenant mode: each process boots Laravel once and keeps running (queue:work polls Redis, the schedule loop
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

MODE="${ULAMS_WORKERS_MODE:-per-tenant}"
case "$MODE" in
  lean|per-tenant) ;;
  *) echo "workers.sh: ULAMS_WORKERS_MODE must be lean or per-tenant, got '$MODE'" >&2; exit 2 ;;
esac
# lean mode: seconds between two passes over the domains (default queues, builder and long-job queues); max seconds one pass spends on one domain
LEAN_INTERVAL="${WORKERS_LEAN_INTERVAL:-10}"
LEAN_HEAVY_INTERVAL="${WORKERS_LEAN_HEAVY_INTERVAL:-20}"
LEAN_MAX_TIME="${WORKERS_LEAN_MAX_TIME:-30}"
# the command that prints the domains; WORKERS_DRY_RUN=1 prints one pass instead of running it
DOMAINS_CMD="${WORKERS_DOMAINS_COMMAND:-$DIR/domains.sh}"
DRY="${WORKERS_DRY_RUN:-}"

declare -A pids=()

# key = "<name>|<domain>"; prints the command for one key
command_for() {
  local name="${1%%|*}" domain="${1#*|}" dom=()
  [ -n "$domain" ] && dom=(--domain="$domain")
  case "$name" in
    default) echo "$PHP" "$DIR/artisan" queue:work --queue=default,broadcast,video --sleep=3 --max-time="$MAX_TIME" --memory=256 "${dom[@]}" ;;
    builder) echo "$PHP" "$DIR/artisan" queue:work "${COURSE_BUILDER_QUEUE_CONNECTION:-$DRIVER-builder}" --queue="${COURSE_BUILDER_QUEUE:-builder}" --sleep=3 --timeout=1800 --max-time="$MAX_TIME" --memory=512 "${dom[@]}" ;;
    long) echo "$PHP" "$DIR/artisan" queue:work "${LONG_JOB_QUEUE_CONNECTION:-${LONG_JOB_CONNECTION:-$DRIVER-long-job}}" --queue="${LONG_JOB_QUEUE:-queue-long-job}" --sleep=5 --timeout=18000 --max-time="$MAX_TIME" --memory=512 "${dom[@]}" ;;
    broadcast) echo "$PHP" "$DIR/artisan" queue:work --queue=broadcast --sleep=3 --max-time="$MAX_TIME" "${dom[@]}" ;;
    schedule) echo "$PHP" "$DIR/artisan" ulams:tenant:schedule-loop --max-time="$MAX_TIME" "${dom[@]}" ;;
  esac
}

wanted() {
  local domain
  if [ "$KIND" = scheduler ] && [ -z "${MULTI_DOMAINS:-}" ]; then
    echo "schedule|"
  fi
  if [ "$KIND" = queue ] && [ -z "${MULTI_DOMAINS:-}" ]; then
    # the platform runs tenant provisioning (platform API, ADR 0078) on the long-job queue
    echo "long|"
  fi
  while read -r domain; do
    case "$KIND" in
      queue) echo "default|$domain"; echo "builder|$domain"; echo "long|$domain" ;;
      broadcast) echo "broadcast|$domain" ;;
      scheduler) echo "schedule|$domain" ;;
    esac
  done < <("$DOMAINS_CMD")
}

# ---- lean mode -------------------------------------------------------------------------------

stopping=""
child=""
helper=""

lean_stop() {
  stopping=1
  [ -n "$child" ] && kill -TERM "$child" 2>/dev/null
  [ -n "$helper" ] && kill -TERM "$helper" 2>/dev/null
}

# the platform (a sentinel, "-") unless MULTI_DOMAINS is set, then every tenant domain
lean_domains() {
  [ -z "${MULTI_DOMAINS:-}" ] && echo "-"
  "$DOMAINS_CMD"
}

# runs `artisan <args> [--domain=<domain>]` as a child and waits for it, also across a signal
lean_artisan() {
  local domain="$1" dom=()
  shift
  [ "$domain" != "-" ] && dom=(--domain="$domain")
  if [ -n "$DRY" ]; then
    echo "$PHP" "$DIR/artisan" "$@" "${dom[@]}"
    return 0
  fi
  "$PHP" "$DIR/artisan" "$@" "${dom[@]}" &
  child=$!
  wait "$child"
  while kill -0 "$child" 2>/dev/null; do wait "$child"; done
  child=""
}

# interruptible sleep
lean_sleep() {
  [ -n "$DRY" ] && return 0
  sleep "$1" &
  wait $!
}

# one pass of the default queues over every domain
lean_default_pass() {
  local domain
  while read -r domain; do
    [ -n "$stopping" ] && break
    lean_artisan "$domain" ulams:tenant:work-once --queue="$1" --timeout=60 --max-time="$LEAN_MAX_TIME" --memory=256
  done < <(lean_domains)
}

# one pass of the builder and long-job queues, one domain at a time. The timeouts are those of the
# per-tenant workers (command_for): worker --timeout >= job $timeout and < retry_after (ADR 0083).
lean_heavy_pass() {
  local domain
  while read -r domain; do
    [ -n "$stopping" ] && break
    lean_artisan "$domain" ulams:tenant:work-once --connection="${COURSE_BUILDER_QUEUE_CONNECTION:-$DRIVER-builder}" --queue="${COURSE_BUILDER_QUEUE:-builder}" --timeout=1800 --max-time="$LEAN_MAX_TIME" --memory=512
    [ -n "$stopping" ] && break
    lean_artisan "$domain" ulams:tenant:work-once --connection="${LONG_JOB_QUEUE_CONNECTION:-${LONG_JOB_CONNECTION:-$DRIVER-long-job}}" --queue="${LONG_JOB_QUEUE:-queue-long-job}" --timeout=18000 --max-time="$LEAN_MAX_TIME" --memory=512
  done < <(lean_domains)
}

# one scheduler tick per domain at the start of every minute (claimed with the minute lock, so a
# second replica does not run it twice)
lean_schedule_pass() {
  local domain
  if [ -z "$DRY" ]; then
    sleep $((60 - $(date +%s) % 60)) &
    wait $!
  fi
  while read -r domain; do
    [ -n "$stopping" ] && break
    lean_artisan "$domain" ulams:tenant:schedule-loop --once --lock
  done < <(lean_domains)
}

lean_main() {
  trap lean_stop TERM INT
  while [ -z "$stopping" ]; do
    case "$KIND" in
      queue)
        if [ -n "$DRY" ]; then
          lean_default_pass "default,broadcast,video"
          lean_heavy_pass
        elif [ -z "$helper" ] || ! kill -0 "$helper" 2>/dev/null; then
          "$DIR/workers.sh" lean-heavy &
          helper=$!
          echo "workers.sh queue (lean): started the builder and long-job process (pid $helper)"
        fi
        [ -z "$DRY" ] && lean_default_pass "default,broadcast,video" ;;
      lean-heavy) lean_heavy_pass ;;
      broadcast) lean_default_pass "broadcast" ;;
      scheduler) lean_schedule_pass ;;
    esac
    [ -n "$DRY" ] && break
    case "$KIND" in
      scheduler) ;;
      lean-heavy) lean_sleep "$LEAN_HEAVY_INTERVAL" ;;
      *) lean_sleep "$LEAN_INTERVAL" ;;
    esac
  done
  [ -n "$helper" ] && wait "$helper"
  exit 0
}

if [ "$MODE" = lean ]; then
  lean_main
fi
[ "$KIND" = lean-heavy ] && { echo "workers.sh: lean-heavy needs ULAMS_WORKERS_MODE=lean" >&2; exit 2; }

# ---- per-tenant mode -------------------------------------------------------------------------

stop_all() {
  for key in "${!pids[@]}"; do kill -TERM "${pids[$key]}" 2>/dev/null; done
  wait
  exit 0
}
trap stop_all TERM INT

while true; do
  mapfile -t want < <(wanted)
  if [ -n "$DRY" ]; then
    for key in "${want[@]}"; do echo "$(command_for "$key")"; done
    exit 0
  fi
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
