#!/bin/sh
# Starts a private Redis for this account when it is not running (README, "Redis"). Optional: the
# default setup uses the database for queues and cache. Redis on MyDevil is a process you run
# yourself: it is not a service, it stops when the server reboots, and every other user of the server
# can reach 127.0.0.1, so it listens on a unix socket in your home directory with a password.
# Needs `devil binexec on`, redis-server in PATH (to confirm), and REDIS_PASSWORD in api/.env.
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
cat > "$RDIR/redis.conf" <<CONF
port 0
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
