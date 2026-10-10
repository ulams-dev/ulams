# ulams staging on MyDevil s51: what was installed (2026-10-09, updated 2026-10-10: current main and the six demos)

A STAGING install on the product owner's MyDevil account (issue #159), done from the runbook in
`README.md`. The same text is kept on the server as `~/ulams/DEPLOY-NOTES.md`. No secrets are written
here: they are on the server only (see "Where the secrets are").

## Hosts (Cloudflare zone `ulams.app`, Free plan: Universal SSL covers one level, so names are flat)

| Host | What | Served by |
|---|---|---|
| `staging-api.ulams.app` | platform API | MyDevil, PHP 8.4 (shared app `~/ulams/api/public`) |
| `staging.ulams.app` | platform landing (Astro SSR) | MyDevil, Passenger, node22 |
| `staging-admin.ulams.app` | admin (static build) | MyDevil, static files `~/ulams/admin` |
| `staging-content.ulams.app` | content origin of the platform | Cloudflare Worker `ulams-staging-content` |
| `staging-storage.ulams.app` | public read of the R2 buckets, `/<bucket>/<key>` | Cloudflare Worker `ulams-staging-storage` |
| `staging-pdf.ulams.app` | PDF service (token protected) | MyDevil, Passenger, node22 |
| `staging-h5p.ulams.app` | H5P service | MyDevil, Passenger, node22 |
| `coffee-staging-api.ulams.app` | tenant `coffee` API | MyDevil, PHP 8.4 |
| `coffee-staging.ulams.app` | tenant `coffee` front (Astro SSR) | MyDevil, Passenger, node22 |
| `coffee-staging-admin.ulams.app` | tenant `coffee` admin | MyDevil, static |
| `coffee-staging-content.ulams.app` | tenant `coffee` content origin | Cloudflare Worker `ulams-staging-content` |
| `{gravity,poland,ulam,oncall,nightsky}-staging-api.ulams.app` | tenant APIs (added 2026-10-10) | MyDevil, PHP 8.4 |
| `{...}-staging.ulams.app` | tenant fronts | MyDevil, Passenger, node22, one process each, the same `~/ulams/web` build and the same `app.js` settings as the platform front |
| `{...}-staging-admin.ulams.app` | tenant admins | MyDevil, static (`public_html` -> `~/ulams/admin`) |
| `{...}-staging-content.ulams.app` | tenant content origins | Cloudflare Worker `ulams-staging-content` |

