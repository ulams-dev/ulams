#!/bin/sh
# Major-version upgrade with logical replication, for installs that cannot take the dump-and-restore
# window (operators/upgrades, "Upgrade PostgreSQL with logical replication"). One publication and one
# subscription per database: the platform database and every tenant database.
#
#   pg-logical-upgrade.sh plan   --old C --new C [options]   print the steps, change nothing
#   pg-logical-upgrade.sh start  --old C --new C [options]   roles, schemas, publications, subscriptions
#   pg-logical-upgrade.sh status --old C --new C [options]   table sync state; exit 1 until all are ready
#   pg-logical-upgrade.sh finish --old C --new C [options]   AFTER you stopped all writes: copy sequences,
#                                                            drop subscriptions and publications, verify
# Options:
#   --old C / --new C   container names of the old and the new server (docker exec is used on both)
#   --old-host H        host name the NEW server uses to reach the old one (default: the --old name)
#   --user U --password P   cluster superuser, the same on both (default: $POSTGRES_USER / $POSTGRES_PASSWORD)
#
# Needs on the OLD server (restart required, do it before the window): wal_level=logical, and
# max_replication_slots and max_wal_senders of at least the number of databases plus a few. On the NEW
# server set max_logical_replication_workers and max_worker_processes to at least two per database.
# The new server must have no database except `postgres` (start it with POSTGRES_DB=postgres).
# Logical replication does not carry sequences: `finish` copies them. Tables without a primary key
# get REPLICA IDENTITY FULL on the old server (listed in the output).
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=pg-lib.sh
. "$here/pg-lib.sh"

command="${1:-}"
case "$command" in -h | --help) sed -n "2,21p" "$0"; exit 0 ;; esac
[ $# -gt 0 ] && shift
old="" new="" old_host=""
PG_USER="${POSTGRES_USER:-}" PG_PASSWORD="${POSTGRES_PASSWORD:-}"
while [ $# -gt 0 ]; do
  case "$1" in
    --old) old="${2:?}"; shift ;;
    --new) new="${2:?}"; shift ;;
    --old-host) old_host="${2:?}"; shift ;;
    --user) PG_USER="${2:?}"; shift ;;
    --password) PG_PASSWORD="${2:?}"; shift ;;
    -h | --help) sed -n "2,21p" "$0"; exit 0 ;;
    *) pg_die "unknown argument '$1'" ;;
  esac
  shift
done
case "$command" in plan | start | status | finish) ;; *) pg_die "first argument must be plan, start, status or finish" ;; esac
[ -n "$old" ] && [ -n "$new" ] || pg_die "give --old and --new (container names)"
[ -n "$PG_USER" ] && [ -n "$PG_PASSWORD" ] || pg_die "give --user and --password (or POSTGRES_USER and POSTGRES_PASSWORD)"
[ -n "$old_host" ] || old_host="$old"
export PG_USER PG_PASSWORD
pg_need docker

# the publication name is per database; subscriptions (and their replication slots, which are per cluster) are named per database
pub="ulams_upgrade"
sub_prefix="ulams_upgrade_"
databases="$(pg_databases "$old")"
[ -n "$databases" ] || pg_die "no databases found on $old"

if [ "$command" = plan ]; then
  cat <<PLAN
Databases on $old: $(printf '%s' "$databases" | tr '\n' ' ')
1. On $old: wal_level=logical (restart needed), max_replication_slots and max_wal_senders >= $(printf '%s\n' "$databases" | wc -l | tr -d ' ') + 4.
2. start: restore the roles on $new; for every database: REPLICA IDENTITY FULL on tables without a primary
   key, CREATE PUBLICATION $pub FOR ALL TABLES on $old, the schema (pg_dump --schema-only) into $new,
   CREATE SUBSCRIPTION ${sub_prefix}<db> on $new. The initial copy and the changes then flow by themselves.
3. status: repeat until every table is 'ready'.
4. Maintenance mode: stop the api, h5p and queue containers so nothing writes to $old.
5. finish: copy every sequence value (setval), DROP SUBSCRIPTION on $new, DROP PUBLICATION on $old,
   run pg-verify.sh (row counts, sequences, indexes, schemas).
6. Point DB_HOST (and the compose postgres service) at $new, start the stack, run php artisan ulams:upgrade.
PLAN
  exit 0
fi

# run SQL on a server's database, reading it from the argument
sql_on() { _c="$1"; _d="$2"; shift 2; pg_psql "$_c" "$_d" "$@"; }

