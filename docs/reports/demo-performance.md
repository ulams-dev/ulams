# Demo performance report

Date: 2026-10-08 · Branch: `phase-0/foundation` · Stack: Laravel 13.35 / PHP 8.4.26 (php-fpm) behind Caddy,
Postgres 12, Valkey 8, Docker Desktop on an Apple M1 Pro (8 vCPU, 8 GB VM), front on Vite dev (host :3000).

Paths are relative to the repository root; `pkg/X` means `api/packages/X`.

## TL;DR

A demo page feels slow because the front runs **three sequential waves of 10–24 API calls**. Each call costs
**70–220 ms warm and 340–520 ms after a few seconds idle**, the response cache is almost never warm, and the
API container already burns about one CPU core doing nothing. In the browser, data finishes 2.5–5 s after
navigation (prod build and dev alike, measured while the other agent's test suite was also loading the
container).

Ranked causes (details in §4):

| # | Cause | Dev-only? | Measured cost | Fix | Expected gain |
|---|---|---|---|---|---|
| 1 | Front: 3 sequential API waves, duplicate calls, StrictMode doubling in dev | partly | data done 2.5–5 s; replay = 0.87 s warm, 1.4 s with dev doubling, on top of browser boot | collapse into 1 bootstrap call plus 1 parallel wave; dedupe | −50–65% of page data time |
| 2 | macOS bind mount + opcache `validate_timestamps` | **dev** | warm ×1.5–2.7 per request; after 3 s idle 340–520 ms vs 105–160 ms | code inside the VM (named volume / synced dirs), or `validate_timestamps=0` in demo mode | −40–120 ms warm, −250–350 ms cold per request |
| 3 | Background CPU: `queue.sh` / `scheduler.sh` boot artisan in a loop | dev **and** prod design | idle container at 84% CPU on average (0–158%); each boot 1.3–4.1 s wall, ~1.2 s CPU | long-lived `queue:work` per tenant (or Horizon), no `--stop-when-empty` loop | frees ~1 core; removes tail spikes |
| 4 | Response cache ineffective | **prod** | flushed every 5 s while a lesson is open; `file` store; `Cache-Control: no-cache, private` | targeted invalidation, Redis store, public `Cache-Control` for catalogue | uncached → cached: −35–150 ms warm, −300–600 ms cold |
| 5 | Vite dev server (440–550 unbundled modules) | **dev** | FCP 1.0–1.6 s vs 0.25–0.3 s; 2.6–3.5 MB vs 0.66–0.98 MB; +2–3 s on first load after start | demo from a prod build | −0.7–1.3 s FCP per page |
| 6 | Laravel per-request boot (191 providers, 1,600–1,900 files), debug on, no config/route/event cache (`config:cache` is currently broken) | **prod** | 404 floor 88 ms live, 51 ms with every fpm fix | caches + `APP_DEBUG=false` + no-dev deps; worker mode later | −10–40 ms per request now; to ~5–10 ms in worker mode |
| 7 | N+1 queries / runtime schema introspection | **prod** | program 95 queries, products 112 (24 `pg_attribute`), platform course list 59 | eager loading, cache `getNameColumn()` | −30–150 ms on uncached responses |
| 8 | CORS preflights served by PHP | **prod** | 93 ms warm, 402 ms cold each; 6–11 per authenticated page | answer `OPTIONS` in Caddy, or same-origin `/api` | removes 6–11 PHP requests per page |
| 9 | `pm.max_children = 5` | prod capacity | no difference for a single user (390 vs 404 ms page replay at 16 vs 4 workers) | size to cores/RAM | matters only under concurrency |

**Verdict:** a rewrite is **not warranted**. Laravel's own cost after the obvious fixes is a ~50 ms boot floor
per request in classic fpm, and ~5–10 ms in worker mode. Everything above that is our code and our
deployment: the waterfall, cache invalidation, N+1 queries and dev tooling. The cheapest big win for public
catalogue data is a **thin read layer**: HTTP caching at Caddy/edge, or the front server fetching and
caching public data. That gets catalogue endpoints to single-digit milliseconds whatever the backend
language. See §5.

## 1. Method

- **Measurement noise.** The other agent's Laravel 13 phpunit run (46 suites) was running for the first
  ~25 min, which put the api container at 230–340% CPU. Afterwards the idle background was 0–158% (§3.6).
  To keep comparisons fair, every A/B table below **interleaves the configurations in random order within
  each round**, so they share the same noise.
