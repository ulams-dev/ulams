# Phase 0 plan: foundation and framework upgrade

Status: **draft, waiting for approval**. Nothing in section B is implemented yet.
Audit findings that feed this plan: [`docs/reports/phase-0-audit.md`](../reports/phase-0-audit.md).
Spec: [`docs/ROADMAP-PROMPT.md`](../ROADMAP-PROMPT.md) Phase 0. Tracker: [`docs/ROADMAP-TODO.md`](../ROADMAP-TODO.md).

---

## A. Foundation work done (0.1b, monorepo)

Commits on `main` and on `phase-0/foundation`, oldest first (`git log --oneline --reverse`).
The three source repositories keep their full history under `api/`, `admin/` and `front/`;
the commits before `820f0fdc` are upstream history.

| Step | Commits | Result |
|---|---|---|
| Monorepo bootstrap | `820f0fdc` chore: initialise Wellms monorepo | `api/`, `admin/`, `front/` with full history |
| Docker fixes | `4517e919` | excimer built from source, `bitnamilegacy/minio` |
| Retroactive ADRs | `f0be8535` | `api/docs/adr`, `admin/docs/adr`, `front/docs/adr` (0000–0015) |
| Vendor 50 PHP packages | `ab11a760` | `api/packages/*`, no `escolalms/*` in `api/composer.json`, providers listed in `api/config/app.php` ([README](../../api/packages/README.md)) |
| Early TODOs | `5fcbf653`, `826895e1` | merged later into the roadmap |
| H5P service | `db2f3f9e` | Lumi-based Node service in `api/h5p` |
| JS workspaces | `d463954e` | Yarn workspaces + Turborepo, JS libraries vendored into `front/src/lib`, `admin/src/lib` (ADR 0005) |
| Compose cleanup | `1964db30` | prebuilt admin/front images dropped from the stack |
| Rename | `874d2475` | EscolaLMS / Wellms → ulams in code, config, infra, with data migration (ADR 0002) |
| Theming | `82ccee16`, `41fe54f5` | CSS custom property contract `--ulams-*` (ADR 0004) |
| Roadmap | `4f5673af`, `f7f81765` | `CLAUDE.md`, spec + TODO, ADRs 0001–0006 |
| H5P swap | `9a97aaa2`, `6f128c56` | `headless-h5p` + `h5p/h5p-core` removed; `api/packages/h5p` read-only index; iframe embedding in admin/front (ADR 0003) |
| Licensing | `f792ee93` | original copyright notices restored, missing licences added, `LICENSING.md` |
| Recommender removal | `9fbded1a`, `ec4f0965` | package, admin and front screens removed (ADR 0006) |
| ReportBro → pdfme | `d3b26b52`, `ff13c05c` | plan and tracking only (roadmap) |
| Multitenancy | `82bfd675`, `9c2a6f58`, `f101917e`, `95875445`, `523e7dec` | `api/packages/tenancy` (provisioning, isolation, unknown-host 404, Redis prefixes), workers/scheduler pick up new tenants, multi-tenant H5P service |
| Valkey | `ba74ef3f` | Redis image replaced by `valkey/valkey:8-alpine` (licence: BSD-3) |

Uncommitted work in progress at the time of writing (other agents): demo seeders
(`api/database/seeds/Demo*`), front landing/tenant code, invoices replacement.

---

## B. Framework upgrade plan (0.2)

### B.1 Target

