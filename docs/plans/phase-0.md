# Phase 0 plan: foundation and framework upgrade

Status: section B **approved**; implementation in progress. Upgrade steps 1 and 2 of 4 (Laravel 10, Laravel 11) are
done, see [B.11](#b11-progress) and [B.12](#b12-step-24-laravel-10--11).
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

**9 → 10** (done, see B.11)
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

### B.11 Progress

Steps are counted as the four framework jumps (L10, L11, L12, L13); PHP 8.4 comes with the L12/L13 steps.

#### Step 1/4: Laravel 9 → 10 (done, 2026-10-08, uncommitted on `phase-0/foundation`)

Result: `laravel/framework` v9.52.21 → **v10.50.3** on PHP 8.3; full PHPUnit run shows no new failures (table below).

Composer (`api/composer.json`, lock updated only for these packages and their dependencies, 74 lock entries changed):

| Package | From | To | Note |
|---|---|---|---|
| `laravel/framework` | ^9 (9.52.21) | ^10.48 (10.50.3) | pulls `monolog/monolog` 2.11 → 3.12, `league/commonmark` 2.10, Symfony 6.4 patch releases |
| `gecche/laravel-multidomain` | ^5.0 | ^10.2 (10.2) | same integration points (Application, both Kernels, queue provider); no code change |
| `orchestra/testbench` | ^7 (7.32) | ^8.0 (8.39, core 8.44) | `canvas` 8, `workbench` 8 follow |
| `spatie/laravel-ignition` | ^1.0 (1.7.2) | ^2.0 (2.9.1) | |
| `staudenmeir/laravel-migration-views` | ^1.0 (1.6.3) | ^1.7 (1.8) | kept for now (removal is still planned) |
| `tzsk/sms` | 6.0.0 (exact) | ^7.0 (7.0.1) | no API change for our two drivers |
| `phpunit/phpunit` | ^9.0 (9.6.35) | ^9.6 (9.6.38) | **stays on PHPUnit 9**: Testbench 8 supports 9.6 and 10; staying avoids the `phpunit.xml` schema change and static data providers in the same step. PHPUnit 10/11 moves to step 2 (L11, Testbench 9 needs ≥10.5) |
| `minimum-stability` | dev | stable | upgrade guide recommendation (`prefer-stable` was already on) |
| `nunomaduro/collision` | ^7 | unchanged | 7.x already supports L10 |
| Not touched | | | Passport 11.10 (supports L10), Horizon 5.50, Tinker 2.11, Socialite 5, l5-swagger 8.6, maatwebsite/excel 3.1.70, spatie/* (permission 6.25, responsecache 7.7, health 1.34, translation-loader 2.8), `devianl2/laravel-scorm` 4.0.1, `treestoneit/shopping-cart` 1.6.1, `rennokki/laravel-eloquent-query-cache` 3.6, `gnello/laravel-mattermost-driver` 1.3.3, `intervention/imagecache` 2.6, `pbmedia/laravel-ffmpeg`, `zanysoft/laravel-zip` 2, `kreait/laravel-firebase` 5 — all accept L10 |
| Dropped from the lock | | | `spatie/laravel-ray`, `spatie/ray`, `php-di/*`, `zbateson/*` (transitive dev dependencies of the Testbench 7 line; no references in our code) |

No forks or patches of third-party packages were needed for L10.

**Composer 2.10 security blocking.** Every Laravel 9, 10 and 11 release has unfixed advisories
(e.g. PKSA-mdq4-51ck-6kdq "CRLF injection in default email rule", fixed only in 12.60+/13.10+), so Composer 2.10
refuses to *resolve* `laravel/framework` 10. `policy.advisories.ignore`/`ignore-id` in `composer.json` did not lift
the block for the root requirement in our test, so the update ran with `COMPOSER_NO_BLOCKING=1`. `composer install`
from the lock is not affected. Until step 3/4 (L12.69+), run updates as
`COMPOSER_NO_BLOCKING=1 composer update …`. `firebase/php-jwt` 6.x (CVE-2025-45769) is pinned by Passport 11 and
goes away with Passport 12/13. The L9 lock had the same advisories plus the file validation bypass (CVE-2025-27515), which has no 9.x fix and is
fixed in 10.48.29+.

Code changes:

| Category | Files | Change |
|---|---|---|
| `$dates` removed in L10 | `packages/courses/src/Models/CourseProgress.php`, `CourseUserAttendance.php` | moved to `$casts` (`deleted_at`, `finished_at`, `attendance_date` as `datetime`) |
| `DB::raw` no longer stringable | `app/Library/UlamsHelpers.php` | `DB::select(DB::raw(...))` → plain SQL with a bound `LIKE` parameter (helper is currently unused) |
| `ResourceCollection::toArray(Request $request)` is typed in L10 | `packages/cart/src/Http/Resources/BaseProductResource.php`; test `packages/vouchers/tests/Api/AdminVoucherTest.php` | pass `$request ?? request()` to nested collections; tests pass `request()` instead of `null` to collections |
| L10 default validation messages ("The :attribute **field** must …") | `packages/cart/tests/API/AdminProductApiTest.php`, `CartApiTest.php` | expected strings updated (Testbench uses the framework's own language lines; the app keeps its `resources/lang/en/validation.php`, so API messages for clients are unchanged) |
| Skeleton | `app/Http/Kernel.php` | `$routeMiddleware` → `$middlewareAliases` (L10 name; old name still works until L11) |

Checked and not needed: `Bus::dispatchNow`, `Redirect::home`, `MocksApplicationServices`, `(string) DB::raw`, `reduceWithKeys`/`reduceMany`,
`assertDeleted`, `QueryException` constructor, custom Monolog handlers (only class names in `config/logging.php`),
`CastsAttributes` implementations (untyped parameters stay compatible), `Rule` implementations (contract still exists).

Verification:

- Tests: full `./vendor/bin/phpunit` per suite on PostgreSQL, CI-style prepared DB (`migrate:fresh`, permissions seeder,
  personal access client, courses test migration). Baseline (L9) vs L10 on the final code, both on freshly prepared databases:

  | Suite | L9 baseline (tests / failing) | L10 final |
  |---|---|---|
  | Integrations (`api/tests`) | 4 / 2 | 4 / 2 (same tests: `EventApiTest` list/order) |
  | auth | 124 / 3 | 124 / 3 (same: `UserApiTest` list/search) |
  | bulk-notifications | 44 / 3 | 44 / 3 (same multicast tests) |
  | core | 62 / 6 | 62 / 6 (same paginate/count tests) |
  | reports | 51 / 2 | 51 / 0 (`ExportStatsTest::testFinishedTopicsSheets` data sets fail at random on both versions) |
  | consultations | 82 / 1 | 82 / 0 (`ConsultationChangeTermTest::testChangeTermForOneUser`, timing-flaky) |
  | other 40 suites | 1 857 / 0 | 1 857 / 0 |
  | **Total** | **2 224 / 17** | **2 224 / 14**, no new failures |

  Before the code fixes, L10 had 17 new failures (cart 14, vouchers 3): the typed
  `ResourceCollection::toArray()` and the new validation wording.

- `php artisan about`: Laravel 10.50.3; `route:list`: 527 routes, identical method/URI list before and after;
  `schedule:list` unchanged; `l5-swagger:generate`: OK (311 paths); Horizon restarted on the new code and running;
  `queue:work --once` OK; platform `migrate` and `ulams:tenant:sync-env --migrate` (coffee, nightsky, oncall): nothing
  pending, tenant DBs resolve (`ulams_coffee`); `migrate:fresh` on an empty DB runs clean on L10 (incl. the
  migration-views migrations).
- HTTP smoke: `GET /api/name` 200; login `admin@ulams.app` → token, `GET /api/profile/me` 200; `GET /api/courses` 200
  on `api.localhost`, `coffee.localhost`, `oncall.localhost` (tenant courses returned); H5P service via
  `H5PServiceClient::show()`/`download()` OK; `GET /api/admin/templates/{id}/preview` 200 and
  `POST /api/admin/pdfs/preview` returns a PDF (pdfme service).

Risks for step 2 (L11), beyond B.3/B.8:

- Composer blocking (above) stays until L12.69+; CI's `composer update --no-scripts` (in `api/.github`, not running yet)
  needs `COMPOSER_NO_BLOCKING=1` or should switch to `composer install`.
- PHPUnit 10 + Testbench 9 + Collision 8 land together with L11 (deferred from this step).
- Validation-message assertions: tests compare framework wording verbatim; expect more of these on each step.
- `ResourceCollection::toArray(null)` pattern: only cart/vouchers hit it; other packages call `->toArray(null)` on single
  resources (stationary-events, course-access, cart tests), which still works but would break if those resources nest
  collections.
- The known "core 6 / auth 3 / bulk-notifications 3" failures are DB-state dependent: they expect 10 users and see 11 on a freshly
  prepared DB, and passed in a run on a DB reused after a full run (inference: a row left by the prep). Compare runs only
  on freshly prepared databases.

### B.12 Step 2/4: Laravel 10 → 11

Done 2026-10-08, uncommitted on `phase-0/foundation`. Result: `laravel/framework` v10.50.3 → **v11.57.0** (latest 11.x)
on PHP 8.3 (8.4 stays with step 4). The full PHPUnit run shows no new failures (table below). The non-slim skeleton is
kept: `config/app.php` provider list, both Kernels, gecche's `Application` in `bootstrap/app.php`.

Composer (`api/composer.json`; the lock changed only for the packages listed and their dependencies, 71 lock entries).
Run updates as `COMPOSER_NO_BLOCKING=1 composer update -W <packages>`: Composer 2.10 still refuses to resolve
`laravel/framework` 11 (same advisories as in B.11; the CRLF email-rule fix exists only in 12.60+/13.10+).

| Package | From | To | Note |
|---|---|---|---|
| `php` (constraint) | >=8.1 | >=8.2 | Laravel 11 floor |
| `laravel/framework` | ^10.48 (10.50.3) | ^11.0 (11.57.0) | pulls Symfony 7.4 (console, http-kernel, mailer, routing…), `laravel/prompts` 0.3, `serializable-closure` 2, `symfony/polyfill-php85` |
| `gecche/laravel-multidomain` | ^10.2 | ^11.0 (11.2) | same integration points; no code change |
| `laravel/passport` | ^11 (11.10.6) | ^12.0 (12.4.3) | `league/oauth2-server` 8.5 and `firebase/php-jwt` 6.11 unchanged |
| `orchestra/testbench` | ^8.0 (8.39) | ^9.0 (9.18, core 9.23) | canvas 9, workbench 9 |
| `phpunit/phpunit` | ^9.6 | ^10.5 (10.5.66) | |
| `nunomaduro/collision` | ^7 | ^8.1 (8.5) | |
| `spatie/laravel-ignition` | ^2.0 | ^2.4 (2.12) | |
| `php-mock/php-mock-phpunit` | ^2.6 | ^2.10 (2.16) | PHPUnit 10 support |
| `rennokki/laravel-eloquent-query-cache` | ^3 (3.4) | ^3.6 (3.6.1) | 3.4 capped at L10; 3.6 is the last line (L11/L12) |
| `spatie/laravel-responsecache` | ^7.4 | ^7.7 (7.7.2) | 7.4 capped at L10 |
| `pbmedia/laravel-ffmpeg` | ^8 (8.3) | ^8.9 (8.9.0) | |
| `tzsk/sms` | ^7.0 | ^8.0 (8.0.0) | no API change for our two drivers (templates-sms suite green) |
| `zanysoft/laravel-zip` | ^2 | ^3.1 (3.1.0) | 2.x capped at L10; facade and provider names unchanged |
| `spatie/laravel-health` | ^1.30 (1.34) | unchanged constraint (1.40.2) | |
| `maatwebsite/excel` | 3.1.69 | 3.1.70 | CVE-2026-84374 (export path escape) |
| `composer/composer` | 2.10.2 | 2.10.3 | CVE-2026-59944, CVE-2026-84361 |
| `doctrine/dbal` | ^2\|^3 (3.10) | **removed** | only needed for `->change()` before L11; no direct use |
| `intervention/imagecache` | ^2 (2.6) | **removed** | abandoned, capped at L10; it only registered the unused `GET images/{template}/{filename}` route (no caller in admin/front/api); `config/imagecache.php` deleted. The `images` package has its own cache |
| `staudenmeir/laravel-migration-views` | ^1.7 | **removed** | the 3 `searchable_events` view migrations now use `DB::statement('CREATE VIEW …')` (same SQL) |
| `laravel/helpers` | ^1.7 | **removed** | `symfony/polyfill-php85` (via Symfony 7.4) now defines 1-argument `array_first()`/`array_last()`, which silently shadow the helpers' callback versions. The only helper call (`str_slug` in `app/Library/UlamsHelpers.php`) → `Str::slug` |
| Not touched (accept L11) | | | Horizon 5.48, Socialite 5, Tinker 2.11, l5-swagger 8.6, Sentry 4, kreait/laravel-firebase 5.10, spatie/permission 6, image-optimizer 1.8, translation-loader 2.8, bensampo/laravel-enum 6, `devianl2/laravel-scorm` 4.0.1, `treestoneit/shopping-cart` 1.6.1, `gnello/laravel-mattermost-driver` 1.3.3, barryvdh/dompdf 2 |

`nesbot/carbon` stays on **2.73**: Laravel 11 accepts Carbon 2 or 3 and `devianl2/laravel-scorm` requires `^2.42`.
Carbon 3 arrives with step 3 (L12 requires it), after the scorm package is vendored.

`composer audit` after the step: 5 advisories in 2 packages, all known and not fixable on L11: `laravel/framework`
(CRLF email rule, signed URL path confusion, debug page XSS: fixed only in 12.6x/13.x) and `firebase/php-jwt` 6
(CVE-2025-45769, needs 7.x; Passport 12 pins 6). dompdf advisories are ignored in `composer.json` as before.

Code changes:

| Category | Files | Change |
|---|---|---|
| `->change()` drops unstated modifiers (L11 changes columns natively) | `auth/…/2022_01_26_130000_change_user_settings_value_field_type_.php`, `cart/…/2023_04_24_000000_product_description_to_text.php` | restate `->nullable()` (up and down); without it fresh databases got `NOT NULL` on `user_settings.value` and `products.description` |
| L11 emits the column comment of a `change()` after the whole blueprint | `templates/…/2021_12_09_000001_modify_templates_table.php` | the `vars_set` change runs in its own `Schema::table` before the rename (fresh migrate failed with `column "vars_set" does not exist`) |
| Other `->change()` migrations (`tasks`, `assign-without-account`, `bookmarks_notes`, `cart` tax rate, `templates` content, `dictionaries`, `questionnaire`) | — | checked by schema diff: same result, no change needed |
| Passport 12 `passport:install` now publishes Passport's migrations with new timestamps and asks to run `migrate` | `core/…/2021_03_11_000002_install_passport.php` | calls what Passport 11's `install` did: `passport:keys`, a personal access client and a password grant client (fresh DBs keep the same two `oauth_clients` rows; the password grant itself stays **disabled**, the Passport 12 default; no client uses it, Swagger's password flow is commented out) |
| `Passport::routes()` no longer exists (already a no-op on Passport 11) | 28 package `AuthServiceProvider`s | dead `method_exists` guards removed (incl. the unreachable `Passport::loadKeysFrom` in `lrs`) |
| Redis cache prefix: L11 stopped appending `:` | `config/cache.php` (`stores.redis.prefix`) | `CACHE_PREFIX` + `:` so keys keep the L10 layout per tenant (`ulams_coffee_` + `ulams_coffee_cache:` + key); tenant env files unchanged. Verified on a live key |
| `Collection::shuffle($seed)` lost its seed in L11 (now random) — quiz order "stable per attempt" broke silently | new `topic-type-gift/src/Support/SeededShuffle.php`; `QuizAttemptResource`, `MatchingQuestionStrategy`, `MultipleChoice*QuestionStrategy`; test `QuizAttemptReadApiTest` | seeded shuffle with the L10 algorithm (`mt_srand` + `shuffle`), so orders of open attempts are identical to L10 (caught by 4 `QuizAttemptReadApiTest` failures) |
| Carbon 3 `diffIn*` (signed floats) | `auth/AuthService.php:71` (remember-me), `reports/QuizSummaryForTopicTypeGIFT.php:67`, `questionnaire/QuestionnaireModelService.php:236`, `courses/CourseProgressCollection.php:111`, test `QuizAttemptGetActiveApiTest.php:161` | `(int) abs(…)`: same result as Carbon 2 today, correct on Carbon 3. These are all `diffIn*` calls in `app`/`packages` |
| PHPUnit 10 configuration | `phpunit.xml`, `docker/envs/phpunit.xml.{postgres,mysql,cc}`, `.gitignore` | `--migrate-configuration` (10.5 schema, `<coverage>` → `<source>`, `.phpunit.cache`) |
| PHPUnit 10 data providers | 32 test files | providers made `static`; two that booted the app (`createApplication()` + `config()`) read the package config file instead (`courses` `TopicResourceTutorApiTest`, `files` `FilesApiUploadTest`; PHPUnit 10 runs providers before any app exists) |
| PHPUnit 10 requires class name = file name | `topic-types/tests/Commands/FixAssetCommandTest.php`, `FixColumnNameCommandTest.php` | classes renamed (`FixAssetCommand` → `FixAssetCommandTest`, …); their 4 tests were silently skipped on PHPUnit 10 |

Checked and not needed: `Model::casts()` conflicts (none), `withConsecutive`/removed PHPUnit assertions (none),
`getDoctrine*`/`registerDoctrineType`/`Schema::getAllTables` (none), `double()`/`float()` (6 `double()` calls, same DDL),
`unsigned*` decimal types (none), rate limiting `decayMinutes`/`GlobalLimit` (none), custom `UserProvider`/`Authenticatable`
(none; password rehash on login uses the default `password` column), `@test` annotations (still work in PHPUnit 10, kept).
`config/*.php` stays as is; L11 merges the framework's default stores/connections into it (adds unused entries only).

Verification:

- **Schema**: `migrate:fresh` (+ courses test migration) on an empty DB on L10 and on L11, `pg_dump --schema-only` diff
  (`api/storage/l11up/schema_l10.sql`, `schema_l11.sql`, `schema.diff`): identical except the text of two equal defaults
  (`order_items.tax_rate`, `products.tax_rate`: `DEFAULT 0` vs `DEFAULT '0'::numeric`, same value). Row counts after the
  migration-time seeds are identical in all 128 tables (incl. the two `oauth_clients`).
- **Tests**: full `./vendor/bin/phpunit` per suite on a freshly prepared PostgreSQL DB (`test_l11`, scripts in
  `api/storage/l11up/`; prep now also copies the Passport keys into Testbench's skeleton storage, as CI does, because
  the composer update replaced that directory).

  | Suite | L10 (tests / failing) | L11 final |
  |---|---|---|
  | Integrations | 4 / 2 | 4 / 2 (same `EventApiTest` list/order) |
  | auth | 124 / 3 | 124 / 3 (same `UserApiTest`) |
  | bulk-notifications | 44 / 3 | 44 / 3 (same multicast tests) |
  | core | 62 / 6 | 62 / 6 (same paginate/count tests) |
  | cart | 113 / 0 | 113 / 1 (`test_update_product_subscription_type_cannot_update_subscription_fields`: random factory data can equal the stored subscription fields, then the update is allowed; passed in the first L11 run and in 3 reruns) |
  | other 41 suites | 1 877 / 0 | 1 877 / 0 |
  | **Total** | **2 224 / 14** | **2 224 / 15**, no new deterministic failures |

  Before the fixes the first L11 run had 2 PHPUnit errors (app-booting data providers), 4 `topic-type-gift` failures
  (seeded shuffle) and 4 `topic-types` tests not run (class names).
- `php artisan about`: Laravel 11.57.0; `route:list`: **526** routes = the 527 of L10 minus `GET images/{template}/{filename}`
  (imagecache), otherwise identical; `schedule:list`: 11 entries; `l5-swagger:generate`: OK (311 paths, as on L10);
  `composer validate`: valid (only the old warning on the exact `davidbadura/faker-markdown-generator` pin).
- Platform `migrate`: nothing to migrate. `ulams:tenant:sync-env --migrate`: coffee, nightsky, oncall synced, 0 pending,
  tenant DBs resolve (`ulams_coffee`, …).
- Horizon restarted on the new code (`horizon:status` running), php-fpm reloaded; `queue:work --once` (platform and
  `--domain=coffee.localhost`) OK.
- HTTP smoke: `GET /api/name` 200; login `admin@ulams.app` → token, `GET /api/profile/me` 200; `GET /api/courses` 200 on
  `api.localhost` (5), `coffee.localhost` (1), `oncall.localhost` (1); tenant login `admin@coffee.ulams.app` 200 and
  `/api/profile/me` 200, the coffee token is rejected on `api.localhost` (401); H5P service `show(3)`/`download(3)` OK;
  `GET /api/admin/templates/{id}/preview` 200; `POST /api/admin/pdfs/preview` returns a PDF.

Risks and follow-ups for step 3 (L12) and later:

- **Carbon 3** is mandatory on L12: vendor `devianl2/laravel-scorm` first. The 5 `diffIn*` call sites are already safe;
  watch other Carbon 3 changes (`createFromTimestamp` UTC default, stricter `create*` parsing) in the scorm code.
- **PHPUnit 11** (with Testbench 10): 154 PHPUnit deprecations remain in 8 suites — data sets whose string keys do not
  match parameter names (named arguments in PHPUnit 11; e.g. `cart` `$errors`, `$filter`) and 6 providers that still use
  `$this` (`lrs` `tokenDataProvider`, `tasks`/`bookmarks_notes` order providers with `$this->assert…` closures). Two
  helper classes named `*Test` (`topic-types/tests/Helpers/MarkdownTest.php`, `webinar/tests/Mocks/MockTest.php`)
  produce runner warnings.
- **Silent API changes**: the shuffle seed removal passed `composer`, boot and static checks and was found only by
  tests. Expect more of this kind in L12 (e.g. `HasUuids` v7, `image` rule without SVG).
- **Composer blocking** stays until L12.69+ (`COMPOSER_NO_BLOCKING=1`).
- **Password grant**: disabled by default from Passport 12. If a client ever needs it, call `Passport::enablePasswordGrant()`;
  Passport 13 (step 4) removes the personal-access-client table and changes `oauth_clients` (B.4).
- **Cache**: the explicit `:` keeps key layout; L12/L13 change more prefix defaults (B.3) — keep `CACHE_PREFIX`,
  `REDIS_PREFIX` and the store prefix explicit.
- The cart subscription test is flaky by design (random factory data); worth pinning its values when that package is
  touched.

### B.13 Step 3/4: Laravel 11 → 12

Done 2026-10-08, uncommitted on `phase-0/foundation`. Result: `laravel/framework` v11.57.0 → **v12.69.3** (latest
12.x) on PHP 8.3, Carbon 2.73 → **3.14**. The full PHPUnit run shows no new failures (table below). The non-slim
skeleton stays.

**Composer blocking is gone.** `composer update` resolved without `COMPOSER_NO_BLOCKING`, and `composer audit` reports
no advisories: 12.69 fixes the framework advisories, `firebase/php-jwt` 7 fixes CVE-2025-45769, and dompdf 3.1.6 fixes
the dompdf advisories, so the `policy.advisories.ignore: [dompdf/dompdf]` block was removed from `api/composer.json`.

Composer (`api/composer.json`; the lock changed only for the packages listed and their dependencies, 59 lock entries;
update run as `composer update -W <packages>`):

| Package | From | To | Note |
|---|---|---|---|
| `laravel/framework` | ^11.0 (11.57.0) | ^12.69 (12.69.3) | pulls `symfony/translation` 7.4, `symfony/clock` |
| `nesbot/carbon` | 2.73 (transitive) | ^3.8 (3.14.2), now a direct requirement | the vendored scorm code and many packages use it directly |
| `gecche/laravel-multidomain` | ^11.0 | ^12.0 (12.2) | same integration points; no code change |
| `devianl2/laravel-scorm` | ^4 (4.0.1) | **removed, vendored** | see below; `doctrine/common` and 3 `doctrine/*` deps it pulled are gone (unused) |
| `firebase/php-jwt` | ^6 (6.11.1) | ^7.0 (7.2.1) | 6.x is blocked by CVE-2025-45769; Passport 12.4, Socialite, google/apiclient and kreait accept 7. 7.x rejects HS256 keys shorter than 32 bytes (see Jitsi below) |
| `barryvdh/laravel-dompdf` | ^2.2 | ^3.1 (3.1.2, dompdf 3.1.6) | 2.x capped at L11; invoices suite green |
| `kreait/laravel-firebase` | ^5 (5.10) | ^6.0 (6.2.0) | `kreait/firebase-php` stays on 7.x; no API change for `PushNotificationChannel` |
| `tzsk/sms` | ^8.0 | ^9.0 (9.0.0) | templates-sms suite green |
| `orchestra/testbench` | ^9.0 (9.18) | ^10.12 (10.12, core 10.15) | canvas 10, workbench 10 |
| `phpunit/phpunit` | ^10.5 (10.5.66) | ^11.5 (11.5.57) | see "PHPUnit 11" below |
| `nunomaduro/collision` | ^8.1 (8.5) | ^8.6 (8.9.5) | 8.5 conflicts with L12 |
| `ext-zip`, `ext-dom` | — | `*` | taken over from the scorm package |
| Not touched (accept L12) | | | Passport 12.4.3, Horizon 5, Socialite 5, Tinker 2, l5-swagger 8.6, Sentry 4, spatie/* (permission 6, responsecache 7.7, health 1.40, translation-loader 2.8, image-optimizer 1.8), maatwebsite/excel 3.1.70, bensampo/laravel-enum 6, pbmedia/laravel-ffmpeg 8.9, zanysoft/laravel-zip 3.1, `rennokki/laravel-eloquent-query-cache` 3.6.1, `treestoneit/shopping-cart` 1.6.1, `gnello/laravel-mattermost-driver` 1.3.3 |

**PHPUnit 11 instead of 10.5.** `orchestra/testbench` 10 requires PHPUnit ^11.5.3 (only `testbench-core` alone would
allow 10.5.35), and `nunomaduro/collision` 8.6+ (the first line that accepts L12) conflicts with PHPUnit < 11.5. So the
step moved to PHPUnit 11 and fixed what that needs; the test run now shows **0 PHPUnit deprecations** (154 on L11).

`devianl2/laravel-scorm` → `api/packages/laravel-scorm` (MIT, upstream `LICENSE` and `README.md` kept, provenance in
`api/packages/README.md` "Third-party forks"). The upstream namespace `Peopleaps\Scorm\` is kept and autoloaded via
PSR-4, so its 35 callers did not change; a separate directory keeps third-party code apart from our `scorm` module
(`Ulams\Scorm\`). Its provider and the `ScormManager` facade alias are registered in `config/app.php` (composer
discovery did that before). Carbon 3 needed no code change (it only calls `Carbon::now()`/`Carbon::parse()`); the four
implicitly nullable parameters were made explicit (`?Sco`, `?Scorm`, `?Carbon`), which PHP 8.4 (step 4) deprecates.

Code changes:

| Category | Files | Change |
|---|---|---|
| Carbon 3: `setTimezone(null)` throws `TypeError` (Carbon 2 fell back to the default timezone) | `templates-email/src/{Webinar/CommonWebinarVariables,Courses/DeadlineIncomingVariables,Consultations/CommonConsultationVariables,ConsultationAccess/ConsultationAccessEnquiry{AdminCreated,Approved}Variables}.php`, `templates-sms/src/Consultations/CommonConsultationVariables.php` | `$user->current_timezone ?? config('app.timezone')`. `users.current_timezone` is nullable, so e-mails/SMS for users without a stored timezone would have failed (caught by 2 `templates-email` tests) |
| L12 `ResourceCollection::toArray()` resolves every item and casts it to an array: a resource whose `toArray()` returns a scalar became a one-element list (`"proposed_terms": [["2026-…"]]`) | `consultations/src/Http/Resources/Consultation{Term,ProposedTerm}Resource.php` | `resolve()` returns the serialised date, so `proposed_terms` and `busy_terms` keep the L11 shape (flat list of ISO dates). Caught by 3 `consultations` tests; these are the only resources returning a scalar |
| `image` rule rejects SVG by default | `categories/src/Http/Requests/Category{Create,Update}Request.php`; new test `CategoriesApiTest::testUpdateCategoryIconAcceptsSvg` | category icons are SVG (the factory and seed icons in `database/multimedia/categories` are `.svg`), so `icon` uses `image:allow_svg`. The 14 other `image` rules (course image/poster, product poster, consultation/stationary event/webinar image and logotype, topic image, video poster) **now reject SVG**: safer (SVG can carry scripts) and none of our seeds or tests upload SVG there. Avatars use `mimes:…,svg` and are unchanged |
| `firebase/php-jwt` 7: HS256 key ≥ 32 bytes | `jitsi/config/jitsi.php` (comment), `webinar/tests/TestCase.php` | self-hosted Jitsi tokens fail with "Provided key is too short" when `JITSI_APP_SECRET` is shorter (the default `Test` is). Tests set a 32-byte secret. JaaS (RS256) is unaffected |
| PHPUnit 11: doc-comment metadata deprecated | 62 test files | `@test` (67), `@dataProvider` (63), `@depends` (1), `@group` (1) → attributes (`#[Test]`, `#[DataProvider]`, …); this is also the PHPUnit 12 work planned for step 4 |
| PHPUnit 11: string data-set keys are passed as named arguments (unknown name = error; PHPUnit 10 ignored the keys) | `bookmarks_notes` (6 tests), `cart` `ProductApiTest`/`AdminProductApiTest`, `cmi5` `Cmi5ApiUploadTest`, `tasks` `TaskCreateApiTest`/`TaskIndexApiTest`, `reports` `ExportStatsTest`, `topic-type-gift` `GiftQuestionServiceTest`, `topic-types` `TopicTypeH5PTest` | keys renamed to the parameter names (or the missing parameter added). Together with the provider fixes below, the 48 data sets that were not collected at all in the first L12 run are back (cart 24, tasks 8, bookmarks 8, bulk-notifications 6, lrs 2) |
| PHPUnit 11: data providers must be `public static` and must not use `$this` | `cart` `invalidSubscriptionDataProvider` (private), `bulk-notifications/tests/TestCase.php` `channelDataProvider` (protected), `lrs` `tokenDataProvider`, `tasks`/`bookmarks_notes` `orderDataProvider` | made `public static`; closures use `self::assert…` |
| Testbench 10 checks error/exception handlers after each test | `mailerlite/tests/Api/SettingsTest.php` | `tearDown()` now calls `parent::tearDown()` (5 risky tests) |
| Flaky test (B.12) | `cart` `test_update_product_subscription_type_cannot_update_subscription_fields` | the update now always changes `subscription_duration` (random factory data could equal the stored values); 8/8 reruns pass, so no CI quarantine entry |
| Undefined row order in the "finished topics" export (pre-existing, flaky on every version, fails more often now) | `reports/src/Stats/Course/FinishedTopics.php` | rows ordered by user id, then topic id: export columns follow topic creation order and users their id, as the test expects; removed from the CI quarantine |
| PHPUnit config | `phpunit.xml`, `docker/envs/phpunit.xml.{postgres,mysql,cc}` | schema URL 10.5 → 11.5 (`--migrate-configuration` reports nothing else to migrate) |

Checked and not needed: `HasUuids` (no model uses it; `Str::uuid()` stays v4), `Schema::getTables/getViews/getTypes`
and schema-qualified `hasTable` (none; `h5p.contents` is reached through the model table name), `mergeIfMissing`,
`Str::is`, `Concurrency`, `DatabaseTokenRepository`, `new Blueprint`/`Grammar` constructors (none),
`local` disk root (`config/filesystems.php` defines `local` explicitly; Testbench's default was already
`app/private` on L11), Carbon 3 `diffIn*` (the same 5 call sites as B.12, all `(int) abs(…)`), week start
(`startOfWeek`/`endOfWeek` unused), container defaults for nullable class parameters (L12 now injects the default `null`:
`CurlApi`, `DateRange`, `AbstractUsersStats`, `TemplateValidContentRule` all handle `null`).

Verification:

- **Schema**: `migrate:fresh` (+ courses test migration) on an empty DB on L11 and L12, `pg_dump --schema-only`
  (`api/storage/l12up/schema_l11.sql`, `schema_l12.sql`, `schema.diff`): **identical**; row counts after the
  migration-time seeds identical in all 128 tables (`rows_l11.txt`, `rows_l12.txt`; script `schema.sh`).
- **Tests**: full `./vendor/bin/phpunit` per suite on a freshly prepared PostgreSQL DB (`test_l12`, scripts in
  `api/storage/l12up/`), compared with the L11 run of B.12.

  | Suite | L11 (tests / failing) | L12 final |
  |---|---|---|
  | Integrations | 4 / 2 | 4 / 2 (same `EventApiTest` list/order) |
  | auth | 124 / 3 | 124 / 3 (same `UserApiTest`) |
  | bulk-notifications | 44 / 3 | 44 / 3 (same multicast tests) |
  | core | 62 / 6 | 62 / 6 (same paginate/count tests) |
  | cart | 113 / 1 | 113 / 0 (subscription test made deterministic) |
  | categories | 26 / 0 | 27 / 0 (new SVG icon test) |
  | other 40 suites | 1 851 / 0 | 1 851 / 0 |
  | **Total** | **2 224 / 15** | **2 225 / 14**, no new failures, 0 PHPUnit deprecations |

  The first L12 run had 122 failing tests and 48 data sets not collected: data-set keys and provider visibility
  (PHPUnit 11), 3 `consultations` (scalar resources), 2 `templates-email` (`setTimezone(null)`), 1 `webinar` (Jitsi key
  length). `reports` `ExportStatsTest::testFinishedTopicsSheets` failed in 2 of 2 full L12 runs: the
  `FinishedTopics` query had no `ORDER BY`, so user rows and topic columns came in plan order. It now orders by user
  id and topic id (`reports/src/Stats/Course/FinishedTopics.php`); 7/7 suite reruns pass and the test is removed from
  the CI quarantine in `.github/workflows/ci.yml`.

- `php artisan about`: Laravel 12.69.3, PHP 8.3.35; `route:list`: **526** routes, method/URI list identical to L11;
  `schedule:list`: 11 entries; `l5-swagger:generate`: OK (311 paths); `composer validate`: valid (only the old warning on
  the exact `davidbadura/faker-markdown-generator` pin); `composer audit`: no advisories.
- Platform `migrate`: nothing to migrate. `ulams:tenant:sync-env --migrate`: coffee, nightsky, oncall synced, 0 pending.
- Horizon restarted on the new code (`horizon:status` running), php-fpm reloaded; `queue:work --once` (platform and
  `--domain=coffee.localhost`) OK.
- HTTP smoke: `GET /api/name` 200 and `GET /api/courses` 200 on `api.localhost` (5), `coffee.localhost` (1),
  `oncall.localhost` (1); login `admin@ulams.app` → `/api/profile/me` 200; tenant login `admin@coffee.ulams.app` 200,
  `/api/profile/me` 200, the coffee token is rejected on `api.localhost` (401); H5P service `show(3)`/`download(3)` OK;
  `GET /api/admin/templates/4/preview` 200; `POST /api/admin/pdfs/preview` with the CourseFinished pdfme template returns
  a PDF; SCORM: `GET /api/scorm/play/{uuid}` 200 (player HTML), `GET /api/scorm/show/{uuid}` 200,
  `POST /api/scorm/track/{uuid}` 200 and `GET /api/scorm/track/1/cmi.core.lesson_location` returns the stored value
  (vendored `Peopleaps\Scorm` models and Carbon 3 `latest_date` written).

Risks and follow-ups for step 4 (PHP 8.4 + L13):

- **Jitsi secret length** (deploy): set `JITSI_APP_SECRET` to ≥ 32 bytes on every self-hosted Jitsi tenant (and in the
  Jitsi/Prosody config), otherwise `generate-jitsi` returns 500. No tenant env sets it today (default `Test`).
- **SVG in `image` rules**: only category icons accept SVG now. If editors upload SVG course posters or webinar logos,
  add `allow_svg` per field (cheap) or sanitise SVG on upload.
- **L13 blockers unchanged**: `rennokki/laravel-eloquent-query-cache` 3.6, `treestoneit/shopping-cart` 1.6.1 and
  `gnello/laravel-mattermost-driver` 1.3.3 all stop at L12 (B.2); Passport 13 data migration (B.4).
- **PHPUnit 12**: doc-comment metadata is gone already; remaining runner warnings are the two helper classes named
  `*Test` (`topic-types/tests/Helpers/MarkdownTest.php`, `webinar/tests/Mocks/MockTest.php`), which make those two
  suites exit 1 as on L11.
- **Scalar resources**: any new `JsonResource` whose `toArray()` returns a scalar needs the same `resolve()` override
  (or should not be a resource).
- The vendored scorm package is now our code: upstream has no Carbon 3 release, so no further syncing is expected.
