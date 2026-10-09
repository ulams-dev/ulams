# ulams staging on MyDevil s51: what was installed (2026-10-09)

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

`/h5p/*` on both API hosts is routed by the Worker `ulams-staging-h5p-proxy` to `staging-h5p.ulams.app`
with `X-Forwarded-Host` (the Caddy snippet of `api/h5p` has no MyDevil equivalent: a MyDevil host is PHP
or Node, never both).

Tenancy patterns in `~/ulams/api/.env` (the flat form works as is, no code change): `TENANCY_API_HOST={slug}-staging-api.ulams.app`,
`TENANCY_FRONT_HOST={slug}-staging.ulams.app`, `TENANCY_ADMIN_HOST={slug}-staging-admin.ulams.app`,
`TENANCY_CONTENT_HOST={slug}-staging-content.ulams.app`, `TENANCY_PLATFORM_HOSTS=staging-api.ulams.app`.
The Astro front uses `ULAMS_TENANT_HOSTS={slug}-staging.ulams.app=>https://{slug}-staging-api.ulams.app`
(set in each `app.js`), the admin was built with `REACT_APP_TENANT_API_HOST_PATTERN={slug}-staging-admin.ulams.app=>https://{slug}-staging-api.ulams.app`.

## MyDevil objects created

- Sites (`devil www add`): the 8 hosts marked MyDevil above. Origin certificate (Cloudflare Origin CA, covers
  `ulams.app` and `*.ulams.app`, valid to 2041) installed with SNI per host: `devil ssl www add 77.79.248.122 ~/ulams/tls/origin.pem ~/ulams/tls/origin.key <host>`.
- PHP sites: `php_openbasedir` extended with `~/ulams`, `php_exec on`, `sslonly on`. Passenger sites: `processes 1`.
  The placeholder `public/index.html` of each Node site was removed (Passenger served it instead of the app).
- PostgreSQL (`devil pgsql db add`): `p1157_ulams` (platform), `p1157_u_coffee` (tenant coffee). **MyDevil truncates
  database names at 16 characters including the `p1157_` prefix**, so `TENANCY_DATABASE=p1157_u_{slug}` (slugs up to 10 characters).
  The H5P service uses the tenant database (schema `h5p`), no extra database.
- Port reserved: `22166/tcp` (Redis on 127.0.0.1, password; the H5P service cannot use a unix socket).
- Redis: `redis-server` in `screen` (`ulams-redis`) via `bin/redis-start.sh` with `ULAMS_REDIS_PORT=22166`. No binexec needed.
- **Binexec: NOT enabled** (nothing needed it). Other account settings are unchanged.
- Crontab (saved before as `~/ulams/crontab.before.txt`: the account had no crontab): the five lines of `crontab`
  (default, builder, long queue passes every minute under `flock`; backup 03:17; log rotation) plus
  `ULAMS_REDIS_PORT=22166`, `@reboot` and `*/5` Redis start lines.
- Files: `~/ulams/{api,bin,admin,web,pdf,h5p,h5p-config,h5p-data,redis,tls,backups,logs,releases,run}`; `~/domains/<host>` for the new hosts only.

## Cloudflare objects created (zone `ulams.app`, account `b754865d6ccf05c90d33e35f213c7214`)

- DNS, proxied A to 77.79.248.122: `staging`, `staging-api`, `staging-admin`, `coffee-staging`, `coffee-staging-api`, `coffee-staging-admin`,
  `staging-pdf`, `staging-h5p`. The Worker custom domains `staging-content`, `coffee-staging-content`, `staging-storage` created their own records.
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

## Resources (idle, a few minutes after the last request)

18 processes of the account (limit 70). Resident memory: Passenger node apps pdf 268 MB, h5p 200 MB, platform front 157 MB, coffee front 145 MB; php-fpm workers about 125 MB each
(shared with the other PHP sites); Redis 13 MB. A cron pass takes 4 seconds for `default` and `builder`; a video pass up to a minute with ffmpeg.

## Not done / later

- Real mail (`MAIL_DRIVER=log`), Anthropic key (owner decision, `AI_DRIVER=fake`), tenants `oncall` and `nightsky` (each needs a database, R2 bucket, Worker binding, three hosts), Cloudflare Workers for the fronts (the Astro app can move there).
- The Cloudflare admin token used for this deploy is temporary and must be revoked by the owner.

## Remove everything

```sh
# on the server
crontab -r                                            # it had no crontab before; ~/ulams/crontab.before.txt
screen -S ulams-redis -X quit; devil port del tcp 22166
for h in staging-api staging-admin coffee-staging-api coffee-staging-admin staging coffee-staging staging-pdf staging-h5p; do
  devil ssl www del 77.79.248.122 $h.ulams.app; devil www del $h.ulams.app --remove; done
devil pgsql db del p1157_u_coffee; devil pgsql db del p1157_ulams
rm -rf ~/ulams
# Cloudflare (zone ulams.app): delete the DNS records and Worker domains above, the Workers and routes, the two rulesets,
# the R2 buckets (empty them first) and the token `ulams-staging-r2-server`, revoke the Origin CA certificate, set SSL mode back to `full`.
```
