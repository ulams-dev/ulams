# ulams on MyDevil.net (shared hosting)

Run the ulams **API** on a MyDevil shared hosting account: PHP 8.4, PostgreSQL, cron and SSH, with the
front, admin, DNS, TLS and storage on Cloudflare. This directory is the runbook and the scripts. The
operator-facing summary is the docs page *Install on MyDevil (shared hosting)*
(`front/docs-site/src/content/docs/operators/install-mydevil.mdx`); the decision is
[ADR 0091](../../docs/decisions/0091-shared-hosting-cron-workers-and-manual-tenant-database.md).

> **Status: installed as a staging on the owner's account (2026-10-09).** See [`STAGING-NOTES.md`](STAGING-NOTES.md): the hosts, every object
> created (MyDevil and Cloudflare), what was verified, the fixes that came out of it and how to remove it all. The facts below marked
> **(to confirm)** are answered in its table "Unknowns". Corrections found while installing: database names are cut at 16 characters (including
> the account prefix), `CACHE_DRIVER=file` (no `cache` table exists), flat host names work for every tenancy pattern, `/h5p/*` of the API hosts
> needs a Cloudflare Worker route, and a reserved loopback port for Redis when the H5P service is used.

## Verified on the owner's account (read-only survey, 2026-10-09)