- **No changes to `api/` or the live container.** All variants ran in a throwaway setup that was removed
  afterwards:
  - a copy of the app inside the container's own filesystem (`/tmp/app`, `/tmp/app2`);
  - extra php-fpm masters on ports 9101–9104;
  - a separate Caddy container on the `ulams` network, published on 127.0.0.1:8090–8094.
- **API timing.** `curl` `time_starttransfer` (TTFB) and `time_total`, 10 runs per cell, p50/p95.
  - "warm" means back-to-back requests after one discarded request.
  - "cold" means a 3 s sleep before each request. That is longer than `opcache.revalidate_freq=2`, so opcache
    re-stats every included file, which is what a presenter pausing between clicks experiences.
  - TTFB and total differ by under 1 ms for these payloads, so the tables show total time.
- **Query counts.** An in-process profiler (`php -r`-style script, not in the repo) booted the app for one
  host, ran one request through the HTTP kernel, and recorded `DB::listen`, cache events, included files and
  per-phase timings. Excimer sampled a 404 request to break down the boot phase.
- **Front.** Playwright with headless Chromium 1366×900, median of 3. Pages: landing, course detail, and
  lesson (logged in as `student1@coffee.ulams.app`). Prod build via `vite build` into a scratch dir, served
  gzip + immutable by a small static server on `coffee.app.localhost:4173`.
- **Caveats.**
  - Bearer tokens were revoked several times during the session, so some cold authenticated cells are
    401/403. They still measure the full boot and auth path, but not the controller. Warm authenticated
    numbers are valid.
  - `/api/demo/*` routes are not registered at the moment (404).

## 2. API timings

### 2.1 Live stack via the real Caddy, warm (p50/p95 ms, 10 runs)

`?_nc=<random>` defeats the response cache, which gives the uncached cost of the same endpoint.

| Endpoint | api (platform) | coffee | oncall | nightsky |
|---|---|---|---|---|
| `GET /api/config` | 67/94 | 99/197 | 71/204 | 76/131 |
| `GET /api/settings` | 70/95 | 94/247 | 73/112 | 96/246 |
| `GET /api/courses?per_page=12` (resp. cache hit) | 95/128 | 68/109 | 70/72 | 101/403 |
| same, uncached | 179/324 | 142/243 | 120/172 | 127/193 |
| `GET /api/courses/{id}` (hit) | 122/378 | 71/87 | 76/91 | 123/361 |
| same, uncached | 163/245 | 351/1190 | 182/248 | 207/533 |
| `GET /api/courses/{id}/program` (auth, hit) | 115/137 | 121/193 | 78/87 | 99/150 |
| same, uncached | 198/399 | 216/284 | 162/294 | 217/412 |
| `GET /api/categories/tree` | 119/222 | 78/103 | 104/286 | 198/365 |
| `GET /api/tutors` | 117/162 | 80/85 | 130/1913 | 101/538 |
| `GET /api/webinars?per_page=6` | 96/191 | 141/193 | 253/576 | 141/736 |
| `GET /api/events` | 101/128 | 100/126 | 136/396 | 129/521 |
| `GET /api/profile/me` (auth) | 127/165 | 139/216 | 212/553 | 179/319 |
| `OPTIONS /api/profile/me` (preflight) | 111/242 | 96/136 | 96/144 | 173/1570 |

**Platform vs tenants: no systematic difference.**
- gecche/multidomain picks `.env.<host>` in `new Application()`, which took **2–3 ms**.
- `RejectUnknownHost` is a config lookup.
- Spread between hosts is noise plus data size: the platform has 5 courses, each tenant 1.

### 2.2 Where the time goes: same requests against four PHP setups (coffee, warm, p50/p95 ms)

The four setups:
- **live**: current fpm, code on the macOS bind mount.
- **local**: identical code and ini, code on the VM's own disk.
- **cached**: local + `APP_DEBUG=false` + config/route/event caches.
- **prod**: cached + `opcache.validate_timestamps=0`, JIT 64 MB, 256 MB opcache, `max_accelerated_files=40000`.