case "$command" in
  start)
    wal="$(sql_on "$old" postgres -c 'SHOW wal_level')"
    [ "$wal" = logical ] || pg_die "wal_level on $old is '$wal': set wal_level=logical (postgresql.conf or 'postgres -c wal_level=logical') and restart it first"

    pg_exec "$new" pg_dumpall -h "$old_host" -U "$PG_USER" --globals-only \
      | grep -Ev "^CREATE ROLE \"?${PG_USER}\"?;\$" \
      | pg_exec_i "$new" psql -U "$PG_USER" -d postgres -X -q -v ON_ERROR_STOP=1 >/dev/null
    pg_log "roles restored on $new"
    pg_allow_md5_roles "$new"

    for db in $databases; do
      pg_log "database $db"
      nopk="$(sql_on "$old" "$db" -c "
        SELECT format('%I.%I', n.nspname, c.relname)
        FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE c.relkind = 'r' AND n.nspname NOT IN ('pg_catalog', 'information_schema') AND n.nspname NOT LIKE 'pg\_toast%'
          AND NOT EXISTS (SELECT 1 FROM pg_constraint k WHERE k.conrelid = c.oid AND k.contype = 'p')
          AND c.relreplident = 'd'")"
      for t in $nopk; do
        pg_log "  REPLICA IDENTITY FULL on $t (no primary key)"
        sql_on "$old" "$db" -c "ALTER TABLE $t REPLICA IDENTITY FULL" >/dev/null
      done
      if [ -z "$(sql_on "$old" "$db" -c "SELECT 1 FROM pg_publication WHERE pubname = '$pub'")" ]; then
        sql_on "$old" "$db" -c "CREATE PUBLICATION $pub FOR ALL TABLES" >/dev/null
      fi
      pg_exec "$new" pg_dump -h "$old_host" -U "$PG_USER" -Fc --schema-only "$db" \
        | pg_exec_i "$new" pg_restore -U "$PG_USER" -d postgres --create --exit-on-error
      sql_on "$new" "$db" -c "CREATE SUBSCRIPTION ${sub_prefix}$db CONNECTION 'host=$old_host port=5432 dbname=$db user=$PG_USER password=$PG_PASSWORD' PUBLICATION $pub" >/dev/null
    done
    pg_log "subscriptions created; run 'status' until every table is ready"
    ;;

  status)
    pending=0
    for db in $databases; do
      states="$(sql_on "$new" "$db" -F ' ' -c "SELECT srsubstate, count(*) FROM pg_subscription_rel GROUP BY 1 ORDER BY 1" | tr '\n' ',')"
      not_ready="$(sql_on "$new" "$db" -c "SELECT count(*) FROM pg_subscription_rel WHERE srsubstate <> 'r'")"
      pg_log "$db: ${states:-no tables} (not ready: $not_ready)"
      [ "$not_ready" = 0 ] || pending=$((pending + 1))
    done
    [ "$pending" -eq 0 ] || exit 1
    pg_log "every table of every database is in sync"
    ;;

  finish)
    for db in $databases; do
      not_ready="$(sql_on "$new" "$db" -c "SELECT count(*) FROM pg_subscription_rel WHERE srsubstate <> 'r'")"
      [ "$not_ready" = 0 ] || pg_die "database $db still has $not_ready table(s) that are not in sync: run 'status' until it succeeds"
    done
    # wait (up to a minute) for the slots to catch up with the old server's WAL position
    n=0
    while [ "$n" -lt 30 ]; do
      lag="$(sql_on "$old" postgres -c "SELECT coalesce(max(pg_wal_lsn_diff(pg_current_wal_lsn(), confirmed_flush_lsn)), 0)::bigint FROM pg_replication_slots WHERE slot_type = 'logical' AND slot_name LIKE 'ulams\\_upgrade\\_%'")"
      [ "$lag" -le 0 ] && break
      n=$((n + 1))
      sleep 2
    done
    for db in $databases; do
      pg_log "database $db: sequences"
      sql_on "$old" "$db" -c "
        SELECT format('SELECT setval(%L, %s, true);', quote_ident(schemaname) || '.' || quote_ident(sequencename), last_value)
        FROM pg_sequences WHERE last_value IS NOT NULL" \
        | pg_exec_i "$new" psql -U "$PG_USER" -d "$db" -X -q -At -v ON_ERROR_STOP=1 >/dev/null
      sql_on "$new" "$db" -c "DROP SUBSCRIPTION ${sub_prefix}$db" >/dev/null
      sql_on "$old" "$db" -c "DROP PUBLICATION $pub" >/dev/null
    done
    sh "$here/pg-verify.sh" --old "$old" --new "$new"
    pg_log "done. Point DB_HOST at the new server, start the stack and run 'php artisan ulams:upgrade'."
    ;;
esac