All six demos (coffee, oncall, nightsky, gravity, poland, ulam) run on staging: the platform landing `https://staging.ulams.app` shows six cards linking to
`<slug>-staging.ulams.app/learn/1` and `<slug>-staging-admin.ulams.app` (`ULAMS_DEMO_TENANTS` and `ULAMS_WARM_TENANTS` in every front's `app.js`). The budget held
(see "Resources"), so no card is shown as unavailable.

`/h5p/*` on both API hosts is routed by the Worker `ulams-staging-h5p-proxy` to `staging-h5p.ulams.app`
with `X-Forwarded-Host` (the Caddy snippet of `api/h5p` has no MyDevil equivalent: a MyDevil host is PHP
or Node, never both).

Tenancy patterns in `~/ulams/api/.env` (the flat form works as is, no code change): `TENANCY_API_HOST={slug}-staging-api.ulams.app`,
`TENANCY_FRONT_HOST={slug}-staging.ulams.app`, `TENANCY_ADMIN_HOST={slug}-staging-admin.ulams.app`,
`TENANCY_CONTENT_HOST={slug}-staging-content.ulams.app`, `TENANCY_PLATFORM_HOSTS=staging-api.ulams.app`.
The Astro front uses `ULAMS_TENANT_HOSTS={slug}-staging.ulams.app=>https://{slug}-staging-api.ulams.app`
(set in each `app.js`), the admin was built with `REACT_APP_TENANT_API_HOST_PATTERN={slug}-staging-admin.ulams.app=>https://{slug}-staging-api.ulams.app`.

## MyDevil objects created

- Sites (`devil www add`): the 8 hosts marked MyDevil above and, for each of the five added tenants, `<slug>-staging-api` (php), `<slug>-staging-admin` (php, `public_html` symlink to `~/ulams/admin`) and `<slug>-staging` (nodejs) via `bin/add-flat-hosts.sh <slug>`. Origin certificate (Cloudflare Origin CA, covers
  `ulams.app` and `*.ulams.app`, valid to 2041) installed with SNI per host: `devil ssl www add 77.79.248.122 ~/ulams/tls/origin.pem ~/ulams/tls/origin.key <host>`.
- PHP sites: `php_openbasedir` extended with `~/ulams`, `php_exec on`, `sslonly on`. Passenger sites: `processes 1`.
  The placeholder `public/index.html` of each Node site was removed (Passenger served it instead of the app).
- PostgreSQL (`devil pgsql db add`): `p1157_ulams` (platform), `p1157_u_coffee` (tenant coffee), and since 2026-10-10 `p1157_u_gravity`, `p1157_u_poland`, `p1157_u_ulam`, `p1157_u_oncall`, `p1157_u_nightsky` (passwords in `~/ulams/.dbpass.<slug>`; `devil` demands at least one digit, one lower and one upper case letter, fed on stdin). **MyDevil truncates
  database names at 16 characters including the `p1157_` prefix**, so `TENANCY_DATABASE=p1157_u_{slug}` (slugs up to 10 characters).
  The H5P service uses the tenant database (schema `h5p`), no extra database.
- Port reserved: `22166/tcp` (Redis on 127.0.0.1, password; the H5P service cannot use a unix socket).
- Redis: `redis-server` in `screen` (`ulams-redis`) via `bin/redis-start.sh` with `ULAMS_REDIS_PORT=22166`. No binexec needed.
- **Binexec: NOT enabled** (nothing needed it). Other account settings are unchanged.
- Crontab (saved before as `~/ulams/crontab.before.txt`: the account had no crontab): the five lines of `crontab`
  (default, builder, long queue passes every minute under `flock`; backup 03:17; log rotation) plus
  `ULAMS_REDIS_PORT=22166`, `@reboot` and `*/5` Redis start lines.
- Interactive demo packages (`gravity.zip`, `poland.zip`, `ulam-*.zip`, built locally with `make -C api demo-packages`) are uploaded to `~/ulams/api/database/seeds/Demo/assets/cache/interactive/`; `upgrade.sh` keeps that cache directory.
- Files: `~/ulams/{api,bin,admin,web,pdf,h5p,h5p-config,h5p-data,redis,tls,backups,logs,releases,run}`; `~/domains/<host>` for the new hosts only.

## Cloudflare objects created (zone `ulams.app`, account `b754865d6ccf05c90d33e35f213c7214`)

- DNS, proxied A to 77.79.248.122: `staging`, `staging-api`, `staging-admin`, `coffee-staging`, `coffee-staging-api`, `coffee-staging-admin`,
  `staging-pdf`, `staging-h5p`. The Worker custom domains `staging-content`, `coffee-staging-content`, `staging-storage` created their own records.
- 2026-10-10: DNS (proxied A to 77.79.248.122) `<slug>-staging`, `<slug>-staging-api`, `<slug>-staging-admin` for gravity, poland, ulam, oncall, nightsky; Worker custom domains `<slug>-staging-content` (service `ulams-staging-content`);
  Worker routes `<slug>-staging-api.ulams.app/h5p*` (`ulams-staging-h5p-proxy`); R2 buckets `ulams-staging-<slug>`; the storage Worker has a binding for each bucket;
  the policy of the token `ulams-staging-r2-server` lists the five new buckets too (the credentials on the server did not change).
  `ulams-staging-content` now also allows `/interactive/*` and keeps the CSP that the API sets for a package version. Sources of the three Workers: `deploy/mydevil/cloudflare/`.
- Zone settings: SSL mode `strict` (was `full`), Always Use HTTPS `on`.
- Rulesets: response header `X-Robots-Tag: noindex, nofollow, noarchive` for every host containing `staging` (`http_response_headers_transform`);
  request header `X-Ulams-Content-Origin` removed on `*staging-api.ulams.app` (`http_request_late_transform`, verified: a client that sends it gets 404, the content Worker still works).
- Workers: `ulams-staging-storage` (R2 bindings for the two buckets; a new tenant needs a binding added), `ulams-staging-content`, `ulams-staging-h5p-proxy`
  (routes `staging-api.ulams.app/h5p*`, `coffee-staging-api.ulams.app/h5p*`).
- R2 buckets: `ulams-staging`, `ulams-staging-coffee` (a new tenant's bucket `ulams-staging-<slug>` must be created by hand: the server token cannot create buckets).
- API token `ulams-staging-r2-server` (R2 object read/write on those two buckets only) is the only Cloudflare credential on the server (`~/ulams/.r2.env`, `api/.env`).
  The admin token never left the deploy machine.
- Origin CA certificate id `29885075380667042202298245871321027097927949785`.

## Where the secrets are (server only, mode 600)

`~/ulams/api/.env` (APP_KEY, DB, R2, service tokens), `~/ulams/.admin-credentials` (platform admin and demo password: `INITIAL_USER_EMAIL`,
`INITIAL_USER_PASSWORD`, `TENANT_DEMO_PASSWORD`), `~/ulams/.dbpass.platform`, `~/ulams/.dbpass.coffee`, `~/ulams/.r2.env`, `~/ulams/tls/origin.key`.
Keep APP_KEY in a password manager (ADR 0066). `AI_DRIVER=fake`, `ANTHROPIC_API_KEY` is empty.

## Findings and fixes (all in this PR)

- `setup.sh` ran `init-keys.sh` before `migrate` (the Passport client insert failed): migrations first now; the keys are also linked into `storage/app`
  (over HTTP the platform host reads them there, the CLI reads `storage/`: "Invalid key supplied" otherwise).
- `CACHE_DRIVER=database` cannot work: no migration creates the `cache` table. The template uses `file`.
- `backup.sh` treated `.env.example` as a tenant.
- `redis-start.sh` can listen on a reserved loopback port (`ULAMS_REDIS_PORT`).
- Astro: the `/_image` endpoint needs `sharp`, which has no FreeBSD binary: `ULAMS_IMAGE_SERVICE=noop` at build time serves the original image.
- H5P service: `@node-rs/crc32` has a FreeBSD binary (`@node-rs/crc32-freebsd-x64`, copied into `node_modules` on the server; the macOS one removed).
  The service calls the API for the user profile on `https://127.0.0.1` with the tenant `Host` header and `NODE_TLS_REJECT_UNAUTHORIZED=0` (loopback only).
- `api/public/robots.txt` disallows everything (an API host is never meant to be indexed).
- `php_openbasedir` also lists `/home/wojczal/ulams` (the home directory is reachable under two paths); `~/ulams` must be mode 711 (nginx traverses it).
- Intermittent: one `ulams:demo:reset` run failed in `getimagesize()` of the R2 endpoint URL (not the public URL) twice and then passed twice; cause not found. Watch the hourly demo reset.

## Update 2026-10-10 (main at `45a191f1`)

- `upgrade.sh` with the release tarball; migrations ran for the platform and coffee. The front (`front/web/dist`, built with `ULAMS_IMAGE_SERVICE=noop`, plus `front/ui`, `front/sdk`, `front/interactive-bridge`) and the admin
  (`REACT_APP_API_URL=https://staging-api.ulams.app`, `REACT_APP_TENANT_API_HOST_PATTERN={slug}-staging-admin.ulams.app=>https://{slug}-staging-api.ulams.app`) were rebuilt locally and rsynced.
- Env: no new variable in `.env.example` since the install; the lean-workers mode is a Docker setting and does not apply to the cron passes. `AI_DRIVER=fake` unchanged.
- The branch `fix/runtime-no-dev-deps` was not merged on main, so the release has a `--no-dev` vendor tree: the Course Builder apply was not exercised on staging and may fail.
- New tenants: `ulams:tenant:create <slug> --name --theme --accent --db-password --demo=on`, then `db:seed --class=DemoCoursesSeeder` with `DEMO_EXPERIENCE=<slug>`; the cron lines needed no change
  (`domains.sh` lists every tenant). A reset of gravity took 54 s; the six hourly resets run one after another in the `default` pass.
- Fixed in the scripts: `upgrade.sh` made the Passport keys mode 775 (the platform API answered 500 until `chmod 600`, about 20 minutes), now 600; `upgrade.sh` keeps the demo cache directory;
  `add-vhost.sh` picked the server's own IP (`devil ssl www add` refused it), now the `webN` address (`lib.sh` `public_ip`, or `ULAMS_PUBLIC_IP`).
- Not verified: playing the landing showcase and an interactive in a real browser (the browser extension was not connected). Verified over HTTP: every front, API config, admin, demo logins, a lesson page, the content-origin iframe (200 with CSP) and the progress ping (API and the front's `/bff`).

## Update 2026-10-10, second pass (main at `2861aec6`)

- Redeployed with `upgrade.sh` (release `ulams-2861aec6.tar.gz`, built with `build-release.sh`), then the front (`ULAMS_IMAGE_SERVICE=noop astro build`, plus `front/ui`, `front/sdk`, `front/interactive-bridge` rsynced) and the admin
  (same two `REACT_APP_*` variables as above) rebuilt locally and rsynced; every front restarted with `tmp/restart.txt`. The release has the no-dev runtime fixes (#217) and the landing Docs link and demo order (#218).
- Passport keys stay mode 600 after `upgrade.sh` (verified: `storage/oauth-*.key` `-rw-------`, the `storage/app` links intact).
- Verified over HTTP: landing shows the Docs link and the cards gravity, poland, ulam, coffee, oncall, nightsky; all seven `/api/config` and all fronts and admins answer 200; the demo login of each front
  (`POST /login` with `demo=1` and an `Origin` header, otherwise Astro answers 403) opens `/account` and the first lesson (`/learn/1/1`); `POST /api/auth/login` of the platform admin and of `admin@<slug>.ulams.app` answers 200.
- Course Builder apply on coffee (`AI_DRIVER=fake`, `ulams builder start --from <md> --defaults --approve-outline --apply` with a profile for `https://coffee-staging-api.ulams.app`): the session reached `applied` and created a draft course, so #217 holds on staging.
  The test course and session were deleted afterwards. The first attempt returned one 500 while polling (`file_put_contents` in `framework/cache/data/..: No such file or directory` at provider boot, a transient race on the file cache); the retry on the same session passed.
- Found: the scheduled hourly `ulams:demo:reset` has failed on every tenant since the install (`The "--force" option does not accept a value`: `Schedule::command()` renders `['--force' => true]` as `--force=1`). Fixed in PR #219; until it is deployed the demos are not reset hourly.

## Unknowns from the README, now answered

| Question | Answer |
|---|---|
| Cron every minute | works (`* * * * *`, three lines under `flock`) |
| Long cron process | a `long` pass with two ffmpeg conversions ran about 1 minute; limit for hours-long runs untested |
| Redis without binexec | works (system binary in `screen`) |
| Passenger with Astro | works; the first request after a restart takes a few seconds |
| H5P under Passenger | works after the FreeBSD crc32 binding; libraries install from the Hub (25 installed) |
| ffmpeg | the demo videos were converted to HLS on the account |
| Wildcard hosts | not needed; flat names work, every host is its own `devil www` site |
| `Authorization` header to PHP | works (login returns a token) |
| pcntl | present; `opcache` CLI extension is not listed (not needed) |

## Verified from the deploy machine (curl)

Platform and coffee `/api/config` 200; platform admin login returns a token; admin SPA loads on both hosts; coffee front, platform landing 200; demo login
(`POST /login`, `demo=1`) sets the session; 19 lessons open (video HLS from R2, image, rich text, audio, OEmbed, PDF, SCORM, LiaScript, cmi5, GIFT quiz,
project); H5P embed page, core and params answer 200 through the front; the SCORM player file is served by the content origin; `/_image` works;
Studio (`/studio`, `/studio/new`) loads with `AI_DRIVER=fake`. Playing an H5P or SCORM item in a real browser was not done.

## Resources

2026-10-10, after the six demos and a reset: 19 processes of the account (limit 70), about 1.4 GB resident in total including the other sites' mail processes. Seven Passenger fronts of about 145 to 180 MB each, h5p 285 MB, Redis 13 MB.
The first install (idle, a few minutes after the last request):

18 processes of the account (limit 70). Resident memory: Passenger node apps pdf 268 MB, h5p 200 MB, platform front 157 MB, coffee front 145 MB; php-fpm workers about 125 MB each
(shared with the other PHP sites); Redis 13 MB. A cron pass takes 4 seconds for `default` and `builder`; a video pass up to a minute with ffmpeg.

## Not done / later

- Real mail (`MAIL_DRIVER=log`), Anthropic key (owner decision, `AI_DRIVER=fake`), Cloudflare Workers for the fronts (the Astro app can move there).
- The Cloudflare admin token used for this deploy is temporary and must be revoked by the owner (the token used on 2026-10-10 expires 2026-10-16).
- Risks: the hourly resets of six tenants run sequentially (about 50 s each); a reset that outlives the `--timeout=60` of its pass would fail until the next hour. The VPS `Caddyfile` of `deploy/vps-cloudflare` does not list `/interactive/*` in its content-origin path matcher (#215).

## Remove everything

```sh
# on the server
crontab -r                                            # it had no crontab before; ~/ulams/crontab.before.txt
screen -S ulams-redis -X quit; devil port del tcp 22166
for h in staging-api staging-admin coffee-staging-api coffee-staging-admin staging coffee-staging staging-pdf staging-h5p \
  $(for s in gravity poland ulam oncall nightsky; do echo $s-staging-api $s-staging-admin $s-staging; done); do
  devil ssl www del 77.79.248.122 $h.ulams.app; devil www del $h.ulams.app --remove; done
for d in coffee gravity poland ulam oncall nightsky; do devil pgsql db del p1157_u_$d; done; devil pgsql db del p1157_ulams
rm -rf ~/ulams
# Cloudflare (zone ulams.app): delete the DNS records and Worker domains above, the Workers and routes, the two rulesets,
# the R2 buckets of all six tenants (empty them first), the DNS records and Worker domains `<slug>-staging*` of the five added tenants and the token `ulams-staging-r2-server`, revoke the Origin CA certificate, set SSL mode back to `full`.
```