| Item | Today | Target | Why |
|---|---|---|---|
| Laravel | 9.52.21 (EOL: security fixes ended Feb 2024) | **13.x** | Current major, released 17 Mar 2026, bug fixes until Q3 2027, security fixes until 17 Mar 2028 ([support policy](https://laravel.com/docs/13.x/releases#support-policy)). Laravel 12 security ends 24 Feb 2027, so stopping at 12 would buy only ~4 months. |
| PHP | 8.3.23 (`escolalms/php:8.3-alpine`, [`api/Dockerfile`](../../api/Dockerfile)) | **8.4** (8.3 is the L13 floor) | `tzsk/sms` 10 and `spatie/laravel-responsecache` 8 (the only lines supporting L13) require PHP ^8.4. PHP 8.4 security support runs to end of 2028. |
| PHPUnit | 9.6 | 12.x | L13 skeleton (`^12.0`); `orchestra/testbench` 11 accepts 11.5/12/13 |
| Testbench | 7.32 | 11.x | `api/packages/core/tests/TestCase.php:8` extends Orchestra Testbench, so **every package suite boots Testbench, not the app** |
| PostgreSQL | 12 (`api/docker-compose.yml:184`, EOL Nov 2024) | 16 or 17 | Not required by Laravel 13, but out of support; do it as a separate step |

Laravel 13 itself is a small step ([upgrade guide](https://laravel.com/docs/13.x/upgrade): "10 minutes");
the cost is in the 9→11 jump and in third-party packages.

### B.2 Blockers found with Composer

`composer why-not laravel/framework 11.0` (run in the `api` container, 2026-10-08):

| Package (installed) | Constraint today | Needed for L10/L11/L12/L13 | Action |
|---|---|---|---|
| `gecche/laravel-multidomain` 5.0 | `laravel/framework ^9.0` | **10.x / 11.x / 12.x / 13.0 all exist** and track Laravel majors ([repo](https://github.com/gecche/laravel-multidomain)) | Bump in lockstep. Not a blocker. Long-term: decide whether `api/packages/tenancy` replaces it (see B.6) |
| `laravel/passport` 11.10 | `^9 \| ^10` | 12 (L11), 13 (L11.35+) | See B.4 |
| `orchestra/testbench` 7 / canvas / workbench | `^9.52` | 8 / 9 / 10 / 11 | Bump in lockstep |
| `nunomaduro/collision` 7 | conflicts `>=11` | 8 | Bump |
| `spatie/laravel-ignition` 1.7 | `^8.77 \| ^9.27` | 2.x | Bump at L10 |
| `staudenmeir/laravel-migration-views` 1.6 | `illuminate/database ^9.0` | one major per Laravel major (1.7/1.9/1.11/1.12) | **Remove**: used only by 3 legacy migrations in `api/database/migrations/2022_0*_*searchable_events_view.php`; rewrite with `DB::statement` |
| `tzsk/sms` 6.0.0 (exact pin) | `^8 \| ^9` | 7.0.1 (L10), 8 (L11), 9 (L12), 10 (L13, PHP ^8.4) | Bump; used in `api/packages/templates-sms/src/{Facades/Sms.php,Drivers/*}` |
| `intervention/imagecache` 2.6 | `~10` max; **abandoned** | — | **Remove**: only `config/imagecache.php` (route `images`); the `images` package uses its own `ImageCache` model (inference: the Intervention route is unused; verify with route:list) |
| `intervention/image` 2.7 | no Laravel constraint | 3.x (API change) | Port `api/packages/images/src/Services/ImagesService.php:14-16,53,112-154` (`ImageManagerStatic::make` → `ImageManager` + driver) |
| `rennokki/laravel-eloquent-query-cache` 3.4 | `^9.35 \| ^10.5`; **abandoned**, last line 3.6 stops at L12 | — | **Replace** before L13: wrapper trait `api/packages/core/src/Models/Traits/QueryCacheable.php`, used by `Course`, `Lesson`, `Topic`, `AbstractTopicContent`, `TopicResource` in `api/packages/courses/src/Models`. Options: drop query caching (measure first) or a small own trait on `Cache::remember` |
| `spatie/laravel-responsecache` 7.4 | `^10` max | 7.7 (L10–12), 8.x (L12–13, PHP ^8.4) | Bump |
| `pbmedia/laravel-ffmpeg` 8.3 | `^9 \| ^10` | 8.9 | Bump (minor) |
| `laraveldaily/laravel-invoices` 3.3 (GPL-3.0) | `^9 \| ^10` | 4.2 | **Being replaced now** (licence); must be gone before L11 or bumped |
| `zanysoft/laravel-zip` 2.0 | `^10` max | 3.1 | Bump |
| `spatie/laravel-health` 1.34 | — | 1.40 (L11+) | Bump |
| `kreait/laravel-firebase` 5.10 | — | 6 (L11–12), 7 (L11–13, PHP ≥8.3) | Bump; check `kreait/firebase-php` 7→8 API |
| `spatie/laravel-permission` 6.25 | `^8.12 … ^13` | 6.x already supports L13 | Stay on 6.x during the upgrade; 7/8 later (PHP 8.3, L12+) |
| `darkaonline/l5-swagger` 8.6 | — | 9 (L11) … 11.1 (L11.44+/12/13, `zircote/swagger-php` 6) | Bump; swagger-php 4→6 may change annotation parsing (attributes/doctrine annotations) — run the swagger workflow |
| `devianl2/laravel-scorm` 4.0.1 (MIT) | `nesbot/carbon ^2.42` | **no release supports Carbon 3** | **Blocks L12** (L12 requires Carbon 3). Vendor into `api/packages/scorm` (MIT allows it) and relax the constraint; 64 references in 35 files |
| `treestoneit/shopping-cart` 1.6.1 | `illuminate ^9 … ^12` | — | **Blocks L13**. Cart is to be retired for Sylius (decision made); until then vendor/fork (MIT) |
| `gnello/laravel-mattermost-driver` 1.3.3 | `illuminate/support … ^12` | — | **Blocks L13**. Vendor or replace with the HTTP client (also fixes the `route:list` constructor-connect bug in the TODO) |
| `trax2/framework` 2.0.4 (GPL-3.0) | declares **no** requirements | unknown | Composer will not block it; runtime compatibility is untested (inference). Gate on the `lrs` suite. Licence issue is separate (audit §licences) |
| `bensampo/laravel-enum` 6.14 | `^9 … ^13` | 6.14.1 | Not blocking, not abandoned. Upstream recommends native enums (inference from README); migrate gradually, 14 files in 10 packages |
| `laravel/horizon` 5.48 | `^9.21 … ^13` | 5.50 | Not blocking |
| `maatwebsite/excel` 3.1.69 | `… ^13` (3.1.70) | 3.1.70 | Not blocking (stay on 3.1; 4.0 optional) |
| `spatie/laravel-translation-loader` 2.8 | `… ^13` | — | OK |
| `davidbadura/faker-markdown-generator` 1.1.0 (exact pin) | none | 1.1.1 | Unpin |
| `laravel/helpers` 1.7 | — | — | L13 adds `symfony/polyfill-php85` whose `array_first()`/`array_last()` can clash with these helpers ([L13 guide](https://laravel.com/docs/13.x/upgrade)). 11 helper calls in 6 files → replace with `Arr`/`Str` and drop the package |
| `doctrine/dbal` 3.10 | — | not needed from L11 | No direct use (`getDoctrineSchemaManager`, `getDoctrineColumn`, `registerDoctrineType`: 0 hits in `api/{app,packages,config,database}`); only needed for `->change()` on L9/L10. Remove at L11 |
| `rennokki` … `predis/predis` 2 | — | 2.x fine | Optional 3.x later |

### B.3 Code changes per Laravel step (scan of `api/{app,packages,config,database,routes,tests}`)

**9 → 10**
- `protected $dates` removed: `api/packages/courses/src/Models/CourseProgress.php:41`,
  `api/packages/courses/src/Models/CourseUserAttendance.php:16` → `$casts`.
- `Bus::dispatchNow`, `Redirect::home`, `MocksApplicationServices` (`expectsEvents` …), `(string) DB::raw`: **0 hits**.
- Monolog 3: 3 direct references in 1 file (check custom handlers/taps).
- `minimum-stability: dev` in `api/composer.json:361` → `stable` (guide recommends).
- `phpunit.xml:3` `processUncoveredFiles` must go for PHPUnit 10.
- `$routeMiddleware` (1 hit, `api/app/Http/Kernel.php`) may stay; rename optional.
- Native return types in skeleton: optional; not required for packages.
- `lang` path: the app uses `api/resources/lang` (still supported); 33 `resources/lang`/`lang_path` references in packages' providers keep working. No action.

**10 → 11** (highest risk)
- `->change()` migrations: in L11 every modifier must be restated, otherwise it is dropped. 22 calls in 10 migrations
  (`tasks`, `assign-without-account`, `auth`, `questionnaire`, `bookmarks_notes`, `cart` ×2, `templates` ×2,
  `dictionaries`). Already-applied migrations are unaffected on existing databases, but **fresh databases (CI, new
  tenants provisioned by `api/packages/tenancy`) would get different columns**. Fix: review each and restate modifiers,
  or squash with `php artisan schema:dump` (PostgreSQL dump) once.
- `double()`/`float()`: 6 `->double('x')` calls without precision (`topic-type-project`, `scorm`, `topic-type-gift`) → same DDL; no `unsignedDecimal` etc.
- Carbon 3 (`diffIn*` now signed floats). Real bugs to fix before switching:
  - `api/packages/auth/src/Services/AuthService.php:71` `expires_at->diffInMinutes(created_at)` → becomes **negative**, "remember me" detection breaks.
  - `api/packages/reports/src/Stats/Topic/QuizSummaryForTopicTypeGIFT.php:67` `end_at->diffInSeconds(started_at)` → negative attempt time.
  - `api/packages/questionnaire/src/Services/QuestionnaireModelService.php:236` → negative seconds.
  - `api/packages/courses/src/ValueObjects/CourseProgressCollection.php:111` → float (time-spent tracking).
  - Test `api/packages/topic-type-gift/tests/Api/QuizAttemptGetActiveApiTest.php:135`.
  Fix with `abs()`/argument swap and `(int)` casts while still on Carbon 2 (behaviour-neutral), then switch.
- Passport 11 → 12: migrations no longer auto-loaded (the app already has its own copies in
  `api/database/migrations/2016_06_01_00000*_create_oauth_*`); password grant off by default. The auth package issues
  personal access tokens (`api/packages/auth/src/Services/AuthService.php:65`, `createToken`), so the password grant is
  probably unused (inference: verify admin/front login flows and any `grant_type=password` client).
- `Passport::routes()` calls in package providers (`auth`, `settings`, `video`, `lrs`, `consultation-access`,
  `courses-import-export`) are already guarded with `method_exists` → harmless; delete.
- `UserProvider`/`Authenticatable` contract additions: no custom implementations (0 hits).
- Password rehash on login: users table column is `password` (default) — no action (verify).
- Per-second rate limiting, `Enumerable`, `BatchRepository`: 0 hits.
- Application structure: **keep the L10 structure** (guide's own recommendation). The slim skeleton
  (`bootstrap/app.php` with `Application::configure`, no `Http/Kernel`) is optional; gecche 11+ documents both, and the
  explicit provider list in `api/config/app.php` (vendored packages) is easier to keep as is. Revisit after L13.
- Cache key prefix no longer gets `:` appended for Redis → the tenancy package's per-tenant prefixes
  (`82bfd675`) must be re-checked; cache keys change once (flush on deploy).
- `doctrine/dbal` removed.
- PHPUnit 10/11: data providers must be `public static` (60 `@dataProvider` uses, 22 static providers found) — fix before 11.

**11 → 12**
- Carbon 3 mandatory → `devianl2/laravel-scorm` must be vendored/forked first.
- `HasUuids` → UUIDv7: 0 hits.
- `image` validation excludes SVG: ~92 `image` rule hits in 40 files → decide if SVG uploads are needed (course images, logos).
- `Storage::disk('local')` default root `storage/app/private` applies only when `local` is not configured — check
  `api/config/filesystems.php` defines `local` (58 calls in 11 files, mostly tests).
- `Blueprint`/`Grammar` constructors: 2 hits of `new Blueprint`/prefix APIs — check.
- `Schema::getTables()` multi-schema: relevant because the `h5p` index package reads the `h5p` schema
  (`api/packages/h5p`); `getColumnListing` calls in `api/app/Http/Controllers/Controller.php:16`,
  `api/packages/core/src/Repositories/BaseRepository.php:293`, `api/packages/auth/src/Listeners/MaskUserData.php:51` are fine.

**12 → 13**
- `VerifyCsrfToken` → `PreventRequestForgery` (2 files reference the old name; alias still works).
- Cache/session prefix defaults (only if not set explicitly — tenancy sets them; verify `config/cache.php`, `config/database.php` redis prefix, `config/session.php`).
- `cache.serializable_classes = false` in new skeleton: the API caches Eloquent models via query cache (inference) — keep the
  option unset or list classes.
- Session `serialization`: keep `php` to avoid logging everyone out (API is mostly token-based anyway).
- `laravel/helpers` vs polyfill-php85 (above), `laravel/tinker` ^3, PHPUnit 12 (doc-comment annotations removed:
  **163 `@test` and 60 `@dataProvider` annotations** → attributes; Rector `PHPUnitSetList` can do it).

### B.4 Package-level patches (vendored `api/packages/*`)

| Package | Patch | Step |
|---|---|---|
| `courses` | `$dates` → `$casts`; Carbon 3 in `CourseProgressCollection`; drop rennokki `QueryCacheable` | L10 / L11 / before L13 |
| `core` | replace `QueryCacheable` wrapper; `tests/TestCase.php` on Testbench 8→11 | each step |
| `auth` | Carbon 3 in `AuthService:71`; Passport 12/13 (`OAuthenticatable` on User, personal access client removal) | L11, L13 |
| `reports`, `questionnaire`, `topic-type-gift` | Carbon 3 diffs | L11 |
| `scorm` | absorb `devianl2/laravel-scorm` (MIT) source, relax Carbon | before L12 |
| `images` | Intervention Image 3; drop imagecache | any step (no Laravel coupling) |
| `templates-sms` | tzsk/sms 7→10 | each step |
| `cart` | shopping-cart fork or Sylius retirement; 2 `->change()` migrations | before L13 |
| `mattermost` | replace `gnello` driver with HTTP client | before L13 |
| `lrs` | `trax2/framework` runtime check; `Passport::loadKeysFrom` | L11 |
| `invoices` | finish GPL replacement | before L11 |
| `tasks`, `assign-without-account`, `bookmarks_notes`, `templates`, `dictionaries`, `questionnaire`, `auth` | restate `->change()` modifiers | L11 |
| 6 packages with `Passport::routes()` | delete dead guard | L11 |
| 10 packages with `bensampo/laravel-enum` | optional native enums | after L13 |
| all with tests | PHPUnit 10–12 (static providers, attributes) | L11–L13 |

Passport 13 specifics ([UPGRADE.md](https://github.com/laravel/passport/blob/13.x/UPGRADE.md)): client UUIDs (already
`client_uuids => true` in `api/config/passport.php:31`), client secrets always hashed (`passport:hash`),
`oauth_personal_access_clients` table and `personal_access_client` config removed (used at
`api/config/passport.php:44` and `AuthService.php:65`), `oauth_clients` columns
`redirect`/`password_client`/`personal_access_client` → `redirect_uris`/`grant_types`, User must implement
`OAuthenticatable`, key file permissions validated (0600/0660), `token()` returns `AccessToken`. Needs a data migration
per tenant database.

### B.5 Order of work

Step-by-step (9→10→11→12→13), one branch `phase-0/laravel-upgrade`, one commit series per step, suite green at each
step. A direct 9→13 jump is not recommended: gecche, Testbench, Passport and Carbon all change at different steps and a
single red suite would hide which change broke what. Shift can be used per step if wanted (paid, optional).

| # | Step | Contents | Effort (estimate) |
|---|---|---|---|
| 0 | Baseline | Record current failures (core 6, auth 3). **No CI runs today**: workflows live in `api/.github`, `admin/.github`, `front/.github` and GitHub only reads the root `.github/` — move `api/.github/workflows/phpunit-tests.yml` to the root first (TODO item) so every step is gated. Add a smoke test that boots the real app through gecche (Testbench does not) | 1–2 d |
| 1 | Prep on L9 | Remove `staudenmeir/laravel-migration-views`, `intervention/imagecache`, `laravel/helpers`; Intervention Image 3; Carbon-safe diffs; `$dates`; static data providers; finish invoices replacement; unpin faker-markdown | 2–3 d |
| 2 | L10 | framework ^10, gecche ^10, testbench 8, ignition 2, collision 7, tzsk 7, PHPUnit 10, Monolog 3 | 1–2 d |
| 3 | L11 | framework ^11, gecche ^11, Passport 12, testbench 9, collision 8, Carbon 3, `->change()` fixes, drop doctrine/dbal, l5-swagger 9+, responsecache 7.7, firebase 6, health, zip 3, ffmpeg 8.9 | 3–5 d |
| 4 | L12 | vendor laravel-scorm; framework ^12, gecche ^12, PHPUnit 11, testbench 10, SVG rule review | 2–3 d |
| 5 | PHP 8.4 | new base image (replaces `escolalms/php`, already a TODO), extensions (excimer, gd/imagick, zip, redis) | 1 d |
| 6 | L13 | replace query cache, cart fork (or Sylius), Mattermost driver; framework ^13, gecche 13, Passport 13 + data migration, PHPUnit 12 attributes (Rector), tinker 3, responsecache 8, tzsk 10, CSRF rename | 3–5 d |
| 7 | Follow-ups | PostgreSQL 12 → 16/17; Soketi → **Laravel Reverb** (first-party, MIT; needs L10.47+, so possible after step 2, sensible after 6); optional slim skeleton; native enums | 2–4 d |

Total: roughly **15–25 developer-days** (inference; the spread is mostly Passport 13 data migration, Carbon 3 behaviour
and the third-party forks).

### B.6 Multitenancy during the upgrade

`gecche/laravel-multidomain` has releases matching every Laravel major (10.x–13.0), so it does not block the upgrade.
It is used in `api/bootstrap/app.php:20` (Application), `api/app/Http/Kernel.php:6`, `api/app/Console/Kernel.php:10`,
`api/config/app.php:160` (queue provider); the Horizon integration is commented out
(`api/app/Providers/HorizonServiceProvider.php:8`). Recommendation: bump it in lockstep now; decide separately (ADR)
whether `api/packages/tenancy` should take over env resolution later, because gecche's env-file-per-host model is what
makes dynamic tenants awkward (see audit §multitenancy).

### B.7 Test strategy

- Gate per step: full PHPUnit run in the `api` container (`make test-phpunit` → `./vendor/bin/phpunit`, 46 suites in
  `api/phpunit.xml`), on PostgreSQL, compared with the baseline (core 6, auth 3 known failures). No new failures allowed.
- Package suites boot Orchestra Testbench (`api/packages/core/tests/TestCase.php:8`), not the real application, so they
  do not exercise gecche, the provider order in `config/app.php` or tenancy. Add per step:
  `php artisan about`, `route:list`, `migrate:fresh --seed` on a fresh tenant DB, `queue:work` + Horizon boot,
  `schedule:list`, `l5-swagger:generate`, a tenant provisioning run (`api/packages/tenancy`), and the admin/front
  Playwright smoke tests against the upgraded API.
- Schema diff: `pg_dump --schema-only` of a fresh migrate before and after step 3 (catches `->change()` drift).
- Data: run the Passport 13 migration on a copy of a real tenant database.

### B.8 Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Carbon 3 sign/float changes break progress, time-spent, quiz reports silently | High | Medium | Fix the 5 known `diffIn*` calls on L9; add assertions |
| `->change()` drift on fresh tenant databases | Medium | High | Restate modifiers or squash; schema diff in CI |
| Passport 13 client/token migration logs users out or breaks LRS guard (`api/packages/lrs/src/Extensions/AccessTokenGuard.php:89`) | Medium | High | Rehearse on DB copy; keep integer IDs if needed |
| `trax2/framework` (no declared constraints) fails at runtime on L11+ | Medium | Medium | `lrs` suite + manual xAPI statement round-trip; replacement is on the table for licence reasons anyway |
| Forked third-party packages (scorm, cart, mattermost) become our maintenance | High | Low | Keep forks minimal, vendored under `api/packages` with licence files |
| PHP 8.4 base image: `escolalms/php` images are upstream-owned | Medium | Medium | Build our own image (TODO item) |
| Concurrent feature work conflicts | High | Medium | Freeze `api/composer.*` during steps 2–6; rebase feature branches per step |
| Swagger annotation parsing changes (swagger-php 6) | Medium | Low | Run `api/.github/workflows/swagger.yml` |

### B.9 Rollback

- Each step is its own commit range and is merged only with a green suite; rollback = revert the step's merge and
  restore `api/composer.lock`.
- Database: steps 2–4 add no schema changes except Passport (step 3: none required; step 6: Passport 13 migration).
  Back up each tenant DB before step 6 and ship a down migration for the `oauth_clients` changes.
- Cache: flush Redis/Valkey per tenant after steps 3 and 6 (prefix behaviour changes).
- Images: keep the previous Docker image tag (PHP 8.3 / L9) deployable until step 6 is accepted.

### B.10 Open questions

1. Target Laravel 13 on PHP 8.4 (recommended) or stop at 12 on 8.3 (security end Feb 2027)?
2. Retire `cart`/`payments`/`vouchers` before step 6 (Sylius), or fork `treestoneit/shopping-cart` now?
3. Keep LRS on `trax2/framework` (GPL) through the upgrade, or replace it first?
4. Use Laravel Shift for steps 2–4 (paid), or do it by hand?
5. Is the password grant used by any client (admin, front, mobile, integrations)?
