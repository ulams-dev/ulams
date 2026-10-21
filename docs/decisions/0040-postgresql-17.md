# 0040. PostgreSQL 17 with a tested dump-and-restore upgrade

- Status: Accepted
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L0-02)

## Context and problem statement

The stack runs `postgres:12` in compose and CI. PostgreSQL 12 has been end of life since November
2024. The production example in the docs site already uses 17. Nothing in the code is
version-specific: the tenancy provisioner uses `CREATE ROLE`, `CREATE DATABASE`, `REVOKE` and
`pg_terminate_backend`, and there are no extensions. A major version cannot reuse the old data
directory, so existing installs need a dump and restore of the platform database and every tenant
database.

## Considered options

1. PostgreSQL 17 (supported until November 2029).
2. PostgreSQL 16 (supported until November 2028).
3. `pg_upgrade` in place instead of dump and restore.

## Decision

Option 1, using dump and restore. Compose and every workflow use `postgres:17.11-alpine`
(one tag, checked by `scripts/check-pinned-images.mjs`); the developer stack gets a new data
directory, `docker/postgres17-data`. `deploy/scripts/pg-major-upgrade.sh` (`make pg-upgrade` in `api/`)
runs these steps and never modifies the old data:

1. Start the old version on a copy of the old data (or read a running old server).
2. Dump the roles (`pg_dumpall --globals-only`) and every database (`pg_dump -Fc`, taken with the new
   version's client) and restore them into an empty 17 cluster.
3. Compare the two servers with `deploy/scripts/pg-verify.sh`: databases, schemas (so each tenant's
   `h5p` schema), every table with its exact row count, sequence values and index counts. Any difference
   exits non-zero.
4. Run `ulams:upgrade`.

Installs that cannot take the dump window use `deploy/scripts/pg-logical-upgrade.sh` (logical
replication, documented in operators/upgrades). A nightly job upgrades a seeded 12 cluster and runs the
tenant isolation suite on the result; the logical path runs in the same job once a week.
`pg_upgrade` is not used: it needs both binaries in one image and gives no benefit at our data sizes.

## Consequences

- Good: three more years of support and one version across dev, CI and production docs.
- Good: dump and restore rebuilds every index, so a change of operating-system collation between the
  Debian-based 12 image and the Alpine 17 image cannot corrupt an index. Text sorted by the database may
  come out in a different order (found by `pg-verify.sh` on the first run); release notes say so.
- Bad: downtime proportional to data size during the upgrade, documented for operators.
- Decided in #41: 17 (not 16, not 18); the plan keeps it on 17 and a later 17 to 18 move reuses the same script.