| Endpoint | live | local | cached | prod |
|---|---|---|---|---|
| `/api/config` | 197/863 | 91/385 | 85/231 | 72/239 |
| `/api/settings` | 110/329 | 61/109 | 54/80 | 58/102 |
| `/api/courses?per_page=12` (hit) | 92/228 | 59/104 | 58/104 | 54/133 |
| same, uncached | 157/386 | 118/161 | 126/215 | 126/258 |
| `/api/courses/1` (hit) | 99/245 | 58/112 | 56/105 | 49/60 |
| same, uncached | 201/510 | 140/215 | 140/183 | 147/197 |
| `/api/courses/1/program` (hit) | 112/390 | 70/231 | 79/175 | 72/158 |
| same, uncached | 182/454 | 171/239 | 181/277 | 167/223 |
| `/api/categories/tree` | 78/261 | 53/63 | 55/92 | 52/88 |
| `/api/tutors` | 104/181 | 74/128 | 57/84 | 56/71 |
| `/api/webinars?per_page=6` | 104/569 | 80/110 | 76/135 | 72/120 |
| `/api/stationary-events?per_page=6` | 153/296 | 74/99 | 69/105 | 71/130 |
| `/api/events` | 145/417 | 82/158 | 94/191 | 108/195 |
| `/api/products?per_page=12` | 145/356 | 101/125 | 99/113 | 95/116 |
| `/api/consultations?per_page=6` | 104/286 | 67/102 | 64/109 | 58/88 |
| `/api/pages` | 99/432 | 64/102 | 56/123 | 52/63 |
| `/api/profile/me` (auth) | 217/377 | 131/188 | 129/194 | 129/193 |
| `/api/does-not-exist` (404, boot only) | 88/267 | 63/105 | 55/118 | 51/67 |
| `OPTIONS` preflight | 93/174 | 58/96 | 48/64 | 48/71 |
| `hello.php` (bare PHP through the same fpm) | 3/5 | 3/7 | 3/7 | 3/5 |

Platform (`api.localhost`), same four setups: 404 95 → 67 → 57 → 57; course list uncached 180 → 163 → 131 → 133;
products 275 → 233 → 191 → 173; profile/me 167 → 147 → 138 → 114.

What this says:
- **Bind mount: −30 to −50% of warm time** (live → local). It is the biggest single PHP-side factor.
- **Debug off + framework caches: another −5 to −15 ms** (local → cached).
- **opcache without timestamp checks + JIT: 0 to −10 ms** (cached → prod). JIT is currently *configured*
  (`opcache.jit=tracing`) but **disabled** (`opcache.jit_buffer_size=0`), and turning it on did not
  measurably help. These requests are I/O- and autoload-bound, not CPU-loop-bound.
- **Bare PHP through fpm is 3 ms**, so Caddy, FastCGI and Docker networking are not the problem.

### 2.3 Cold (3 s idle before each request; p50/p95 ms, 10 runs)

| Endpoint | host | live | local | prod |
|---|---|---|---|---|
| `/api/config` | coffee | 438/636 | 184/279 | 121/154 |
| `/api/courses?per_page=12` (hit) | coffee | 377/534 | 168/1003 | 129/200 |
| same, uncached | coffee | 688/1538 | 326/575 | 242/452 |
| `/api/courses/1` (hit) | coffee | 521/870 | 233/410 | 138/347 |
| same, uncached | coffee | 776/1005 | 402/676 | 345/584 |
| `/api/tutors` | coffee | 518/1136 | 208/351 | 159/329 |
| `OPTIONS` preflight | coffee | 402/787 | 166/240 | 111/163 |
| `/api/courses/{id}/program` (boot + auth only, token revoked) | coffee | 341/538 | 135/269 | 102/291 |
| `/api/config` | api | 442/797 | 225/298 | 104/147 |
| `/api/courses?per_page=12` (hit) | api | 541/1010 | 172/335 | 127/190 |
| `/api/courses/4` (uncached) | api | 1125/3183 | 353/1762 | 238/1522 |
| `hello.php` | coffee | 13/21 | 8/21 | 11/20 |

After a short pause, **every request on the live stack costs 340–520 ms instead of 70–120 ms**. With
`validate_timestamps=On` and `revalidate_freq=2`, opcache `stat()`s each of the 1,600–1,900 included files
once 2 s have passed. On VirtioFS each stat is a round trip to macOS. This is the "laggy after every click"
feeling during a demo, and it is dev-only.

