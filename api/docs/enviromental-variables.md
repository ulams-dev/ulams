# Environment Variables

Application is designed to be stateless - it is controlled by environmental variables

## `LARAVEL_` prefix

Each Variable that that has `LARAVEL_` is converted nto variable set for `.env` file for [Laravel Environment Configuration](https://laravel.com/docs/11.x/configuration#environment-configuration). Note that `.env` is ephemeral and created each time service restarts.

Example

```bash
LARAVEL_APP_NAME=Ulams
LARAVEL_APP_ENV=local
```

will be saved into `.env` as

```bash
APP_NAME=Ulams
APP_ENV=local
```

## `MULTI_DOMAINS` List of domains and specic domain variable

`MULTI_DOMAINS` has comma separated list of domains that will be used for [init_multidomains.sh](init-script.md) script

Each domain can have a specific [Laravel Environment Configuration](https://laravel.com/docs/11.x/configuration#environment-configuration). Note that each domain `.env` is ephemeral and created each time service restarts.

Example

```bash
MULTI_DOMAINS=api17005.localhost,api16576.localhost,api22800.localhost
API17005_LOCALHOST_APP_NAME="App one"
API16576_LOCALHOST_APP_NAME="App two"
API22800_LOCALHOST_APP_NAME="App three"
API22800_LOCALHOST_INITIAL_USER_PASSWORD="password one"
API16576_LOCALHOST_INITIAL_USER_PASSWORD="password one"
API17005_LOCALHOST_INITIAL_USER_PASSWORD="password two"
```

Wil create three `.env` for each domain prefixed

```bash
#.env.api17005.localhost file
APP_NAME="App one"
INITIAL_USER_PASSWORD="password one"
```

```bash
#.env.api16576.localhost file
APP_NAME="App two"
INITIAL_USER_PASSWORD="password one"
```

```bash
#.env.api22800.localhost file
APP_NAME="App three"
INITIAL_USER_PASSWORD="password two"
```

## List of domains

Each

| Variable name                           | Description                                                      | Default             |
| --------------------------------------- | ---------------------------------------------------------------- | ------------------- |
| `LARAVEL_` prefix                       | main Laravel Environment Configuration                           |                     |
| `${DOMAIN_KEY}_` prefix                 | domain specific Laravel Environment Configuration                |                     |
| `MULTI_DOMAINS`                         | Comma separated list of multidomains                             |                     |
| `DISABLE_PHP_FPM`                       | Disable PHP FPM Supervisor process                               | false               |
| `DISABLE_HORIZON`                       | Disable Laravel Horizon Supervisor process                       | false               |
| `DISABLE_SCHEDULER`                     | Disable Laravel Scheduler Supervisor process                     | false               |
| `JWT_PUBLIC_KEY_BASE64`                 | Base64 encoded `storage/oauth-public.key`                        |                     |
| `JWT_PRIVATE_KEY_BASE64`                | Base64 encoded `storage/oauth-private.key`                       |                     |
| `${DOMAIN_KEY}_JWT_PUBLIC_KEY_BASE64`   | Base64 encoded `storage/oauth-public.key` for domain             |                     |
| `${DOMAIN_KEY}_JWT_PRIVATE_KEY_BASE64`  | Base64 encoded `storage/oauth-private.key` for domain            |                     |
| `DISABLE_DB_MIGRATE`                    | Disable Laravel Database migration on startup                    | false               |
| `DISABLE_DB_SEED`                       | Disable Laravel Permissions Database Seed migration on startup   | false               |
| `DISABLE_QUEUE`                         | Disable the tenant/multidomain queue workers (`queue.sh`)        | false               |
| `DISABLE_TENANT_SYNC`                   | Skip `ulams:tenant:sync-env` on startup                          | false               |
| `INITIAL_USER_PASSWORD`                 | Initial admin password                                           |                     |
| `INITIAL_USER_FIRST_NAME`               | Initial admin first name                                         | Root                |
| `INITIAL_USER_LAST_NAME`                | Initial admin last name                                          | Admin               |
| `INITIAL_USER_EMAIL`                    | Initial admin email                                              | admin@ulams.app |
| `${DOMAIN_KEY}_INITIAL_USER_PASSWORD`   | Initial admin password for domain                                |                     |
| `${DOMAIN_KEY}_INITIAL_USER_FIRST_NAME` | Initial admin first name for domain                              | Root                |
| `${DOMAIN_KEY}_INITIAL_USER_LAST_NAME`  | Initial admin last name for domain                               | Admin               |
| `${DOMAIN_KEY}_INITIAL_USER_EMAIL`      | Initial admin email for domain                                   | admin@ulams.app |

## Redis prefixes

Every domain shares one Redis. Set by `ulams:tenant:create` for each tenant
(`ulams_<slug>_…`); the platform uses the defaults.

| Variable name    | Description                                      | Default                       |
| ---------------- | ------------------------------------------------ | ----------------------------- |
| `REDIS_PREFIX`   | Prefix of every Redis key (queues, cache, locks) | `<slug of APP_NAME>_database_` |
| `CACHE_PREFIX`   | Cache store prefix (inside `REDIS_PREFIX`)       | `<slug of APP_NAME>_cache`     |
| `HORIZON_PREFIX` | Horizon metadata prefix                          | `<slug of APP_NAME>_horizon:`  |

## Tenancy (`packages/tenancy`)

Read on the platform by `ulams:tenant:*`, see [multidomain.md](multidomain.md).
`{slug}` is replaced with the tenant slug.

| Variable name                | Description                                                                                  | Default                                      |
| ---------------------------- | -------------------------------------------------------------------------------------------- | -------------------------------------------- |
| `TENANT_DEMO_PASSWORD`       | Password of all demo users of new tenants. **Development only**                             | `secret`                                     |
| `TENANCY_ENFORCE_HOSTS`      | Answer 404 to hosts that are neither platform hosts nor provisioned tenants                 | `true`                                       |
| `TENANCY_PLATFORM_HOSTS`     | Comma separated hosts served by the platform `.env` (add k8s service/ingress hosts here)     | `api.localhost,localhost,127.0.0.1,caddy,api` |
| `TENANCY_SCHEME`             | Scheme of tenant URLs                                                                         | `http`                                       |
| `TENANCY_API_HOST`           | Tenant API host                                                                               | `{slug}.localhost`                           |
| `TENANCY_FRONT_HOST`         | Tenant front host (`global.frontURL`, `FRONTEND_URL`)                                        | `{slug}.app.localhost`                       |
| `TENANCY_ADMIN_HOST`         | Tenant admin panel host                                                                       | `{slug}.admin.localhost`                     |
| `TENANCY_EMAIL_DOMAIN`       | Domain of demo user e-mails and `MAIL_FROM_ADDRESS`                                          | `{slug}.ulams.app`                           |
| `TENANCY_DATABASE`           | Tenant database and role name                                                                | `ulams_{slug}`                               |
| `TENANCY_BUCKET`             | Tenant bucket                                                                                 | `ulams-{slug}`                               |
| `TENANCY_REDIS_PREFIX`       | Tenant `REDIS_PREFIX`                                                                         | `ulams_{slug}_`                              |
| `TENANCY_STORAGE_PUBLIC_URL` | Public object store URL; the tenant `AWS_URL` is this plus `/<bucket>`                       | `http://storage.localhost`                   |
| `TENANCY_S3_ENDPOINT`        | Object store endpoint used to create buckets                                                 | `AWS_ENDPOINT`                               |
| `TENANCY_S3_KEY`             | Object store access key used to create buckets                                               | `AWS_ACCESS_KEY_ID`                          |
| `TENANCY_S3_SECRET`          | Object store secret used to create buckets                                                   | `AWS_SECRET_ACCESS_KEY`                      |
| `TENANCY_S3_REGION`          | Object store region                                                                           | `AWS_DEFAULT_REGION`                         |
| `DB_ADMIN_HOST`              | Host of the `pgsql_admin` connection (creates tenant roles and databases)                   | `DB_HOST`                                    |
| `DB_ADMIN_PORT`              | Port of the `pgsql_admin` connection                                                         | `DB_PORT`                                    |
| `DB_ADMIN_DATABASE`          | Database the `pgsql_admin` connection logs in to                                             | `DB_DATABASE`                                |
| `DB_ADMIN_USERNAME`          | Role with `CREATEROLE` and `CREATEDB`                                                        | `DB_USERNAME`                                |
| `DB_ADMIN_PASSWORD`          | Its password                                                                                  | `DB_PASSWORD`                                |
| `TENANCY_PHP_BINARY`         | PHP binary for tenant subprocesses                                                           | current PHP binary                           |
| `TENANCY_PROCESS_TIMEOUT`    | Timeout of one tenant subprocess, seconds                                                    | `900`                                        |
| `WORKERS_CHECK_INTERVAL`     | Seconds between `workers.sh` checks of the domain list and of exited processes               | `10`                                         |
| `WORKERS_MAX_TIME`           | Seconds a per-domain worker or scheduler loop runs before it is restarted (fresh code/config) | `3600`                                       |

Set per tenant in `.env.<host>` (do not set them on the platform): `TENANT_SLUG`,
`REDIS_PREFIX`, `CACHE_PREFIX`, `HORIZON_PREFIX`, `INITIAL_USER_EMAIL`,
`INITIAL_USER_PASSWORD`, `FRONTEND_URL`, `ADMIN_URL`, `DEMO_MODE`.

## Demo mode (`packages/demo`)

Read inside a tenant. `DEMO_MODE` is written by `ulams:tenant:create <slug> --demo=on|off`;
the others are optional overrides. See [packages/demo/README.md](../packages/demo/README.md).

| Variable name           | Description                                                                         | Default                          |
| ----------------------- | ----------------------------------------------------------------------------------- | -------------------------------- |
| `DEMO_MODE`             | Login without password (`/api/demo/*`) and an hourly reset. **Never on production data** | `false`                     |
| `DEMO_ADMIN_EMAIL`      | Account of the automatic admin login                                                | `INITIAL_USER_EMAIL`             |
| `DEMO_STUDENT_EMAIL`    | Account of the automatic student login                                              | `student1@<admin e-mail domain>` |
| `DEMO_ADMIN_URL`        | Admin panel link on the front's demo badge                                          | `ADMIN_URL`                      |
| `DEMO_RESET_SCHEDULE`   | Schedule `ulams:demo:reset`                                                          | `true`                           |
| `DEMO_RESET_CRON`       | When                                                                                 | `0 * * * *`                      |
| `DEMO_RESET_WIPE_FILES` | Also delete every file in the tenant bucket on reset                                | `false`                          |
| `DEMO_RESET_STUDENTS`   | Demo students when no baseline was captured                                         | `5`                              |
| `DEMO_CONTENT_SEEDER`   | Seeder of the demo courses                                                           | `Database\Seeders\DemoCoursesSeeder` |
| `ULAMS_DEMO_EXPERIENCE` | Demo course(s) to seed                                                               | `TENANT_SLUG`                    |

## Performance and runtime profile

| Variable name                       | Description                                                                                          | Default                    |
| ----------------------------------- | ---------------------------------------------------------------------------------------------------- | -------------------------- |
| `DEMO_PERF`                         | Demo profile of the api container (`make demo-up`): production PHP settings, cached config/routes/events, vendor in a named volume. See `php-profile.sh` | `0`                        |
| `ULAMS_OPTIMIZE`                    | Build config/route/event/view caches per domain at start (set in the production image)               | `false` (`true` in `Dockerfile`) |
| `LOG_LEVEL`                         | Minimum log level of the file/stderr channels (production template: `warning`)                      | `APP_LOG_LEVEL`, then `debug` |
| `REDIS_CLIENT`                      | `phpredis` (C extension) or `predis`                                                                  | `phpredis`                 |
| `RESPONSE_CACHE_DRIVER`             | Cache store of the response cache; must support tags (Redis) for targeted invalidation               | the default cache store    |
| `HTTP_CACHE_PUBLIC`                 | `Cache-Control: public, s-maxage` on anonymous catalogue GETs (`config/http_cache.php`)              | `true`                     |
| `HTTP_CACHE_S_MAXAGE`               | Shared-cache lifetime of those responses, seconds                                                    | `60`                       |
| `HTTP_CACHE_STALE_WHILE_REVALIDATE` | Seconds a shared cache may serve them stale while it revalidates                                     | `300`                      |

