# 0040. PostgreSQL 17 with a tested dump-and-restore upgrade

- Status: Proposed
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

Option 1, using dump and restore. Compose and every workflow use `postgres:17-alpine` on a new
volume. `make pg-upgrade` runs these steps:

1. Dump the globals and every database from a temporary 12 container (`pg_dumpall --globals-only`,
   `pg_dump -Fc`).
2. Restore them into 17.
3. Verify the row count of every table.
4. Run `ulams:upgrade`.

A nightly job tests the upgrade on seeded demo data. `pg_upgrade` is not used: it needs both binaries
in one image and gives no benefit at our data sizes.

## Consequences

- Good: three more years of support and one version across dev, CI and production docs.
- Good: dump and restore rebuilds indexes, which avoids collation surprises between images.
- Bad: downtime proportional to data size during the upgrade, documented for operators.
- Default pending #41 (16 is the alternative).
