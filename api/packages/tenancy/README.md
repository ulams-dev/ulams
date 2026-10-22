# Tenancy

## What does it do

Provisions and removes tenants of a multi-domain ulams API. One API deployment serves the
platform (`api.localhost`) and any number of tenants, each on its own host
(`<slug>.localhost`) with its own:

- PostgreSQL role and database (`ulams_<slug>`),
- S3 bucket with public read (`ulams-<slug>`) and, on the bundled SeaweedFS, its own S3 key limited to that
  bucket (`TENANCY_S3_IDENTITY_DRIVER=seaweedfs`, ADR 0041),
- `.env.<host>` file and storage directory (gecche/laravel-multidomain),
- `APP_KEY` and Passport key pair (a token from one tenant is rejected by every other),
- Redis key prefix for queues, cache and Horizon (`ulams_<slug>_`),
- demo users and the public settings the front reads (`global.companyName`,
  `global.frontURL`, `theme.theme`, `theme.accent`),
- optionally demo mode (`tenants.demo` → `DEMO_MODE` in the env file, see
  [packages/demo](../demo/README.md)).

The registry of tenants is the `tenants` table in the platform database. Secrets in it
(`db_password`, `app_key`, Passport keys) are encrypted with the platform `APP_KEY`.

It also stops laravel-multidomain from serving unknown hosts with the platform `.env`: a
global middleware answers 404 to any host that is neither a platform host
(`TENANCY_PLATFORM_HOSTS`) nor a registered tenant with its own env file.

## Commands

All commands run on the platform (no `--domain`).

```bash
# create or resume; finished steps are skipped
php artisan ulams:tenant:create coffee --name="The Coffee Atlas" --theme=coffee --accent="#C2552D" --users=5

# run one step again
php artisan ulams:tenant:create coffee --redo=migrate

# demo mode (packages/demo): DEMO_MODE=true in the env file, login without password,
# hourly reset; only the env file is rewritten
php artisan ulams:tenant:create coffee --demo=on

php artisan ulams:tenant:list
php artisan ulams:tenant:list --hosts         # active API hosts, one per line

# rebuild env files, registrations and Passport keys from the tenants table (init.sh)
php artisan ulams:tenant:sync-env --migrate
# per-tenant S3 keys (ADR 0041): issue (or add) a key for every tenant, then drop the old ones
php artisan ulams:tenant:sync-env --rotate-storage-keys [--tenant=acme]
php artisan ulams:tenant:sync-env --prune-storage-keys
# create missing buckets and re-apply their public read policy (after moving object stores)
php artisan ulams:tenant:ensure-buckets

# AI settings (ANTHROPIC_API_KEY, AI_*) are inherited from the platform env; per-tenant override (ADR 0063)
php artisan ulams:tenant:set-env acme --set=AI_DRIVER=disabled

# drop database, role, bucket, env file, storage directory and Redis keys
php artisan ulams:tenant:delete coffee --force
```

## Platform API (off by default)

`/api/platform/tenants` and `/api/platform/operations` manage tenants over HTTP (ADR 0078, 0085). They exist only on a
platform host with `TENANCY_PLATFORM_API=true` (404 otherwise), for users with the `platform_admin` permission and
scoped tokens with `platform:read|write`. Creation and deletion are queued jobs (`ProvisionTenantJob`,
`DeleteTenantJob`) that record each step in `tenant_operations`; `TenantLifecycle` is the code the artisan commands
and the API share. CLI: `ulams tenants …`. Operator guide: the docs site, "Platform tenant API".

Steps of `ulams:tenant:create`, recorded in `tenants.steps`:

