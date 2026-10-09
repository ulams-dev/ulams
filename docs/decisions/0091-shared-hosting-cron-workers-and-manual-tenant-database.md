# 0091. Shared hosting: cron-driven workers and an operator-created tenant database

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

The product owner has a paid MyDevil.net shared hosting account (FreeBSD, PHP 8.4, PostgreSQL, SSH,
cron, no Docker, no supervisor, no PostgreSQL superuser). We want to know whether the API can run
there, and to ship a documented variant (`deploy/mydevil/`) if it can. Two things in the code assume
a Docker host: `workers.sh` supervises long-lived `queue:work` and `ulams:tenant:schedule-loop`
processes, and `ulams:tenant:create` creates each tenant's PostgreSQL role and database through a
connection with CREATEROLE/CREATEDB rights. A shared host offers neither a process supervisor we can
rely on nor those rights.

## Decision

1. **`ulams:tenant:work-once`** (tenancy package): one pass for one domain. It runs the scheduler tick
   (`--schedule`, reusing `ulams:tenant:schedule-loop --once`) and then `queue:work --stop-when-empty
   --max-time` on the given connection and queues, and exits. `deploy/mydevil/bin/cron-tick.sh` calls
   it per domain from cron under `flock`, one cron line per queue group (default, builder, long-job),
   with the same connections and timeouts as `workers.sh` (ADR 0083). `workers.sh` and the Docker
   stack are unchanged.
2. **`ulams:tenant:schedule-loop --once` no longer needs ext-pcntl**: signal handlers are installed only
   in loop mode and only when `SIGTERM` exists.
3. **`TENANCY_DATABASE_PROVISIONER=manual`** selects `ManualDatabaseProvisioner`: the `database` step
   only checks that the tenant can log in, and `drop` leaves the database to the operator. The operator
   creates the database first (`devil pgsql db add`) with a password they pass to
   `ulams:tenant:create --db-password=` (at least 16 characters; applied until the `database` step is
   done). The default stays `admin` (`PostgresDatabaseProvisioner`).
4. `TENANCY_S3_PUBLIC_READ_POLICY=false` skips the public-read bucket policy (Cloudflare R2 has no bucket
   policies; the operator makes buckets public with a custom domain), and the health check registers
   `RedisCheck` only when the queue or cache uses Redis.
5. The default queue and cache on this variant are the `database` driver. Redis is optional and
   self-run (unix socket, password) and is needed only by the H5P service.
6. The recommended split is: the API and PostgreSQL on MyDevil; front, admin, content-origin proxy,
   DNS, TLS and wildcards on Cloudflare; storage on R2 (documented in
   `operators/install-mydevil` and `deploy/mydevil/README.md`).

## Considered options

- Run `workers.sh` in `screen` on MyDevil. Not chosen: the host's process and memory limits are not
  published, long processes die with the server's reboots, and a cron line that exits is easier to
  reason about and to restart. `workers.sh` could still be used where `screen` is allowed.
- Make `queue:work` itself the cron command. Not chosen: it would not run the scheduler tick in the same
  boot, and each tenant needs `--domain`, so a loop is needed anyway.
- Create tenant databases with an admin connection to MyDevil's PostgreSQL. Not possible: no
  CREATEDB/CREATEROLE for the account user.
- Tenant provisioning that waits for the database (polling). Not chosen: the step is idempotent and
  resumable already; the failed step's message says what to do.

## Consequences

- Good: ulams can run on a cron-only host without a code fork; the Docker path is untouched.
- Good: `ulams:tenant:schedule-loop --once` also works on PHP builds without pcntl.
- Bad: queue latency is up to a minute on the default queue; the scheduler runs at minute granularity
  only if cron does.
- Bad: with no pcntl, `queue:work --timeout` cannot interrupt a hung job; the host's own process limits
  are the only guard (to confirm on the account).
- Bad: creating a tenant is two steps (database by hand, then the command); `deploy/mydevil/bin/add-tenant.sh`
  wraps both.
- Open: the `X-Ulams-Content-Origin` header is stripped by Caddy today. A host without Caddy needs the
  same stripping at the Cloudflare edge (a Transform Rule), or a secret value for the header (not done
  here; it changes the content-origin security design and needs its own decision).
