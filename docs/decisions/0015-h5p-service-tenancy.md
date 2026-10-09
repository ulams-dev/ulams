# 0015. H5P service per tenant: derived internal token, platform-only libraries, least-privilege mounts

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

The H5P service (`api/h5p`, GPL, ADR 0003) serves every tenant from the Laravel env files
(`EnvFileTenantResolver`). Phase 1 (M1.8, decision 43) had to settle how the API authenticates to
the service per tenant, who may change the H5P libraries (one volume shared by all tenants), what
the service may read from the API host, how many connections it holds, and how the embedded player
keeps working when the 5-minute Passport token rotates.

## Decision

- **Internal token**: each tenant's `H5P_INTERNAL_TOKEN` is
  `hash_hmac('sha256', 'ulams-h5p-internal-token', tenant APP_KEY)`, written to the tenant env file
  by provisioning and `ulams:tenant:sync-env`. No new column or secret store; rotating the tenant
  `APP_KEY` rotates it.
- **Libraries are platform-only**: library install, update and delete through the service are
  accepted only on the platform host, because the library volume is shared. Hub installs started from
  the editor are still open to tenants (open question).
- **Least-privilege configuration in production**: with `H5P_SERVICE_CONFIG_DIR` set, the API exports
  only the env keys the service reads and the Passport public keys
  (`ulams:h5p:export-config`, kept current by tenant create, sync-env and delete), and the service
  mounts that directory read-only (`api/h5p/compose.h5p.prod.yml`) with a read-only root filesystem.
  Development keeps the read-only mount of `api/`.
- **Idle tenants are evicted**: a tenant without requests for `TENANT_IDLE_EVICT_MS` (30 min) closes
  its Postgres pool and S3 client and is rebuilt on its next request.
- **Token rotation**: the frontends send every refreshed token to the embed page
  (`ulams-h5p:token`); the player re-fetches the play model and swaps the AJAX URLs in place (no
  reload), refreshes are serialised, the editor swaps the token in its model. The old React front now
  refreshes the Passport token a minute before it expires.

## Consequences

- The exported directory holds database and bucket credentials of every tenant: it is still a
  secret, but no longer `APP_KEY`, mail/payment secrets or Passport private keys.
- Background jobs (temporary file sweep, Hub cache) only see active tenants; an idle tenant's expired
  temporary files are swept after its next request.
- The Astro front plays H5P anonymously (no token in the browser); learner state is not saved there
  (open question, `docs/plans/phase-1.md`).