### 2.4 Page-level replay of the landing waterfall (coffee, curl, parallel within a wave)

The waves, taken from the front capture:
1. `pages`, `settings`, `config`;
2. `courses`, `tutors`, `webinars`, `stationary-events`, `products`, `consultations`;
3. `courses/1`.

"Doubled" means every call twice, as React StrictMode does in dev.

| Setup | warm p50 (waves) | warm, doubled | cold (4 s idle), doubled |
|---|---|---|---|
| live (bind mount, 5 workers) | 867 ms (224 / 464 / 175) | 1,416 ms (305 / 762 / 216) | 1,177 ms (max 1,929) |
| prod-like, 4 workers | 404 ms (83 / 197 / 68) | 477 ms | 869 ms (max 920) |
| prod-like, 16 workers | 390 ms (110 / 185 / 84) | 442 ms | n/a |

The floor for this page is **three serial round trips**, whatever the backend. The browser adds JS boot
before wave 1 and CORS preflights on authenticated pages (§3.7).

## 3. PHP breakdown

### 3.1 Runtime

| Item | Value | Note |
|---|---|---|
| Server | php-fpm 8.4.26 behind Caddy `reverse_proxy … transport fastcgi` | no `artisan serve`, no Octane / FrankenPHP |
| fpm pool | `pm = dynamic`, `max_children = 5`, `start_servers = 2`, `max_requests = 500` | `api/docker/php` defaults |
| opcache | `enable=On`, `enable_cli=Off`, `validate_timestamps=On`, `revalidate_freq=2`, 128 MB, 10,000 files | |
| JIT | `opcache.jit=tracing` but `jit_buffer_size=0`, so **off** | turning it on measured 0–10 ms gain |
| xdebug | not loaded | `excimer` is loaded (sampling profiler, negligible when idle) |
| `memory_limit` | 2G | |
| `APP_DEBUG` | `true` (compose env and every `.env*`) | `LOG_LEVEL=debug`; today's log is 6.0 MB |
| Caches (`artisan about`) | config **NOT CACHED**, events **NOT CACHED**, routes **NOT CACHED**, views cached | |
| Code | bind mount `./:/var/www/html` (VirtioFS); 57,570 PHP files in `vendor/`, 1.8 GB | copying it out of the mount took 3 min 41 s |
| Dev dependencies | installed (bind-mounted `vendor/` includes ignition, phpunit, testbench, …) | `IgnitionServiceProvider` boots on every request |

### 3.2 Per-request boot

Each request loads **1,594 files for a 404 and up to 1,920 for `courses`**, and **191 service providers**.

In-process phase timings (CLI with opcache file cache, warm) for a 404 on the prod-like copy:
- `bootstrap` (env + config + register + boot providers): **~120 ms of 163 ms**;
- routing and middleware: 19 ms.

Under fpm with shared-memory opcache the same request takes ~51 ms; the proportions hold. Excimer split of
the boot:

| Share of samples | What |
|---|---|
| 46% | `BootProviders` |
| 42% | Composer class loading (`ClassLoader::loadClass`); 13% in `ComposerStaticInit::getInitializer` alone (the classmap includes `google/apiclient-services`) |
| 20% | `RegisterProviders` |
| 10.6% | `Ulams\Settings\UlamsSettingsServiceProvider::boot`: reads administrable config from Redis on **every** request via **Predis** (pure PHP; the `redis` extension is installed but `REDIS_CLIENT` defaults to `predis`). On a cache miss it opens a DB connection and runs `Schema::hasTable('config')` |
| 5.4% | Carbon provider |
| 3.7% each | Sentry (`register` + `boot`, no DSN configured), `TemplatesEmail\AuthTemplatesServiceProvider` |
| ~1% each | Ignition, Excel, Video, Courses, TopicTypes, ~10 template providers |

**`config:cache` currently fails.** `app/Providers/AppServiceProvider.php:44` writes a
`new \OpenApi\Analysers\ReflectionAnalyser(...)` object into `l5-swagger.defaults.scanOptions.analyser` at
boot, and `config:cache` cannot serialize it:

```
Your configuration files could not be serialized because the value at
"l5-swagger.defaults.scanOptions.analyser" is non-serializable.
```

