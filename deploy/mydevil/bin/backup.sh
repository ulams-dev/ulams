#!/bin/sh
# Dumps the platform database and every tenant database, and copies the env files and keys.
# Keeps ULAMS_BACKUP_DAYS (7) days under ~/ulams/backups. MyDevil also keeps its own account backups
# (to confirm: how long and how to restore: https://pomoc.mydevil.net/Backup/), but a dump you made
# yourself can be restored into any PostgreSQL, so keep this one as well. Run daily from cron.
#
# What it does NOT cover: the object storage (R2: use its own versioning or `rclone sync`) and the
# APP_KEY, which must also live in your password manager (tenant secrets cannot be read without it).
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu
PATH="$HOME/bin:$PATH"; export PATH

need pg_dump
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="$ULAMS_HOME/backups/$STAMP"
mkdir -p "$DEST"
umask 077

# env value from a file, without sourcing it (values may contain $ and spaces)
envval() { sed -n "s/^$2=//p" "$1" | tail -1 | tr -d "\"'"; }

dump_from_env() { # <env file> <label>
  host="$(envval "$1" DB_HOST)"; port="$(envval "$1" DB_PORT)"
  db="$(envval "$1" DB_DATABASE)"; user="$(envval "$1" DB_USERNAME)"; pass="$(envval "$1" DB_PASSWORD)"
  [ -n "$db" ] || { say "skip $2: no DB_DATABASE"; return 0; }
  # pg_dump reads the password from the environment, never from the command line (visible in ps)
  PGPASSWORD="$pass" pg_dump -h "$host" -p "${port:-5432}" -U "$user" --no-owner --format=custom \
    --file="$DEST/$2.dump" "$db" && say "dumped $2 ($db)" || { say "FAILED $2 ($db)"; return 1; }
}

status=0
dump_from_env "$ULAMS_API/.env" platform || status=1
for f in "$ULAMS_API"/.env.*; do
  [ -e "$f" ] || continue
  dump_from_env "$f" "tenant-${f##*/.env.}" || status=1
done

mkdir -p "$DEST/files"
cp -p "$ULAMS_API"/.env "$ULAMS_API"/.env.* "$DEST/files/" 2>/dev/null || true
cp -p "$ULAMS_API"/storage/oauth-*.key "$DEST/files/" 2>/dev/null || true
[ -f "$ULAMS_API/config/domain.php" ] && cp -p "$ULAMS_API/config/domain.php" "$DEST/files/"

# retention
find "$ULAMS_HOME/backups" -mindepth 1 -maxdepth 1 -type d -mtime +"${ULAMS_BACKUP_DAYS:-7}" -exec rm -rf {} + 2>/dev/null || true
say "backup in $DEST"
exit $status
