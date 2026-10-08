# 0007. Tenancy: database per tenant, provisioned by the tenancy package

- Status: Proposed
- Date: 2026-10-08

## Context and problem statement

The roadmap needs one site per tenant on its own subdomain (Course Builder tenant provisioning,
per-tenant themes, Sylius channels). The API already runs on `gecche/laravel-multidomain` (an env file
per host, a database per host), but nothing created tenants, and the setup leaked between tenants:
shared Redis queue and cache keys, unknown hosts silently served by the platform, workers that only
learned the tenant list at boot.

## Considered options

1. Keep gecche's database-per-tenant model and add provisioning and isolation fixes.
2. A single database with `tenant_id` scoping on every model (about 50 packages to change).
3. A separate stack per tenant (Kubernetes namespace per tenant).

## Decision

Option 1. `api/packages/tenancy` adds a platform `tenants` table (secrets encrypted) and
`ulams:tenant:create|list|delete|sync-env`: database and role, bucket, env file, migrations, Passport
keys, permissions, demo users and theme settings, each step recorded and resumable. `sync-env` rebuilds
env files and keys from the table for ephemeral containers. Redis, cache and Horizon keys are prefixed
per tenant; unknown hosts get 404; queue workers and the scheduler re-read the tenant list on every
pass. The H5P service resolves tenants from the same env files.

## Consequences

- Good: strong isolation (separate databases, keys and buckets) with little change to the packages.
- Good: tenants are created at runtime without restarting containers.
- Bad: per-tenant migrations and workers; many tenants mean many databases and connections.
- Bad: `config/domain.php` is runtime state; storage credentials are still shared (per-bucket isolation
  only); production DNS/TLS for wildcard subdomains is still to be designed.
