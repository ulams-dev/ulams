#!/bin/sh
# One cron pass of background work, for hosts that cannot keep a long-lived worker (ADR 0091).
#
#   cron-tick.sh default   scheduler tick + default,broadcast,video queues, platform and every tenant
#   cron-tick.sh builder   the builder queue (Course Builder, Living Course, Adapt build): jobs up to 30 min
#   cron-tick.sh long      the long-job queue (video, course clone): jobs up to 5 h
#
# Run it from cron under flock (see ../crontab), so a pass that is still busy is never doubled; for the
# `default` group that is also what stops the scheduler tick running twice in one minute.
# Environment: ULAMS_HOME, ULAMS_PHP (php84), ULAMS_QUEUE_DRIVER (database or redis),
# ULAMS_WORK_MAX_TIME (seconds per domain for `default`, 20).
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"

GROUP="${1:-default}"
DRIVER="${ULAMS_QUEUE_DRIVER:-database}"
case "$DRIVER" in database | redis) ;; *) die "ULAMS_QUEUE_DRIVER must be database or redis" ;; esac

case "$GROUP" in
  default)
    OPTIONS="--schedule --queue=default,broadcast,video --timeout=60 --max-time=${ULAMS_WORK_MAX_TIME:-20} --memory=256"
    [ "$DRIVER" = redis ] && OPTIONS="$OPTIONS --connection=redis"
    ;;
  builder)
    OPTIONS="--connection=${COURSE_BUILDER_QUEUE_CONNECTION:-$DRIVER-builder} --queue=${COURSE_BUILDER_QUEUE:-builder} --timeout=1800 --max-time=1500 --memory=512"
    ;;
  long)
    OPTIONS="--connection=${LONG_JOB_CONNECTION:-$DRIVER-long-job} --queue=${LONG_JOB_QUEUE:-queue-long-job} --timeout=18000 --max-time=3000 --memory=512"
    ;;
  *) die "unknown group '$GROUP' (default, builder or long)" ;;
esac

status=0
# the domain list is read before the loop: the artisan child must not eat the loop's stdin
DOMAINS="$(ulams_domains)"
OLD_IFS="$IFS"
IFS='
'
for domain in $DOMAINS; do
  IFS="$OLD_IFS"
  if [ "$domain" != - ]; then
    # shellcheck disable=SC2086
    artisan ulams:tenant:work-once --domain="$domain" $OPTIONS </dev/null || status=1
  else
    # the platform: it also runs tenant provisioning on the long-job queue
    # shellcheck disable=SC2086
    artisan ulams:tenant:work-once $OPTIONS </dev/null || status=1
  fi
  IFS='
'
done
IFS="$OLD_IFS"
exit $status