For the measurements, the copy skipped that assignment during `config:cache`. The real fix: set it only
where swagger generation runs, for example in a `l5-swagger:generate` wrapper or via a config value that is a
class name. This file belongs to the Laravel 13 upgrade, so it is a note for that work.

### 3.3 Tenant bootstrap

Not a cost.
- `new Gecche\Multidomain\Foundation\Application` with env detection: 2–3 ms.
- `.env.<host>` parse: inside the bootstrap above, under 1 ms.
- Tenancy middleware (`RejectUnknownHost`): a config lookup.
- `SetTimezoneForUserMiddleware`: one extra `users` query per authenticated request, and a `save()` when the
  `CURRENT-TIMEZONE` header changes.

### 3.4 Queries per endpoint (in-process, warm; "hit" means served by the response cache)

| Endpoint | host | queries | query ms | worst duplicates |
|---|---|---|---|---|
| `/api/config` | both | 0 | 0 | – |
| `/api/settings` | both | 1 | 1–2 | – |
| `/api/courses?per_page=12` | coffee (hit) | 0 | 0 | – |
| `/api/courses?per_page=12` | api (miss) | **59** | 97 | `courses where id = ?` ×15, `categories … category_user` ×12, `pg_attribute` column introspection ×8 |
| `/api/courses/4` | api (miss) | **56** | 44 | `courses where id = ?` ×8, `lessons where parent_lesson_id = ?` ×6, `topics where lesson_id = ?` ×6 |
| `/api/courses/{id}/program` (auth, miss) | coffee / api | **95** | 55–73 | `topic_resources where topic_id = ?` ×18, `topics where topicable_id in (?)` ×7, `topics where lesson_id = ?` ×6 |
| `/api/categories/tree` | both | 4 | 7–36 | – |
| `/api/tutors` | api | 9 | 11 | `categories … category_user` ×7 |
| `/api/webinars?per_page=6` | api | 11 | 11 | trainers ×3, tags ×3, categories ×3 |
| `/api/events` | api | 29 | 72 | `stationary_events where id = ?` ×6, `webinars where id = ?` ×3 |
| `/api/products?per_page=12` | api | **112** | **174** | `pg_attribute` introspection ×24, `courses where id = ?` ×22, categories ×12 |
| `/api/consultations?per_page=6` | api | 24 | 36 | categories ×4, consultations ×3 |
| `/api/profile/me` (auth) | both | 11 | 13 | `users` ×2, roles ×2 |

Two sources stand out.
- **Runtime schema introspection.** `pkg/cart/src/Contracts/ProductableTrait.php:126` (`getNameColumn()`)
  calls `Schema::hasColumn()` twice per productable, and each call is a `pg_attribute` query.
  `pkg/core/src/Repositories/BaseRepository.php:293` and `app/Http/Controllers/Controller.php:16` call
  `getColumnListing()` per request.
- **Lazy relations in resources.** `pkg/courses/src/Http/Resources/TopicResource.php:41` loads
  `$this->resource->resources` per topic, and lessons load topics per lesson.

Postgres itself is fast (1–3 ms per query). Queries are 25–55% of an uncached handler, not the whole story.

### 3.5 Cache, session, permissions

| Item | Value | Effect |
|---|---|---|
| Default cache | Redis (Valkey) via **Predis** | ~1–3 ms per call more than phpredis (inference) |
| Response cache | `spatie/laravel-responsecache`, store **`file`** (`pkg/courses/config/responsecache.php`), on the bind mount, per tenant storage dir | used by `courses`, `courses/{id}`, `courses/{id}/program`, topic resources, progress |
| Invalidation | `pkg/courses/src/Providers/EventServiceProvider.php:22`: `eloquent.created/updated/deleted: Ulams*` → `ResponseCache::clear()` | **any** write to **any** `Ulams*` model flushes **every** cached response of the tenant |
| Who writes | the lesson page pings `PUT /api/courses/progress/{topic}/ping` **every 5 s** (`front/src/components/Courses/Course/CourseProgramContent.tsx:64`); the ping runs `UserTopicTime::create` and `CourseProgress::updateOrCreate` | while anyone has a lesson open, the catalogue cache never survives more than 5 s |
| HTTP caching | every API response has `Cache-Control: no-cache, private` | no browser or edge caching possible |
| Session | cookie driver; API uses Passport bearer tokens | negligible |
| spatie/permission | permission cache in Redis (hits visible in the cache events) | negligible |

