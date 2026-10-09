# Demo

## What does it do

Turns a tenant into a public demo that anyone can try without an account:

- `POST /api/demo/login {"role": "student" | "tutor" | "admin"}` logs the visitor in as the tenant's
  seeded student, tutor or admin, without a password. It issues a regular Passport personal access
  token and answers with the same body as `POST /api/auth/login`, so the front and the admin
  store it like after a normal login. The demo student is first given access to every
  published course, so any course link opens with access.
- `GET /api/demo` returns `{enabled: true, users: [{role, email}], front_url, admin_url,
  reset_cron}`. It never returns passwords.
- `GET /api/config` gains `ulams_demo: {enabled, front_url, admin_url}`. The front and the
  admin read it at boot and log in automatically (front as the student, admin as the admin).
- `ulams:demo:reset` wipes the tenant and seeds it again; the scheduler runs it hourly.

**Demo mode has no security.** Anyone who can reach the API can act as the tenant admin. Turn
it on only for throwaway demo tenants whose data is wiped every hour, never for a tenant with
real data, and never on the platform.

## Turning it on

Demo mode is on when the tenant's `.env.<host>` has `DEMO_MODE=true`. Default is off: then the
routes do not exist (404), nothing is added to `/api/config`, and no reset is scheduled.

Set it through the tenant registry so that `ulams:tenant:sync-env` keeps it:

```bash
php artisan ulams:tenant:create coffee --demo=on    # or --demo=off; only the env file is rewritten
make demo-mode-on                                   # the six demo tenants: coffee, oncall, nightsky, gravity, poland, ulam
```

## Accounts

| Role | Account | Override |
|---|---|---|
| admin | `INITIAL_USER_EMAIL` (`admin@<slug>.ulams.app`, created by `PermissionsSeeder`) | `DEMO_ADMIN_EMAIL` |
| student | `student1@<admin e-mail domain>` (created by `ulams:tenant:seed-demo`) | `DEMO_STUDENT_EMAIL` |
| tutor | `tutor@<admin e-mail domain>` (created by `ulams:tenant:seed-demo`; the course author in the studio) | `DEMO_TUTOR_EMAIL` |

If the account does not exist, the first user with that role is used.

## Hourly reset

```bash
php artisan ulams:demo:reset --force --domain=coffee.localhost
make demo-reset DOMAIN=coffee.localhost
```

The command refuses to run unless `DEMO_MODE=true` on that domain, and always refuses on the
platform. First it deletes the tenant's H5P content in the H5P service (api/h5p), in
process. The service keeps that content in schema `h5p` of the tenant database and in the
tenant bucket, which `migrate:fresh` does not touch, so without this step every reset would
add another copy of the demo content:

- `DELETE /h5p/contents/{id}` for every id the tenant database knows (`h5p.contents` rows and
  `topic_h5ps.value`),
- `POST /h5p/contents/orphans/delete`: files of contents that have no row any more.

Both calls go through the h5p package's client, which sends the tenant host and the internal
token; the service scopes them to that tenant's schema and bucket. If the service cannot be
reached, the reset goes on with a warning: the rows stay in `h5p.contents`, so the next reset
deletes them.

Then it runs these steps, each as a child `php artisan … --domain=<host>`:

1. `migrate:fresh --force`: the whole tenant database, including the OAuth tables, so every
   token issued so far stops working. The front and the admin then log in again by
   themselves.
2. `passport:client --personal` (the personal access client lived in the wiped database).
3. `db:seed --class=PermissionsSeeder` (roles and the admin account).
4. `ulams:tenant:seed-demo` with the baseline (below): tutor, students, name, theme, accent.
5. `ulams:demo:seed`: the demo courses (`DemoCoursesSeeder`, experience = tenant slug) and
   access to every published course for the demo student.

The tenant's Redis cache keys are deleted after step 1 and at the end. `cache:clear` is not
used because the Redis cache store is shared by all tenants. With `--wipe-files` (or
`DEMO_RESET_WIPE_FILES=true`) every file in the tenant bucket is deleted first; by default
uploads stay, and the demo assets are uploaded again at the same paths.

**Baseline.** Name, theme, accent, front URL, number of students and the e-mail domain live
in the database being wiped. The first reset captures them into
the tenant storage directory
(`storage/coffee_localhost/app/ulams-demo-baseline.json`), and later resets reuse that file. A theme
change made in the demo admin therefore does not survive the next reset.
`--recapture` takes a new baseline from the current database.

**Schedule.** The provider schedules `ulams:demo:reset --force --domain=<host>` hourly
(`DEMO_RESET_CRON`, default `0 * * * *`) on every domain whose env has `DEMO_MODE=true`.
`scheduler.sh` already runs `schedule:run --domain=<host>` for every tenant, so nothing else
needs to be set up. The command runs in the background, without overlapping.

`ulams:demo:seed --skip-content` only grants the student access to the published courses, for
example after courses were created by hand.

## Configuration

See [src/config.php](src/config.php) and the demo mode section of
[docs/enviromental-variables.md](../../docs/enviromental-variables.md).

## Tests

```bash
vendor/bin/phpunit --testsuite demo
```

The feature tests never wipe the test database: the reset steps run through a fake command
runner. The tenant isolation test switches the Passport key pair to show that a token of one
tenant is rejected by another. The end-to-end check against the running demo tenants is
opt-in:

```bash
docker compose exec -T -e DEMO_INTEGRATION=1 api vendor/bin/phpunit packages/demo/tests/Integration
```

`DemoResetH5PTest` also needs `DEMO_INTEGRATION_RESET=1`: it resets `coffee.localhost` twice
and checks that the H5P content count is the same after both resets.
