# Tenancy

## What does it do

Provisions and removes tenants of a multi-domain ulams API. One API deployment serves the
platform (`api.localhost`) and any number of tenants, each on its own host
(`<slug>.localhost`) with its own:

- PostgreSQL role and database (`ulams_<slug>`),
- MinIO/S3 bucket with public read (`ulams-<slug>`),
- `.env.<host>` file and storage directory (gecche/laravel-multidomain),
- `APP_KEY` and Passport key pair (a token from one tenant is rejected by every other),
- Redis key prefix for queues, cache and Horizon (`ulams_<slug>_`),
- demo users and the public settings the front reads (`global.companyName`,
  `global.frontURL`, `theme.theme`, `theme.accent`).

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

php artisan ulams:tenant:list
php artisan ulams:tenant:list --hosts         # active API hosts, one per line

# rebuild env files, registrations and Passport keys from the tenants table (init.sh)
php artisan ulams:tenant:sync-env --migrate

# drop database, role, bucket, env file, storage directory and Redis keys
php artisan ulams:tenant:delete coffee --force
```

Steps of `ulams:tenant:create`, recorded in `tenants.steps`:

| Step | What it does |
|---|---|
| `database` | `CREATE ROLE` + `CREATE DATABASE` through the `pgsql_admin` connection |
| `bucket` | creates the bucket and a public read policy (AWS SDK) |
| `env` | `domain:add <slug>.localhost` with the tenant values |
| `migrate` | `migrate --force` |
| `passport_keys` | `passport:keys`, copy stored encrypted on the tenant row |
| `passport_client` | `passport:client --personal` |
| `permissions` | `db:seed --class=PermissionsSeeder` (creates the admin user) |
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