### 3.6 Horizon, queues, scheduler

The container runs these all the time:
- Horizon (2 supervisors, 2 workers);
- **3 `queue.sh` loops**: each pass runs 2 `artisan queue:work --stop-when-empty` per tenant, i.e. 6 cold
  artisan boots per pass, then sleeps 3 s;
- `scheduler.sh`: 4 `schedule:run` per minute, one per tenant plus the platform.

| Measurement | Value |
|---|---|
| One artisan boot (`--version`) from the bind mount, CLI without opcache | 1.30–4.12 s wall, ~1.2–1.5 s CPU |
| Same, local disk + caches | 0.30–0.57 s wall, ~0.3 s CPU |
| api container CPU with no traffic (12 samples, ~1 min) | mean **84%**, range 0.3–158% |
| api container CPU during the other agent's phpunit run | 230–340% |

So about one core is permanently busy booting Laravel to find empty queues. Requests compete with it, and it
shows up as the p95 tails in the tables above. In production the same loop costs less (no bind mount), but
the design (cold boot every few seconds per tenant) scales linearly with the number of tenants.

### 3.7 CORS preflights

Authenticated pages send 6–11 `OPTIONS` requests. Caddy forwards them to fpm, and Laravel's `HandleCors`
answers after a full boot: **93 ms warm and 402 ms cold** on the live stack, 48 ms on the prod-like setup.
They also occupy one of the 5 fpm workers each.

## 4. Front: dev vs prod build

Medians of 3, Playwright, cold means a fresh browser context. "Data done" is when the last API call ends; it
was measured while the API container was under the test-suite load.

| Page / mode | FCP | LCP | TBT | requests (JS) | JS bytes | total bytes | API calls (+OPTIONS) | data done |
|---|---|---|---|---|---|---|---|---|
| landing dev cold | 1305 | 2575 | 21 | 477 (439) | 2.65 MB | 3.29 MB | 13 (+1) | 4131 |
| landing dev warm | 993 | 1214 | 12 | 475 (439) | 98 kB | 144 kB | 13 | 4117 |
| landing prod cold | 287 | 1986 | 9 | 47 (8) | 662 kB | 1.33 MB | 10 (+1) | 3049 |
| landing prod warm | 288 | 412 | 0 | 39 (8) | 0 | 343 kB | 10 | 2570 |
| course dev cold | 1332 | 3656 | 22 | 604 (554) | 3.53 MB | 3.90 MB | 22 (+4) | 5190 |
| course prod cold | 300 | 3696 | 0 | 101 (41) | 984 kB | 1.38 MB | 11 (+2) | 5099 |
| course prod warm | 320 | 4227 | 2 | 95 (41) | 0 | 37 kB | 12 | 4602 |
| lesson dev cold | 1560 | 1619 | 0 | 581 (524) | 3.18 MB | 3.68 MB | 19 (+11) | 4106 |
| lesson prod cold | 279 | 333 | 0 | 82 (27) | 887 kB | 1.26 MB | 10 (+6) | 2516 |

Findings:
- **TBT is ~0 everywhere.** Main-thread JS is not the problem.
- **Dev mode costs ~0.7–1.3 s of FCP per page.**
  - It ships 440–550 unbundled modules (2.6–3.5 MB) vs 8–41 chunks (0.66–0.98 MB).
  - The first load after starting Vite adds 2.3–2.6 s.
  - `vite-plugin-eslint` lints every module on transform (~0.5 s on a cold transform, one sample).
- **Course-page LCP is ~3.7 s in both modes.** The LCP element is data-driven, so the API waterfall sets it.
- **Waterfall: 3 sequential waves on every page.**
  - Wave 1 is the bootstrap gate: `pages`, `config`, `settings` (plus `profile/me`, `notifications` when
    logged in).
  - Wave 2 is the page data.
  - Wave 3 is the course detail, questionnaire stars, or progress ping.
- **Duplicate calls even in prod:** `/api/pages` ×2 on landing and ×3 on course; `/api/config` ×2 on course;
  `/api/profile/me` ×2 on lesson. **In dev, StrictMode doubles everything**: landing 13 vs 10 calls, course
  22–24 vs 11.
