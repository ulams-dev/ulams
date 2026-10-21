# Shared helpers for pg-major-upgrade.sh, pg-logical-upgrade.sh and pg-verify.sh (sourced, POSIX sh).
# Everything talks to PostgreSQL through `docker exec`, so no client tools are needed on the host.

pg_log() { printf '[pg-upgrade] %s\n' "$*" >&2; }
pg_die() { printf '[pg-upgrade] ERROR: %s\n' "$*" >&2; exit 1; }

pg_need() {
  command -v "$1" >/dev/null 2>&1 || pg_die "$1 is required"
}

# pg_exec CONTAINER CMD...: run a command in a container with the database password set.
pg_exec() {
  _c="$1"
  shift
  docker exec -e PGPASSWORD="$PG_PASSWORD" "$_c" "$@"
}

# pg_exec_i CONTAINER CMD...: same, with stdin attached.
pg_exec_i() {
  _c="$1"
  shift
  docker exec -i -e PGPASSWORD="$PG_PASSWORD" "$_c" "$@"
}

# pg_psql CONTAINER DB ARGS...: psql as the cluster superuser, tuples only, unaligned.
pg_psql() {
  _c="$1"
  _db="$2"
  shift 2
  pg_exec "$_c" psql -U "$PG_USER" -d "$_db" -X -At -v ON_ERROR_STOP=1 "$@"
}

# pg_wait CONTAINER: wait until the server accepts connections (the official image restarts once
# during first initialisation, so ask twice).
pg_wait() {
  _n=0
  _ok=0
  while [ "$_n" -lt 90 ]; do
    if pg_exec "$1" pg_isready -U "$PG_USER" -d postgres >/dev/null 2>&1; then
      _ok=$((_ok + 1))
      [ "$_ok" -ge 2 ] && return 0
    else
      _ok=0
    fi
    _n=$((_n + 1))
    sleep 1
  done
  pg_die "$1 did not become ready"
}

# pg_databases CONTAINER: every database that holds data (not templates, not the maintenance db); unsorted.
pg_databases() {
  pg_psql "$1" postgres -c "SELECT datname FROM pg_database WHERE NOT datistemplate AND datname <> 'postgres'"
}

# pg_allow_md5_roles CONTAINER: roles restored from a server older than 14 carry MD5 password hashes,
# and the official image's pg_hba.conf lets network clients in with scram-sha-256 only, which refuses
# them. The `md5` method accepts both kinds of hash, so switch the network lines to it and reload.
# Re-setting a role's password (ALTER ROLE ... PASSWORD) converts it to SCRAM.
pg_allow_md5_roles() {
  _md5="$(pg_psql "$1" postgres -c "SELECT coalesce(string_agg(rolname, ', ' ORDER BY rolname), '') FROM pg_authid WHERE rolpassword LIKE 'md5%'")"
  [ -n "$_md5" ] || return 0
  pg_log "roles with an MD5 password hash: $_md5"
  pg_log "pg_hba.conf on the new server now uses 'md5' (accepts MD5 and SCRAM) instead of 'scram-sha-256'; re-set those passwords with ALTER ROLE to move them to SCRAM"
  docker exec "$1" sh -c "sed -i '/^host/s/scram-sha-256/md5/' \"\$PGDATA/pg_hba.conf\""
  pg_psql "$1" postgres -c "SELECT pg_reload_conf()" >/dev/null
}
