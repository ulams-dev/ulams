# 0021. High-availability reference architecture

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

Operators need a documented way to run ulams without a single point of failure. The runtime was
built for one server: one `api` container runs php-fpm, Horizon, the tenant queue workers
(`queue.sh`) and the scheduler (`scheduler.sh`) under supervisord, and on every start `init.sh`
migrates the platform, rebuilds the tenant env files and Passport keys from the `tenants` table
(`ulams:tenant:sync-env --migrate`) and seeds permissions. Tenancy is a database, a bucket, an env
file and a Passport key pair per tenant (ADR 0007). What the code does today decides what can be
replicated:

- The tenant registry and secrets are in the platform database; env files and keys are derived
  and rebuilt per container. A tenant created on one container is unknown to the others until
  they run `ulams:tenant:sync-env` (or restart).
- The scheduler runs `schedule:run` for the platform and every tenant each minute; no task uses
  `onOneServer()`, only the demo reset uses `withoutOverlapping()` (cache store: Redis).
- Migrations run on every container start without `--isolated`.
- The platform Passport keys are files in `storage/`; if missing, `init.sh` generates new keys and
  a new `APP_KEY`, which would make the encrypted tenant secrets unreadable.
- The H5P service keeps installed libraries on a local volume (`/data/libraries`, shared by all
  tenants), content and temporary files in the tenant bucket, its tables in the tenant database
  and caches and locks in Redis. It reads the Laravel env files and public keys from a mount.
- Redis is configured as a single host (`REDIS_HOST`); there is no Sentinel or Cluster
  configuration. `SCORM_DISK` defaults to `local`.
- `api/docs/high-availability.md` is a stub.

## Considered options

1. **Single server with backups** (the documented default): one host, restore from backups.
   Simple, but every component is a single point of failure.
2. **Stateless application tier behind a load balancer, managed stateful services**: replicate
   the API (php-fpm), front, admin, PDF and H5P containers; run exactly one scheduler and one
   migration job; use managed or clustered PostgreSQL, a highly available Redis-protocol endpoint
   and S3-compatible object storage.
3. **Active-active across regions**: per-tenant databases replicated across regions, global load
   balancing. Needs write conflict handling the application does not have.

## Decision

Option 2 as the reference architecture, documented in the operators guide
(`front/docs-site/src/content/docs/operators/high-availability.mdx`), with these rules:

- **Roles from one image.** The `api` image runs as three roles selected by the existing
  `DISABLE_*` variables: *web* (php-fpm only, N replicas), *worker* (Horizon and `queue.sh`,
  M replicas) and *scheduler* (exactly one replica). Only one role instance runs migrations
  (`DISABLE_DB_MIGRATE=true` everywhere else, or a one-off job before the rollout).
- **Platform secrets are configuration.** `APP_KEY`, `JWT_PRIVATE_KEY_BASE64` and
  `JWT_PUBLIC_KEY_BASE64` are set on every replica from a secret store. Tenant keys keep coming
  from the `tenants` table.
- **Tenant changes are rolled out.** After `ulams:tenant:create` or `delete`, every API and H5P
  replica gets the new env files: run `ulams:tenant:sync-env` on each, or restart them.
- **PostgreSQL**: a managed service or Patroni with a single writer endpoint; all tenant
  databases live on that cluster (the tenancy code creates them through `pgsql_admin`).
- **Redis protocol**: a managed or Sentinel-backed service exposed as one endpoint; Redis
  Cluster is not supported by the current configuration.
- **Object storage**: S3-compatible, highly available; `SCORM_DISK` and any other local disks
  are switched to `s3` or put on shared storage.
- **H5P service**: N replicas share the library volume (ReadWriteMany) or a library set that is
  installed identically on each; everything else it stores is already shared.
- **Kubernetes**: supported by the same rules; a Helm chart is future work.

## Consequences

- Nothing in the code changes for this ADR; it records how the existing runtime is meant to be
  replicated and which limits remain (no Redis Cluster, no zero-downtime guarantee for
  migrations, manual env-file rollout after tenant changes, single scheduler).
- Follow-ups worth separate items: (scheduler replication is done, see ADR 0068: a per-minute lock in the
  cache store) `migrate --isolated`; a shared or database-backed source
  for tenant env files and keys so new tenants reach every replica without a sync; Redis
  Sentinel configuration; a Helm chart; a real `api/docs/high-availability.md`.
- The topology is a recommendation; the project does not test it.