- **Bundle weight that every page pays:**
  - Sentry including Replay: 80 kB gz, and no DSN is set locally.
  - KaTeX: 86 kB gz.
  - The leva `ThemeCustomizer`: 65 kB gz.
  - In the entry chunk: `rc-tree-select` and `photoswipe`.
  - The course page also pulls `react-pdf` + `scorm-again` (195 kB gz).

## 5. Fixes, ranked, with expected gains

Gains are from the measurements above unless marked (estimate).

### Dev-only (makes demos fast on a Mac; nothing to do with production)

1. **Demo from a prod front build**, not Vite dev. Expected: FCP 1.0–1.6 s → 0.25–0.3 s, StrictMode doubling
   disappears (landing 13 → 10 calls, course 22–24 → 11), and the 2–3 s first-load penalty after `yarn dev`
   goes away. Also drop `vite-plugin-eslint` from the dev server (lint in CI and pre-commit instead).
2. **Get PHP code off the bind mount**, or stop re-stat'ing it. Two options:
   - Simplest: a "demo" profile with `opcache.validate_timestamps=0` and `APP_DEBUG=false`, restarting fpm
     after code changes. Measured on local disk that removes −40–120 ms warm and −250–350 ms per request after
     idle (cold 340–520 ms → 105–160 ms). On the bind mount, `validate_timestamps=0` alone removes the
     cold-after-idle penalty; warm stays slower because autoload still `stat()`s (estimate).
   - Better: keep `vendor/` in a named volume (or Docker Desktop synchronized file shares / Mutagen) and
     bind-mount only `app/`, `packages/`, `config/`, `routes/`.
3. **Replace the `queue.sh` / `scheduler.sh` loops in dev with long-lived workers.** One `queue:work` per
   tenant without `--stop-when-empty`, or Horizon per tenant. Frees ~1 core at idle (84% mean) and removes
   the p95 spikes. Setting `opcache.enable_cli=1` with `opcache.file_cache` cuts each artisan boot from
   1.3–4.1 s to 0.3–0.6 s.

### Production (real problems)

4. **Front waterfall.**
   - Serve `config` + `settings` + `pages` as one bootstrap payload, ideally inlined into `index.html` by
     the front server or fetched once and cached, instead of gating every page on three calls.
   - Fire page data in parallel with it, and dedupe `pages` / `config` / `profile/me`.
   - Expected: 3 waves → 1–2, removing one full round trip (≥ 1 request latency, 70–500 ms) and 2–4
     duplicate requests per page. From the replay: −35–45% of the page data time.
5. **Make the response cache actually hold.**
   - Invalidate per model or tag (course, lesson, topic, category, product) instead of `Ulams*`, and never on
     progress, time-tracking or token writes.
   - Use the Redis store, not `file`.
   - Expected: catalogue endpoints at the "hit" numbers instead of "uncached": −35–150 ms warm, −300–600 ms
     cold per call.
6. **Answer CORS preflights without PHP.** Either Caddy responds to `OPTIONS` for `/api/*` with the tenant's
   allowed origins, or the front calls the API same-origin (`coffee.app.localhost/api` → Caddy → fpm) so no
   preflight happens. Removes 6–11 PHP requests (48–400 ms each) per authenticated page.
7. **Production PHP configuration.**
   - `APP_DEBUG=false`, `LOG_LEVEL=warning`.
   - `composer install --no-dev --classmap-authoritative` (plus `--apcu`).
   - `config:cache`, `route:cache` and `event:cache` per tenant — this first needs the swagger analyser fix
     in §3.2.
   - `REDIS_CLIENT=phpredis`.
   - Drop the Sentry provider when no DSN is set, or keep it but lazy-load Replay in the front.
   - opcache `validate_timestamps=0`, `max_accelerated_files ≥ 20000`, 256 MB.
   - Expected: −10–40 ms per request (404 floor 88 → 51 ms measured).
8. **Fix the N+1s.**
   - Eager-load `lessons.topics.resources` and `topicable` in `program` and `show`.
   - Cache `ProductableTrait::getNameColumn()` per class (static array) instead of 2 `hasColumn` queries per
     item.
   - Load categories once in the tutors, course and product lists.
   - Expected (estimate from query ms): program 95 → ~10 queries; products 112 → ~15 queries, −100–150 ms;
     course list 59 → ~10.
