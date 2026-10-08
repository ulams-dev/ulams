# 0013. Multi-tenancy (SaaS) with gecche/laravel-multidomain

- Status: Accepted (retroactive)
- Date: 2024-02-07

## Context and Problem Statement

Running a separate API deployment per customer is costly. The goal ("Saas multidomain") was
to serve many LMS instances, each with its own database, bucket and settings, from one
deployment, while keeping single-domain installs working.

## Considered Options

Not recorded beyond the chosen library. Single-domain mode remains supported side by side.

## Decision Outcome

Use `gecche/laravel-multidomain` (added `71fdb93e`, 2024-02-07; `bootstrap/app.php`,
`config/domain.php`, console/HTTP kernels switched to the package's classes). The tenant is
selected by the request host (HTTP) or `--domain=` (console/queue).

- `MULTI_DOMAINS` lists the domains. `init_multidomains.sh` (`9936577c`/`bc5c354c`,
  2024-09) generates `.env.<domain>` from `<DOMAIN>_*` variables, creates each tenant's
  Postgres database and MinIO bucket, and runs migrations/seeds/Passport per domain.
- A `multidomain-tool` helper and `api/docs/multidomain.md` document the setup (`4390ef1d`).
- Tenant isolation is at database + bucket level; code is shared.
- Horizon's multi-domain provider did not work for many domains and was replaced by a
  per-domain `queue:work` loop (`1c92ea67`, ADR-0009). A default domain setup was added to
  resolve Sentry issues (`b6f90be3`, 2024-12-17).

### Consequences

- Good: one deployment hosts many tenants; adding a tenant is env vars + init script.
- Good: strong data separation (separate DBs and buckets).
- Bad: every artisan command, job and cron must be domain-aware; mistakes leak to the default
  domain.
- Bad: migrations run N times on deploy; start-up time grows with tenant count.
- Bad: dependency on a third-party package that wraps Laravel's bootstrap.

## Evidence

- `71fdb93e` 2024-02-07 "Add multi domains" — `api/bootstrap/app.php`, `api/config/domain.php`,
  `api/init.sh`, `api/composer.json` (`gecche/laravel-multidomain ^4.2`).
- `7e955c2b` 2024-02-16 "WIP"; `340a2f2b` "Merge branch 'develop' into feature/multi-domains".
- `9936577c` 2024-09-24 / `bc5c354c` 2024-09-26 "Saas multidomain v01" — `api/init_multidomains.sh`,
  `api/docker/envs/envs_multidomains.php`.
- `4390ef1d` 2024-09-30 "multidomain" — `api/docs/multidomain.md`, `api/docker/conf/Caddyfile`.
- `1c92ea67` 2024-12-04; `b6f90be3` 2024-12-17.