| Fact | Value |
|---|---|
| Server, OS | `s51.mydevil.net`, FreeBSD 14.3 |
| Plan | MD2 (50 GB SSD/NVMe), valid until 2026-12-18; 2FA on; the account also hosts the owner's other live sites (PHP and pointer types): never change them while testing |
| Limits | max memory **4 GB** (`ulimit -m 4096000`), max user processes **70**, open files 3000, no disk quota shown, CPU time unlimited |
| PHP | `php56` to `php85`; default CLI 8.3. `php84` has `pdo_pgsql`, `pgsql`, `redis`, `imagick`, `gd`, `intl`, `zip`, `bcmath`, `sodium`, `pcntl`, `posix`, `sockets`, `apcu`, `memcached`, `mbstring`, OPcache, `gmp`, `exif`: every extension ulams needs. CLI `memory_limit` 512M, `disable_functions` empty |
| PostgreSQL | **16.10** on the shared server `pgsql51.mydevil.net`, created with `devil pgsql`; no databases yet. ulams is developed against 12 and its default for 0-2 is 17 (#41): 16 is in between and no migration uses a version-specific feature, and a full `php artisan migrate` on a fresh PostgreSQL 16.15 passes (168 tables); the full test suite has not been run on 16 |
| Node | `node16` to `node26`; default 22.22 |
| Tools present | `redis-server`, `redis-cli`, `ffmpeg`, `ffprobe`, `screen`, `tmux`, `flock`, `rsync`, `git`, `curl`, `openssl`, `psql`, `pg_dump`. `composer84` is not on the path (not needed: `vendor/` is built elsewhere) |
| Network | outbound HTTPS works (`api.anthropic.com` answers) |
| Binexec | **off** (`devil binexec on` has not been run) |
| Ports | `devil port list` is empty (no reserved ports) |
| Hosting | existing www sites are PHP and pointer types; no Node app yet |
| DNS / mail servers | `dns1`/`dns2.mydevil.net`, `mail51.mydevil.net` |

## What runs where

| Piece | Where | Notes |
|---|---|---|
| Laravel API (php-fpm via MyDevil's nginx) | **MyDevil**, PHP 8.4 | one vhost per tenant API host, all with the same document root |
| PostgreSQL (platform + one database per tenant) | **MyDevil** | databases are made with `devil pgsql`, not by the app (ADR 0091) |
| Queue workers, scheduler | **MyDevil cron**, every minute | `ulams:tenant:work-once` (no long-lived processes) |
| Redis | not needed for the API (database queue and cache); a private `redis-server` (installed) on a unix socket for H5P and if you want it | section 9 |
| H5P service, PDF service, mjml | MyDevil Node (Passenger), one site each | section 8 |
| Learner front (Astro SSR), admin (static), content-origin proxy | **Cloudflare** (Workers / Pages) | see `deploy/vps-cloudflare` for the Worker side |
| Object storage | **Cloudflare R2** | S3 API |
| DNS, TLS, wildcards, caching | **Cloudflare** in front of everything | proxied records |
| ffmpeg video conversion | MyDevil (`ffmpeg` is installed; verified) | CPU and process limits apply |

The VPS variant is `deploy/vps-cloudflare` (another branch of work; link: <https://github.com/ulams-dev/ulams/tree/main/deploy/vps-cloudflare> once merged). Use it when a limit in section 11 bites.

## Files

| File | Purpose |
|---|---|
| `.env.example` | platform `.env` template (copied to `~/ulams/api/.env` by `install.sh`) |
| `crontab` | the cron lines: three queue groups, backup, log rotation, optional Redis |
| `bin/build-release.sh` | builds the tarball (vendor included) on your machine or in CI |
| `bin/check-host.sh` | read-only survey of the account: PHP extensions, tools, network, limits |
| `bin/install.sh`, `bin/setup.sh` | step 1 (unpack, `.env`) and step 2 (keys, migrations, caches) |
| `bin/upgrade.sh` | backup, swap code, keep state, migrate every tenant |
| `bin/backup.sh` | `pg_dump` of every database plus env files and keys, 7 days |
| `bin/add-vhost.sh`, `bin/add-tenant.sh` | one host name as a PHP site on the shared app; one whole tenant |
| `bin/cron-tick.sh` | what cron runs (per domain: scheduler tick, then drain a queue) |
| `bin/redis-start.sh` | optional private Redis on a unix socket |

All scripts are POSIX `sh` (FreeBSD `/bin/sh`); no bash-isms. Bash exists on MyDevil
(`/usr/local/bin/bash`) if you prefer it.

## 1. The account

The servers run **FreeBSD**: home directories are `/usr/home/LOGIN`, software is in `/usr/local/bin`, the
wiki's examples use `fetch`, `clang` and BSD tools, and MyDevil advertises *BSD expertise (a third-party
listing says Linux; it is wrong about the paths). It matters in three places: scripts must be POSIX `sh`
(no GNU-only flags: `sed -i` needs a suffix, `date -d` does not exist), Linux binaries do not run (so no
`ffmpeg` or `redis-server` brought from a Linux machine), and `apk`/`apt` packages do not exist; PHP and
Node are the platform's own builds.

Log in (`ssh LOGIN@sN.mydevil.net`, N is your server number; key login:
<https://pomoc.mydevil.net/Logowanie_kluczem/>) and run the survey:

```sh
sh check-host.sh            # upload it first: scp deploy/mydevil/bin/check-host.sh LOGIN@sN.mydevil.net:
```

It prints the PHP extensions of `php84`, the tools present (`ffmpeg`, `redis-server`, `screen`, `flock`,
`node22`, ...), outbound reachability (Anthropic API), `devil info`, `ulimit` and the process count.
Send the output to the repository (an issue) so the "to confirm" items here can be closed.

Make PHP 8.4 the CLI default (the account default is 8.3; the CLI binary is `php84`:
<https://pomoc.mydevil.net/PHP/>). Binexec (running your own binaries from your home directory, off on
this account) is **not needed for the plan below** (every tool it uses is a system binary); turn it on
only if something refuses to start, and only with the owner's approval:

```sh
# devil binexec on      # only if needed; log out and in again: https://pomoc.mydevil.net/Binexec/
mkdir -p ~/bin && ln -sf /usr/local/bin/php84 ~/bin/php
echo 'export PATH=$HOME/bin:$PATH' >> ~/.bash_profile && . ~/.bash_profile
php -v                  # 8.4.x
```

Required PHP extensions (from `api/composer.json` and `api/docker/php/install.sh`): `pdo_pgsql`, `redis`
(only with Redis), `intl`, `gd`, `zip`, `bcmath`, `opcache`, `sodium`, `dom`, `mbstring`, `exif`. All are
present in `php84` on the account (verified), including `pcntl`, so `--timeout` of queue jobs is enforced.

## 2. DNS and the platform host

Put the zone on Cloudflare (nameservers at your registrar), so wildcards and TLS are handled there. In
Cloudflare create **proxied** (orange cloud) `A` records to MyDevil's web IP (`devil vhost list public`)
for the API hosts you use, e.g. `api.example.com` and `*.api.example.com`. A wildcard record covers
tenants without a DNS change; the vhost per tenant is still needed on MyDevil (section 7).

Create the platform vhost and point it at the app (done by the script; the app is unpacked in step 4):

```sh
sh ~/ulams/bin/add-vhost.sh api.example.com
```

What that does, in `devil` terms (<https://pomoc.mydevil.net/Strona_WWW/>):

```sh
devil www add api.example.com php
rm -rf ~/domains/api.example.com/public_html
ln -s ~/ulams/api/public ~/domains/api.example.com/public_html      # shared document root
printf 'AddType application/x-httpd-php84 .php\n' >> ~/domains/api.example.com/.htaccess
devil www options api.example.com php_openbasedir "$HOME/ulams"      # the app lives outside the site directory
devil www options api.example.com php_exec on                         # the app starts artisan processes
devil www options api.example.com sslonly on
```

The PHP limits go to `~/domains/<host>/.user.ini` (`memory_limit`, `upload_max_filesize`; the script
writes them). The CLI `memory_limit` is 512M (verified); the web value is set by `.user.ini` (the script writes 768M); the container uses 1 GB.
The `open_basedir` option and `.user.ini` are documented at <https://pomoc.mydevil.net/PHP/>.

Whether `devil www add` accepts a wildcard name (`*.api.example.com`) is not documented **(to
confirm)**; if it does, one vhost replaces the per-tenant ones and `add-vhost.sh` runs once.

## 3. PostgreSQL

Databases are created with `devil` (<https://pomoc.mydevil.net/PostgreSQL/>); the account has no
CREATEDB/CREATEROLE, so ulams runs in `TENANCY_DATABASE_PROVISIONER=manual` mode:

```sh
devil pgsql db add ulams          # asks for a password; a same-named user is created
devil pgsql list                  # the exact database and user names (an account prefix may be added)
```

The host is `pgsqlN.mydevil.net` for server `sN.mydevil.net` (here `pgsql51.mydevil.net`). Put the names and password in `api/.env`
(`DB_*`) and the prefix in `TENANCY_DATABASE` (e.g. `m1234_{slug}`). ulams needs no PostgreSQL
extensions (no migration runs `CREATE EXTENSION`); MyDevil offers `pg_trgm`, `unaccent`, `pgcrypto`,
`uuid-ossp`, `vector` and others per database with `devil pgsql extensions <db> <name>`. The server
is PostgreSQL 16.10 (verified; see the table above for the compatibility note).
Remote access is only through an SSH tunnel (`ssh -L`), so run `psql` and `pg_dump` on the account.

## 4. Build, upload, install

MyDevil has no Docker, so build elsewhere. On a Linux or macOS machine with Docker, or in CI:

```sh
deploy/mydevil/bin/build-release.sh            # writes dist/ulams-<commit>.tar.gz (vendor/ included, no .env)
scp dist/ulams-*.tar.gz LOGIN@sN.mydevil.net:
```

A CI job is the same two lines: check out, run the script, upload `dist/*.tar.gz` as an artifact, then
`scp`/`rsync` it from a runner with the SSH key in a secret (the build does not need the host).
`vendor/` is plain PHP, so a tree built on Linux runs on FreeBSD.

On the account:

```sh
tar -xzf ulams-*.tar.gz bin/install.sh && sh bin/install.sh ulams-*.tar.gz   # step 1: unpack into ~/ulams, php -> 8.4, .env
vi ~/ulams/api/.env                                                          # fill in every CHANGE value
sh ~/ulams/bin/setup.sh                                                      # step 2: keys, migrate, ulams:upgrade, caches
```

`.env` needs: `APP_KEY` (`echo "base64:$(openssl rand -base64 32)"`; **keep it in a password manager**:
tenant secrets are encrypted with it), the database from section 3, R2 credentials, SMTP, the host
patterns (`TENANCY_*`), `INITIAL_USER_PASSWORD`, `TENANT_DEMO_PASSWORD` and `TENANCY_PHP_BINARY=/usr/local/bin/php84`
(under php-fpm `PHP_BINARY` is not the CLI, and the app starts `php artisan` for each tenant step).
Never edit `.env` with a tool that adds a BOM. Passport keys are generated by `setup.sh`
(`init-keys.sh`).

Check: `curl -sI https://api.example.com/api/health` (through Cloudflare once DNS and TLS are in place).

## 5. Cron: workers and the scheduler

```sh
sed 's#LOGIN#yourlogin#g' ~/ulams/crontab | crontab -
crontab -l
```

(<https://pomoc.mydevil.net/Cron/>.) Three lines run every minute under
`/usr/local/bin/flock -n` (the wiki shows this pattern):

| Line | Runs | Why |
|---|---|---|
| `default` | scheduler tick + `default,broadcast,video` queues, platform and every tenant, up to 20 s each | replaces `ulams:tenant:schedule-loop` and the default queue worker |
| `builder` | the builder queue, jobs up to 30 min (`--timeout=1800`) | Course Builder, Living Course, Adapt build |
| `long` | the long-job queue, jobs up to 5 h | video and course clone; needs ffmpeg |

`ulams:tenant:work-once --domain=<host>` (ADR 0091) is `queue:work --stop-when-empty --max-time` plus an
optional scheduler tick in one process. The scheduler is the app's own (`ScheduleRunCommand` per
domain), so per-tenant schedules and the per-minute lock (ADR 0068) work as in Docker; do not use
`schedule:run` directly, it would run only the platform's schedule. Each tenant is processed in its
own artisan process with `--domain=<host>` (laravel-multidomain picks `.env.<host>`).

Limits that matter: queue latency is up to a minute; with one `default` line and N tenants the pass
takes up to N x 20 s, so with more than two or three tenants split the line or lower `ULAMS_WORK_MAX_TIME`; whether a cron process may run 30 min or 5 h is not documented **(to confirm; if the host kills
long processes, builder jobs fail at the kill, and `retry_after` re-queues them)**. The wiki shows
`*/2` cron lines; `* * * * *` is the standard syntax **(to confirm it is accepted)**.

## 6. First tenant

```sh
sh ~/ulams/bin/add-tenant.sh acme "Acme Academy" coffee
```

The script creates the database `<prefix>_acme` (`devil pgsql db add`, interactive), the API vhost
`acme.api.example.com` (section 7) and runs `ulams:tenant:create acme --db-password=...`. The command
is resumable; without the script, do the three steps by hand. The tenant's `.env.acme.api.example.com`
and the entry in `config/domain.php` are written under `~/ulams/api`; `upgrade.sh` and `backup.sh`
keep them.

`ulams:tenant:create` also creates the tenant's bucket. R2 has no bucket policies, so set
`TENANCY_S3_PUBLIC_READ_POLICY=false` (in `.env`, already in the template): the step then only creates
the bucket, and you make it public once with an R2 custom domain per bucket (or one wildcard-capable
storage host) in the Cloudflare dashboard; `TENANCY_STORAGE_PUBLIC_URL` plus `/<bucket>` is the public
URL the app writes into the tenant's `AWS_URL`. The R2 API token needs *Object Read & Write* and
bucket creation (Admin Read & Write).

## 7. Tenant domains and Cloudflare

Per tenant the hosts are (patterns in `.env`, `operators/dns-and-tls`):

| Host | Served by |
|---|---|
| `<slug>.api.example.com` | MyDevil vhost (`add-vhost.sh`), the app's API |
| `<slug>.app.example.com` | Cloudflare Worker (Astro front) |
| `<slug>.admin.example.com` | Cloudflare (static admin build) |
| `<slug>.content.example.com` | Cloudflare Worker that proxies to the API host (below) |

How the many-domains-one-app model works on MyDevil: every host is its own vhost (`devil www add`),
but they all share the document root `~/ulams/api/public` through a symlink, so one copy of the code
serves every tenant. The app chooses the tenant from the `Host` header: laravel-multidomain loads
`.env.<host>` from `~/ulams/api`. `TENANCY_ENFORCE_HOSTS` (default on) answers 404 for any host that is
neither in `TENANCY_PLATFORM_HOSTS` nor a provisioned tenant, so a vhost for an unknown name is harmless.
A `pointer` site (`devil www add <host> pointer <existing site>`) might replace the symlink; whether
it keeps the `Host` header is not documented **(to confirm)**. The per-host symlink is the safe choice.

TLS: Cloudflare terminates the browser connection. Between Cloudflare and MyDevil use SSL mode **Full
(strict)** with a Cloudflare Origin CA certificate (free; covers `*.api.example.com`), installed per
host with SNI (<https://pomoc.mydevil.net/SSL/>):

```sh
ULAMS_ORIGIN_CERT=$HOME/origin.pem ULAMS_ORIGIN_KEY=$HOME/origin.key sh ~/ulams/bin/add-vhost.sh acme.api.example.com
# = devil ssl www add <public IP> origin.pem origin.key acme.api.example.com
```

Let's Encrypt (`devil ssl www add <IP> le le <host>`) checks that the `A` record points at MyDevil, so it
fails while the record is proxied (switch the record to DNS only for the issuance). MyDevil documents
no wildcard certificates; Cloudflare covers them at the edge. **Cloudflare's free Universal SSL does
not cover `*.api.example.com` (second level)**: use Advanced Certificate Manager (about USD 10 a month),
flatten the names (`<slug>-api.example.com`), or accept the cost. The same applies to
`*.app`, `*.admin` and `*.content`.

**Security: strip `X-Ulams-Content-Origin` at the edge.** Caddy removes this request header on the API
hosts in the Docker stack; MyDevil's nginx does not. Without that, anyone can send it and load package
HTML on the API origin. Add a Cloudflare Transform Rule, *Modify Request Header*, on
`http.host wildcard "*.api.example.com" or http.host eq "api.example.com"`: remove `X-Ulams-Content-Origin`.
Only the content-origin Worker may set it (it must set `Host` to the tenant's API host, drop `Cookie` and
`Authorization`, and add the CSP headers of `api/docs/content-origin.md`). Treat the content origin as not
ready until both exist.

The client IP seen by the app is Cloudflare's unless the real IP header reaches PHP **(to confirm)**;
rate limits by IP depend on it.

## 8. Node services (optional, defer)

Node 22 and 24 exist (<https://pomoc.mydevil.net/Node.js/>); both services need Node >= 22.12.
Sites of type `nodejs` run under Phusion Passenger: the app directory is
`~/domains/<host>/public_nodejs`, the entry file `app.js`, `listen()` is intercepted by Passenger (no
port needed), the app stops after 24 h without requests and restarts on the next one, variables come
from `~/.bash_profile`, and `devil www options <host> processes N` caps the processes.

```sh
devil www add pdf.example.com nodejs /usr/local/bin/node22 production
# ~/domains/pdf.example.com/public_nodejs/app.js:   import('/usr/home/LOGIN/ulams/pdf/dist/index.js')   (pdf is an ES module)
devil www restart pdf.example.com
```

Build `api/pdf` and `api/h5p` with `yarn workspace api-pdf build` / `api-h5p build` and ship `dist/` plus
`package.json` and install the production dependencies on the account (`npm22 install --omit=dev`;
the direct dependencies are pinned, transitive ones are not). Set `PDF_SERVICE_URL`/`H5P_SERVICE_URL`
to the public host and keep the `*_INTERNAL_TOKEN` secrets. The **H5P service needs Redis** (locks)
and reads the tenants' env files from the exported config directory
(`H5P_SERVICE_CONFIG_DIR`, `ulams:h5p:export-config`); both are documented in `api/h5p/README.md`.
Nothing here was run on MyDevil **(to confirm: native modules, memory, Passenger startup of a module
that listens itself)**. The account has no Node app yet and no reserved ports; Passenger sites need
neither a port nor binexec (the wiki: "port reservation is not needed for `nodejs` sites"). The
first site to try is the PDF service (no Redis, no state).

The Astro front (`output: server`, `@astrojs/node` standalone, `dist/server/entry.mjs`) can also run as a
`nodejs` site, with `app.js` doing `import('.../dist/server/entry.mjs')`. Each host is its own Passenger
application with its own processes, so it suits one to three tenants (see the resource plan) and does
not scale to a wildcard of tenants: use Cloudflare Workers beyond that. Passenger stops an app after 24 h
without requests and starts it on the next, so the first request after a quiet day is slow.

### Resource plan (MD2: 4 GB, 70 processes)

The two hard limits are **70 processes** (every `sh`, `flock`, `php`, `node`, `screen` and SSH session
counts) and **4 GB of memory** for everything the account runs, including the owner's other sites.
The plan keeps ulams to about half of both for 3 to 6 tenants:

| Consumer | Processes | Memory (estimate) |
|---|---|---|
| php-fpm for the API vhosts (shared with the other PHP sites) | 5 to 15 | 100 to 200 MB each at work, up to the 768M limit on a big request |
| Cron `default` pass (`sh`, `flock`, `php`, child tenant steps) | 3 to 4 | up to 256 MB (`--memory=256`) |
| Cron `builder` pass | 3 to 4 while a job runs | up to 512 MB |
| Cron `long` pass (video: `php` plus `ffmpeg`) | 4 to 5 while a job runs | 512 MB plus ffmpeg (a few hundred MB) |
| Redis (`redis-server` and `screen`) | 2 | `maxmemory 128mb` |
| PDF service (Passenger + node) | 2 to 3 | 100 to 200 MB |
| H5P service (Passenger + node) | 2 to 3 | 200 to 400 MB |
| Astro front, one Passenger app per tenant host | 2 to 3 each | 150 to 300 MB each |
| SSH sessions | 3 to 5 | small |

Worst case with 3 tenants' fronts: about 50 processes and 2.7 GB with all three cron lines busy; with 6
tenants' fronts on MyDevil it is 60 to 70 processes and the limit bites, so past three tenants put the
fronts on Cloudflare (section 7) and keep only the API, PDF and H5P here. All cron lines run under
`flock`, so each group is at most one pass at a time; tenants within a pass are sequential, never in
parallel. Cap Passenger with `devil www options <host> processes 1`. These are estimates: watch
`ps -U $USER | wc -l` and `top -U $USER` during the first week.

## 9. Redis (optional)

Not needed for the API on this variant. The H5P service needs it (locks), and you may want it for the
queue and cache once latency matters. `redis-server` and `redis-cli` are installed system-wide on the
account (verified), so:

- **No reserved port is needed**: the script runs Redis on a **unix socket** (`port 0`) in `~/ulams/redis`
  (mode 700) with a password, which is what the wiki recommends (<https://pomoc.mydevil.net/Redis/>).
  `devil port add tcp <port>` is only for a TCP listener; if you use one, bind `127.0.0.1` and keep the
  password, because every user of the server can reach the loopback interface.
- **Binexec is probably not needed**: it governs your own binaries (<https://pomoc.mydevil.net/Binexec/>);
  `/usr/local/bin/redis-server` is a system one. Start without it; ask the owner to turn it on only if the
  start fails **(to confirm)**.
- It runs in `screen`, started by `redis-start.sh`; there is no restart policy, so the crontab has an
  `@reboot` line and a five-minute check.

```sh
sh ~/ulams/bin/redis-start.sh                  # starts redis-server in screen on ~/ulams/redis/redis.sock
# api/.env: REDIS_CLIENT=phpredis REDIS_HOST=/usr/home/LOGIN/ulams/redis/redis.sock REDIS_PORT=0 REDIS_PASSWORD=...
# H5P service: REDIS_URL=unix:///usr/home/LOGIN/ulams/redis/redis.sock  (node-redis 4; plus the password)
```

Then, to move the API to Redis: `QUEUE_CONNECTION=redis`, `CACHE_DRIVER=redis`, `ULAMS_QUEUE_DRIVER=redis`
for the cron scripts, and (optionally) a `screen` worker (below). It is not Valkey, it stops on a
server reboot, and its memory counts against the account. Memcached is also installed
(<https://pomoc.mydevil.net/Memcached/>) but ulams does not use it.

### Workers: cron or background processes?

Both work; cron is the default. Background processes (`screen`/`tmux` are installed) are allowed by the
terms and the wiki shows them for Redis and PHP's built-in server, and they do not need binexec when they
are `php84`, a system binary. But each long-lived `php artisan queue:work` holds 100 to 250 MB and a
process slot forever, per tenant and per queue (`workers.sh` runs three per tenant: 3 tenants = 9
processes and over 1 GB), they die with the server's reboots, and nothing restarts them. Cron passes only
use memory while there is work, and a crashed pass is replaced a minute later. Use cron; add one
`screen` worker (`workers.sh queue` needs bash, which exists at `/usr/local/bin/bash`) only for a queue
where a minute of latency is too much, after Redis is in place.

## 10. Upgrade, backup, rollback

```sh
# on your machine: git pull && deploy/mydevil/bin/build-release.sh && scp dist/ulams-*.tar.gz LOGIN@sN.mydevil.net:
sh ~/ulams/bin/upgrade.sh ulams-<commit>.tar.gz
```

`upgrade.sh` takes a backup, unpacks the release next to the app, copies `.env*`, `config/domain.php` and
`storage/` into it, swaps the directories (the old code is `~/ulams/api.previous`), then runs
`package:discover`, `ulams:tenant:sync-env`, `ulams:upgrade` (migrations and one-off steps for the platform and
every tenant) and rebuilds the caches. Roll back: `mv ~/ulams/api ~/ulams/api.failed && mv ~/ulams/api.previous ~/ulams/api`
(restore the databases from `backups/` if a migration ran).

`backup.sh` (cron, daily 03:17) writes a custom-format `pg_dump` per database and copies the env files and
Passport keys; restore with `pg_restore --no-owner -h pgsqlN.mydevil.net -U <user> -d <db> <file>`.
MyDevil keeps its own backups (<https://pomoc.mydevil.net/Backup/>: how long and how to restore, **to
confirm**). R2 data is not in these dumps: enable R2 object versioning or `rclone sync` to a second bucket.

## 11. Limits and unknowns

- **Limits on the owner's MD2 (verified):** 4 GB memory and **70 processes** for the whole account,
  3000 open files. MyDevil does not publish limits per plan, so MD1, MD3 and MD4 differ **(to confirm)**.
  The ulams images assume gigabytes and up to ten workers per container; the cron design and the resource
  plan above stay inside 70 processes for 3 to 6 tenants.
- The plan is MD2, valid until 2026-12-18: renew or decide before then (issue `owner-action`).
- No Docker, no root, no systemd, no supervisor. Long-lived processes are allowed by the terms (only P2P
  software and some game and IRC servers are forbidden: <https://www.mydevil.net/dokumenty/zalacznik-nr-2-do-regulaminu-limity-bezpieczenstwa-i-ograniczenia/>)
  and the wiki runs Redis and PHP's built-in server in `screen`, but there is no restart policy and the
  resource limits are unknown. Cron is the only reliable scheduler.
- Web requests have a time limit (error 504 on the error-page list); PHP scripts "without time limits"
  run from the CLI. Large SCORM (512 MB) and course imports (1 GB) through PHP uploads will hit limits
  **(to confirm)**; `upload_max_filesize` is set in `.user.ini`.
- ffmpeg and ffprobe are installed (verified). Video conversion is CPU-bound and counts against the 70
  processes and 4 GB; keep it to one job at a time (the single `long` cron line does).
- Outbound HTTPS works (verified for `api.anthropic.com`); R2 and SMTP ports are to confirm with `check-host.sh`.
- MyDevil's nginx uses its own `.htaccess` emulation; the app's `public/.htaccess` is standard
  (front controller and `Authorization` header). Check that the `Authorization` header reaches PHP
  (login works) **(to confirm)**.
- Mail: use SMTP (the account's mail or a relay), not PHP `mail()` (it has its own rules on MyDevil).

## Sources (MyDevil)

- Offer and prices: <https://www.mydevil.net/> (MD1 to MD4: 25/50/100/200 GB SSD/NVMe; list prices, gross, per year 200/400/800/1600 PLN or per month 20/40/80/160 PLN, read 2026-10-09; unlimited sites, databases, transfer; SSH; PHP 5.6 to 8.4; Node.js; MySQL, PostgreSQL, MongoDB)
- Technologies: <https://www.mydevil.net/technologie/> (PHP default 8.3, switchable by one `.htaccess` line)
- Wiki: <https://pomoc.mydevil.net/> pages `PHP`, `PostgreSQL`, `Node.js`, `Cron`, `Redis`, `Memcached`, `SSL`, `Strona_WWW`, `Vhost`, `Binexec`, `Rezerwacja_portow`, `Srodowisko`, `Composer`, `Mise`, `Cache`, `Git`, `Konfiguracja_DNS`, `Devil`, `htaccess`
- Terms: <https://www.mydevil.net/dokumenty/regulamin-korzystania-z-uslug-mydevil-net/> and the limits annex above