9. **Size the fpm pool** (`max_children` ≈ 2–4 × cores, within RAM). Single-user replay showed no difference
   between 4 and 16 workers, so this is for concurrent demo audiences and production load, not one presenter.
10. **Front bundle.** Lazy-load Sentry Replay, KaTeX, `ThemeCustomizer` (leva) and the course player;
    expected −230 kB gz on every page. Small next to the API, since TBT is already ~0.

## 6. Verdict: rewrite in something else?

**No.** The numbers do not point at PHP or Laravel as the bottleneck.

- **Measured floor.** With every fpm-level fix applied (local disk, caches, debug off, no timestamp checks),
  a request that does nothing (404 or preflight) costs **~50 ms**, and a cached catalogue response
  **49–58 ms**. That 50 ms is Laravel booting 191 providers and ~1,600 files on every request in the
  classic fpm model. It is a known, solvable cost: worker mode boots once.
- **Worker mode (Octane on FrankenPHP or Swoole) would bring the floor to ~5–10 ms** (estimate:
  `bootstrap` is ~75% of the 404 time, and routing + middleware measured 19 ms in CLI / ~10 ms in fpm). There
  is one real obstacle: **gecche/laravel-multidomain picks the tenant's `.env`, DB and storage paths once,
  when the application boots**, so a long-lived worker would serve every host with the first tenant's
  config. Options:
  - one Octane worker pool per tenant: fine for 3 demo tenants, does not scale to many;
  - move tenancy to runtime switching (a per-request tenant resolver that swaps DB connection, cache prefix,
    storage disk and Passport keys), which is a Phase-level change worth its own ADR.
- **Realistic p50 for public catalogue endpoints** (`config`, `settings`, `courses`, `courses/{id}`,
  `categories`, `tutors`, `webinars`, `events`), server time on production-like hardware:

  | Setup | Expected p50 |
  |---|---|
  | today, live dev stack | 70–200 ms warm, 340–520 ms after idle |
  | fpm + fixes 5, 7, 8 (cache holds, prod config, no N+1) | **45–60 ms** (measured floor 49–58 ms for cache hits) |
  | Octane/FrankenPHP worker mode + same fixes | **5–15 ms** for cache hits, 20–60 ms uncached (estimate) |
  | HTTP cache in front (Caddy `cache-handler`/Souin, Varnish, or CDN) with `Cache-Control: public, s-maxage=60, stale-while-revalidate` on anonymous catalogue GETs | **< 5 ms**, PHP not touched |

- **Recommendation.**
  1. Do the dev-only fixes for demos now (prod front build, `validate_timestamps=0` demo profile, long-lived
     queue workers). Cheap, no product risk; they cut perceived latency by roughly 3×.
  2. Fix response-cache invalidation, the preflights, the bootstrap waterfall and the obvious N+1s. These are
     production bugs regardless of language.
  3. For public catalogue data, add a **thin read layer**, not a rewrite: anonymous `GET`s with
     `Cache-Control: public` and per-tenant keys (`Host` in the key), cached at Caddy or the edge. Or let the
     new front server fetch and cache the bootstrap payload and catalogue per tenant and inline them into the
     HTML. This removes the API from the critical path of the landing and course pages entirely.
  4. Consider worker mode only after the tenancy model can switch per request. Measure again then; a rewrite
     would have to beat ~5–15 ms per request and would also inherit the same waterfall, invalidation and N+1
     problems.

## Appendix: reproduction

- Scratch scripts (not in the repo):
  - `bench.py` / `bench2.py`: interleaved curl timings with `Host` override;
  - `fanout.py`: waterfall replay;
  - `prof.php`: in-process phases and queries;
  - Excimer collapsed stacks.
- A/B setup, all removed after the run:
  - app copies in `/tmp/app` (raw) and `/tmp/app2` (debug off + caches) inside the api container;
  - fpm masters with `pm = static`, 4 workers (16 for the pool test), started with `php-fpm -y … -d …`;
  - a throwaway `caddy` container on the `ulams` network forwarding to `api:9000` / `api:910x` with
    `SCRIPT_FILENAME` set per port.
- Front: `npx vite build --outDir <scratch>`, served on `coffee.app.localhost:4173` (gzip, immutable assets);
  Playwright 1.48 headless Chromium; LCP/long tasks via `PerformanceObserver`, network via CDP.