| Step | What it does |
|---|---|
| `database` | `CREATE ROLE` + `CREATE DATABASE` through the `pgsql_admin` connection |
| `bucket` | creates the bucket and a public read policy (AWS SDK) |
| `storage_identity` | `StorageIdentityProvisioner`: the tenant's own S3 key limited to its bucket (`seaweedfs` driver) or nothing (`none`); stored encrypted in `tenants.storage_key`/`storage_secret` |
| `env` | `domain:add <slug>.localhost` with the tenant values |
| `migrate` | `migrate --force` |
| `passport_keys` | `passport:keys`, copy stored encrypted on the tenant row |
| `passport_client` | `passport:client --personal` |
| `permissions` | `db:seed --class=PermissionsSeeder` (creates the admin user) |
| `lti_keys` | `ulams:lti:rotate-keys --init`: the tenant's LTI signing keys (ADR 0012) |
| `demo` | `ulams:tenant:seed-demo`: tutor, students, settings |

Every step from `migrate` on runs as `php artisan … --domain=<host>` in a child process.
An in-process `Artisan::call` would keep the platform configuration.

Demo users: `admin@<slug>.ulams.app`, `tutor@<slug>.ulams.app`,
`student1@<slug>.ulams.app` … `studentN@<slug>.ulams.app`, all with the password from
`TENANT_DEMO_PASSWORD` (default `secret`, development only).

## Configuration

See [src/config.php](src/config.php) and the tenancy section of
[docs/enviromental-variables.md](../../docs/enviromental-variables.md).

## Tests

```bash
vendor/bin/phpunit --testsuite tenancy
```

The end-to-end isolation test provisions two real tenants and is opt-in:

```bash
docker compose exec -T -e TENANCY_INTEGRATION=1 api vendor/bin/phpunit packages/tenancy/tests/Integration
```

The nightly workflow (`.github/workflows/nightly-conformance.yml`, job `tenancy`) runs it against the compose stack.
Every route is also probed in-process on each pull request: `api/tests/Integrations/TenantIsolationCoverageTest.php`
(ADR 0190).

## Entry points (ADR 0195)

`tenant_entry_points` (platform database) holds the branded front hosts of a tenant: kind `academy`, `department`,
`course` or `role`, a target, a theme override and SEO settings (plan `docs/plans/academy-entry-points.md`). An entry
point never grants access.

- The tenant admin API is `GET|POST /api/admin/entry-points`, `GET|PATCH|DELETE /api/admin/entry-points/{id}` and
  `GET /api/admin/entry-points/audit` (permission `entry_points_manage`, token scope `settings`, tenant hosts only).
  The academy is the one the process serves (`TENANT_SLUG`), never a request value.
- A tenant process has no credentials for the platform database: `PlatformEntryPointGateway` runs the hidden
  command `ulams:entry-points:gateway <slug> <operation> --payload=<base64 json>` in a platform child process, which
  prints one `ULAMS-JSON:` line. `EntryPointService` is the only writer of the table and audits every change in
  `tenant_entry_point_audits`. It fires `EntryPointsChanged` after each change.
- Hosts: `EntryHost` (DNS name, not an address, no `content` label, not a platform, academy or entry point host).
  Under `TENANCY_ENTRY_PLATFORM_SUFFIXES` a host is one `{slug}-name` label and active at once; elsewhere it is a
  custom domain, `pending_dns` until verified. `TENANCY_ENTRY_TRUSTED_SUFFIXES` is for development and CI.
- `TENANCY_MAX_ENTRY_POINTS` (20) limits them per tenant. CLI: `ulams entry-points list|get|create|update|delete|audit`.
- **Trusted entry hosts (EP-3).** A tenant process cannot read the platform table, so the platform copies an academy's
  active entry hosts into its own database (`tenant_entry_hosts`) after every change: `EntryHostPublisher` runs the hidden
  `ulams:entry-points:publish` in a process of the academy, and remembers a hash of what it published
  (`tenant_entry_host_publications`). `ulams:entry-points:reconcile` (scheduled every five minutes on the platform) republishes
  the academies whose copy is out of date. `Ulams\Core\Support\EntryFronts` reads the copy (`EntryHostStore`) for the origin
  check of the API, the return URLs of the redirect logins, the frame ancestors of interactive content and the session
  hand-off (`POST /api/auth/handoff`, auth package), so an entry host is trusted everywhere or nowhere.
