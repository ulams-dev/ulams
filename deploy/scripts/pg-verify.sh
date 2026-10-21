#!/bin/sh
# Compares two PostgreSQL servers after a major-version upgrade and exits non-zero on any difference.
#
#   pg-verify.sh --old CONTAINER --new CONTAINER [--user U] [--password P]
#
# For every database of the old server it compares: the list of schemas (so the per-tenant `h5p`
# schema must exist), every table with its exact row count, every sequence with its value, and the
# number of indexes. The old server must not be written to while this runs.
# --user / --password default to POSTGRES_USER / POSTGRES_PASSWORD from the environment.
set -eu

here="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=pg-lib.sh
. "$here/pg-lib.sh"

old="" new=""
PG_USER="${POSTGRES_USER:-}" PG_PASSWORD="${POSTGRES_PASSWORD:-}"
while [ $# -gt 0 ]; do
  case "$1" in
    --old) old="${2:?}"; shift ;;
    --new) new="${2:?}"; shift ;;
    --user) PG_USER="${2:?}"; shift ;;
    --password) PG_PASSWORD="${2:?}"; shift ;;
    -h | --help) sed -n "2,9p" "$0"; exit 0 ;;
    *) pg_die "unknown argument '$1'" ;;
  esac
  shift
done
[ -n "$old" ] && [ -n "$new" ] || pg_die "give --old and --new (container names)"
[ -n "$PG_USER" ] && [ -n "$PG_PASSWORD" ] || pg_die "give --user and --password (or POSTGRES_USER and POSTGRES_PASSWORD)"
export PG_USER PG_PASSWORD
pg_need docker

work="$(mktemp -d "${TMPDIR:-/tmp}/pg-verify.XXXXXX")"
trap 'rm -rf "$work"' EXIT INT TERM

# Lists are sorted on the host with LC_ALL=C: the two servers may order text differently (glibc and musl).
# schema.table|rows for every ordinary and partitioned table outside the system schemas
tables_with_counts() {
  _query="$(pg_psql "$1" "$2" -c "
    SELECT coalesce(string_agg(
      format('SELECT %L AS t, count(*) AS n FROM %I.%I', n.nspname || '.' || c.relname, n.nspname, c.relname),
      ' UNION ALL ' ORDER BY n.nspname, c.relname), '')
    FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE c.relkind IN ('r', 'p') AND n.nspname NOT IN ('pg_catalog', 'information_schema')
      AND n.nspname NOT LIKE 'pg\_toast%' ESCAPE '\\'")"
  [ -n "$_query" ] || return 0
  pg_psql "$1" "$2" -F '|' -c "SELECT t, n FROM ($_query) x" | LC_ALL=C sort
}

sequences() {
  pg_psql "$1" "$2" -F '|' -c "SELECT schemaname || '.' || sequencename, coalesce(last_value::text, 'unused') FROM pg_sequences" | LC_ALL=C sort
}

schemas() {
  pg_psql "$1" "$2" -c "SELECT nspname FROM pg_namespace WHERE nspname NOT IN ('pg_catalog', 'information_schema') AND nspname NOT LIKE 'pg\_%' ESCAPE '\\'" | LC_ALL=C sort
}

indexes() {
  pg_psql "$1" "$2" -c "SELECT count(*) FROM pg_indexes WHERE schemaname NOT IN ('pg_catalog', 'information_schema')"
}

failures=0
databases="$(pg_databases "$old" | LC_ALL=C sort)"
[ -n "$databases" ] || pg_die "no databases found on $old"

new_databases="$(pg_databases "$new" | LC_ALL=C sort)"
if [ "$databases" != "$new_databases" ]; then
  pg_log "DIFFERENT databases:"
  printf '%s\n' "$databases" >"$work/old.dbs"
  printf '%s\n' "$new_databases" >"$work/new.dbs"
  diff "$work/old.dbs" "$work/new.dbs" >&2 || true
  failures=$((failures + 1))
fi

tables_total=0
for db in $databases; do
  for what in schemas tables sequences indexes; do
    case "$what" in
      schemas) schemas "$old" "$db" >"$work/old.$what" ;;
      tables) tables_with_counts "$old" "$db" >"$work/old.$what" ;;
      sequences) sequences "$old" "$db" >"$work/old.$what" ;;
      indexes) indexes "$old" "$db" >"$work/old.$what" ;;
    esac
    case "$what" in
      schemas) schemas "$new" "$db" >"$work/new.$what" ;;
      tables) tables_with_counts "$new" "$db" >"$work/new.$what" ;;
      sequences) sequences "$new" "$db" >"$work/new.$what" ;;
      indexes) indexes "$new" "$db" >"$work/new.$what" ;;
    esac
    if ! diff "$work/old.$what" "$work/new.$what" >"$work/diff" 2>&1; then
      pg_log "DIFFERENT $what in database $db (< old, > new):"
      cat "$work/diff" >&2
      failures=$((failures + 1))
    fi
  done
  n="$(wc -l <"$work/old.tables" | tr -d ' ')"
  tables_total=$((tables_total + n))
  pg_log "$db: $n tables, $(wc -l <"$work/old.sequences" | tr -d ' ') sequences, $(cat "$work/old.indexes") indexes compared"
done

if [ "$failures" -gt 0 ]; then
  pg_die "$failures difference(s) between $old and $new"
fi
pg_log "verified: $(printf '%s\n' "$databases" | wc -l | tr -d ' ') databases, $tables_total tables, row counts, sequences, indexes and schemas are identical"
