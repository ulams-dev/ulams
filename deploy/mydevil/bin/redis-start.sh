#!/bin/sh
# Starts a private Redis for this account when it is not running (README, "Redis"). Optional: the
# default setup uses the database for queues and cache. Redis on MyDevil is a process you run
# yourself: it is not a service, it stops when the server reboots, and every other user of the server
# can reach 127.0.0.1, so it listens on a unix socket in your home directory with a password.
# redis-server is a system binary (binexec is probably not needed, to confirm); needs REDIS_PASSWORD in api/.env.
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu

need redis-server
need screen
RDIR="$ULAMS_HOME/redis"
mkdir -p "$RDIR"
chmod 700 "$RDIR"
PASS="$(sed -n 's/^REDIS_PASSWORD=//p' "$ULAMS_API/.env" | tail -1 | tr -d "\"'")"
[ -n "$PASS" ] && [ "$PASS" != CHANGE ] || die "set REDIS_PASSWORD in $ULAMS_API/.env"

if [ -S "$RDIR/redis.sock" ] && redis-cli -s "$RDIR/redis.sock" -a "$PASS" ping 2>/dev/null | grep -q PONG; then
  exit 0
fi
rm -f "$RDIR/redis.sock"
# ULAMS_REDIS_PORT: also listen on 127.0.0.1 at a port reserved with `devil port add tcp random` (the H5P
# service reads REDIS_URL/REDIS_HOST and cannot use a unix socket); the password is still required
if [ -n "${ULAMS_REDIS_PORT:-}" ]; then LISTEN="port $ULAMS_REDIS_PORT
bind 127.0.0.1"; else LISTEN="port 0"; fi
cat > "$RDIR/redis.conf" <<CONF
$LISTEN
unixsocket $RDIR/redis.sock
unixsocketperm 700
requirepass $PASS
dir $RDIR
save 900 1
save 300 100
appendonly yes
maxmemory ${ULAMS_REDIS_MAXMEMORY:-128mb}
maxmemory-policy noeviction
CONF
chmod 600 "$RDIR/redis.conf"
screen -dmS ulams-redis redis-server "$RDIR/redis.conf"
echo "started redis on $RDIR/redis.sock"
