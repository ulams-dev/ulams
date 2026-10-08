# Phase 0.1 audit report

Date: 2026-10-08 · Branch: `phase-0/foundation` · Spec: [`docs/ROADMAP-PROMPT.md`](../ROADMAP-PROMPT.md) §0.1 ·
Upgrade plan: [`docs/plans/phase-0.md`](../plans/phase-0.md)

Paths are relative to the repository root; `pkg/X` means `api/packages/X`. "(inference)" marks conclusions drawn from
reading code, not from running it. Status values: **done** (answered and acted on where needed), **partial** (answered,
follow-up work open), **open** (not yet answered or acted on).

## Summary

| # | Spec bullet (0.1) | Status | One-line answer |
|---|---|---|---|
| 1 | Repo map, packages, versions, course → lesson → topic, topic types | done | 48 vendored (50 imported, minus headless-h5p and recommender) + 2 own PHP packages (`h5p`, `tenancy`); 12 topic types via a class registry; topics keyed by FQCN morph |
| 2 | Module report (headless-h5p … notifications) | done | §2; tracker no longer exists, reports schedule nothing, LRS/SCORM/H5P are separate silos |
| 3 | Recommender | done | Removed (ADR 0006); nothing depends on it; **front still captures webcam frames** |
| 4 | Multitenancy | partial | DB + bucket + env file per tenant; dynamic tenants work in dev via CLI; production edge and isolation gaps |
| 5 | Learner activity data inventory | done | 6+ stores, mostly "latest state only", no retention, no event log except `notifications` |
| 6 | How content updates preserve progress | done | No mechanism beyond stable `topic_id`; type change, delete, clone, import lose data |
| 7 | Tests, CI, code style, queues, storage, AI code | partial | Postgres-backed PHPUnit on Testbench; **no CI runs**; no PHP static analysis; no AI code |
| 8 | Licence audit | partial | `LICENSING.md`; GPL linked in API (trax2, laravel-invoices), AGPL services (ReportBro, MinIO, Soketi) |
| 9 | Runtime dependency inventory | done | §9 |
| 10 | Commerce audit + Sylius | done | §10; **critical: payment callback lets anyone mark a Stripe payment paid** |

### Findings that need action before any new feature work

| Severity | Finding | Ref |
|---|---|---|
| Critical | Public `ANY /api/payments-gateways/callback/{payment}`; `StripeDriver::callback()` returns `new CallbackResponse()` whose default is `success=true` → payment PAID → `PaymentSuccess` → order PAID → access granted. Any user can get paid content by calling it with their pending payment id (inference: code path verified, not exploited) | `pkg/payments/src/routes.php:10`, `pkg/payments/src/Gateway/Drivers/StripeDriver.php:55-58`, `pkg/payments/src/Gateway/Responses/CallbackResponse.php:11`, `pkg/payments/src/Entities/PaymentProcessor.php:133-164,188-192` |
| Critical | RevenueCat driver enabled by default and selectable by the client; `purchase()` always succeeds | `pkg/payments/src/config.php:29-31`, `pkg/payments/src/Gateway/Drivers/RevenueCatDriver.php:16-19`, `pkg/cart/src/Http/Requests/PaymentRequest.php:47-60` |
| High | Front captures a webcam frame every 1 s for students in consultations (consent-gated) and uploads to S3 via **unauthenticated** signed-URL endpoints; leftover of the recommender pipeline, contradicts ADR 0006 | `front/src/utils/constants.ts:2`, `front/src/components/Consultations/ConsultationCard/JitsyMeeting/index.tsx:109-145`, `front/src/workers/saveImageWorker.ts:72-108`, `pkg/consultations/src/routes.php:32-33`, `pkg/webinar/src/routes.php:27` |
| High | Jitsi webhook `POST api/jitsi/recorded-video` is unauthenticated (only `appId == 'meet-id'` default) and downloads a URL from the request → SSRF / arbitrary write (inference) | `pkg/jitsi/src/routes.php:6`, `pkg/jitsi/config/jitsi.php:7-9` |
| High | LRS guard decodes the JWT payload without verifying the signature, then looks up `jti` | `pkg/lrs/src/Extensions/AccessTokenGuard.php:78-106` |
| High | Course list query leaks other users' courses (`orWhereDate` without grouping) | `pkg/course-access/src/Services/CourseAccessService.php:64-73` |
| Medium | Client can override currency; `payProduct` skips `purchasable`/limits; client-set `has_trial` | `pkg/payments/src/Entities/PaymentProcessor.php:105-109,222-227`, `pkg/cart/src/Services/ShopService.php:67-87` |
| Medium | Tenant video jobs never consumed (queue/connection mismatch) | §4, §7 |
| Medium | No CI runs in the monorepo (workflows not in root `.github/`) | §7 |

Recommendation: fix the two critical payment issues now as a hotfix on the current stack (disable the Stripe/Free/
RevenueCat callback success path, verify server-side), independent of the Sylius plan. Remove the webcam capture and
lock the Jitsi webhook in the same pass. These were found by reading code; none was exploited.

---

## 1. Repository, packages, versions, content model

### Findings

**Monorepo layout** (ADRs `docs/decisions/0001`, `0005`):

| Path | What | Stack |
|---|---|---|
| `api/` | Laravel REST API | Laravel 9.52.21, PHP 8.3.23 (`escolalms/php:8.3-alpine`), `gecche/laravel-multidomain` 5.0, Passport 11, Horizon 5 |
| `api/packages/*` | 48 former `escolalms/*` packages vendored as source (namespace `Ulams\`) + `h5p` and `tenancy` written here | versions in `api/packages/versions.json`, provenance in `api/packages/README.md` |
| `api/h5p` | H5P service (GPL), Lumi `@lumieducation/h5p-server` 10.0.4, Express 5, Node 22 | `api/h5p/package.json` |
| `admin/` | Admin SPA | umi max 4, antd 5, React 18; JS libs in `admin/src/lib` |
| `front/` | Learner SPA | Vite 5, React 18; JS libs (SDK, components, scorm-player) in `front/src/lib` |
| root | Yarn 1 workspaces + Turborepo (`turbo.json`) | husky + lint-staged |

Packages are not composer-installed: PSR-4 maps are merged into `api/composer.json`, providers are listed in
`api/config/app.php`, test suites in `api/phpunit.xml` (46 suites). Third-party PHP dependencies: see the plan §B.2
(`composer outdated --direct` output).

**Course → lesson → topic** (`pkg/courses`):

- `courses`: content fields, `status` (`draft`, `published`, `archived`, `published_unactivated`;
  `pkg/courses/src/Enum/CourseStatusEnum.php:9-12`), `active_from/to`, `hours_to_complete`, `findable`, `public`,
  `fields` JSON, `scorm_sco_id`. Authors via `course_author` pivot. No price fields (moved to cart products,
  `pkg/courses/database/migrations/2022_03_16_133941_drop_base_price_and_purchasable_from_courses_table.php`).
- `lessons`: `course_id`, `order`, `active`, `active_from/to`, **nested** via `parent_lesson_id`
  (`…/2023_02_07_142600_add_column_parent_lesson_id_to_lessons_table.php:13`); `Lesson::isActive()` walks parents
  (`pkg/courses/src/Models/Lesson.php:171-185`).
- `topics`: `lesson_id`, `order`, `active`, `preview`, `can_skip`, `summary`, `introduction`, `description`, `duration`,
  `json`, polymorphic `topicable_type`/`topicable_id` (`pkg/courses/src/Models/Topic.php:178-181`). No SoftDeletes.
  `topic_resources` for attachments.
- Scheduling: `ActivateCourseJob` daily, `CheckForDeadlines` hourly (`pkg/courses/src/ScheduleServiceProvider.php:15-16`).

**Topic types**: registered with `Topic::registerContentClass()` into `TopicRepository::$contentClasses`
(`pkg/courses/src/Repositories/TopicRepository.php:81-123`); listed by `GET /api/admin/topics/types`.
`topicable_type` stores the **fully qualified class name** — no `morphMap` (the rename migration
`api/database/migrations/2026_10_08_120000_rename_legacy_identifiers_to_ulams.php` had to rewrite them).

| Type | Table | Registered in |
|---|---|---|
| Audio, Video, Image, RichText, H5P, OEmbed, PDF, ScormSco | `topic_audios`, `topic_videos`, `topic_images`, `topic_richtexts`, `topic_h5ps` (`value` = `h5p.contents.id`), `topic_oembeds`, `topic_pdfs`, `topic_scorm_scos` | `pkg/topic-types/src/UlamsTopicTypesServiceProvider.php:74-83` |
| Cmi5Au | `topic_cmi5_aus` | same file `:124-133` (only if cmi5 is installed) |
| Video (HLS-processed subclass) | `topic_videos` | `pkg/video/src/UlamsVideoServiceProvider.php:37` |
| GiftQuiz | `topic_gift_quizzes` | `pkg/topic-type-gift/src/UlamsTopicTypeGiftServiceProvider.php:59-64` |
| Project | `topic_projects` | `pkg/topic-type-project/src/UlamsTopicTypeProjectServiceProvider.php:39-40` |

YouTube, Jitsi and Pencil Spaces are webinar/consultation integrations, not topic types.

**Creation via API** (`pkg/courses/src/routes.php`): `resource('courses')`, `resource('lessons')`,
`resource('topics')`, `POST topics/{topic}` (multipart update), `POST courses/sort`, `POST lessons/{id}/clone`,
`POST topics/{id}/clone`. Topic create: `CreateTopicAPIRequest` → `TopicRepository::createFromRequest`
(`:187-210`) → instantiates `topicable_type`, validates with the class's `rules()`, stores uploads, associates
(`:243-277`). The demo seeders (uncommitted, `api/database/seeds/Demo/`) use the same path
(`Demo/Support/TopicFactoryHelper.php:29-31`).

### Gaps
- FQCN morph types: every namespace change is a data migration; add an enforced `morphMap` with stable aliases.
- `Course::scopeActive` compares dates the wrong way round (`pkg/courses/src/Models/Course.php:429-439`) (inference).
- Topic move (`lesson_id` update) is not restricted to the same course (`Topic.php:112-126,157`) (inference).
- No content versioning anywhere (relevant for Living Course).

### Recommendations
- Introduce `Relation::enforceMorphMap()` before Phase 1 adds new topic types.
- Phase 1 formats (LiaScript, Adapt, LTI) follow the `registerContentClass` + topicable table pattern.
- The `courses-import-export` `content.json` (`pkg/courses-import-export/src/Services/ExportImportService.php:70-155`)
  is the only full course serialisation; candidate base for the AI builder / Git workflow format (inference).

---

## 2. Module report

| Module | What it actually does | Key refs | Gaps / notes for later phases |
|---|---|---|---|
| `headless-h5p` (removed in `9a97aaa2`) | In-process PHP H5P server on `h5p/h5p-core` + `h5p-editor` 1.24 (GPL); 28 `api/admin/hh5p/*` + public `api/hh5p/content/{uuid}` routes; `hh5p_*` tables; `h5p:seed` pulled from h5p.org; no resume state; editor returned the bearer token in JSON | `git show 9a97aaa2^:api/packages/headless-h5p/src/routes.php`, `…/src/Http/Controllers/EditorApiController.php:22-27` | Replaced; done |
| `h5p` (new, MIT) | Read-only index of the service's `h5p.contents`; `GET api/admin/h5p/contents`, `DELETE api/admin/h5p/unused`; `H5PServiceClient` (upload/download/delete/show, `X-Internal-Token`, `X-Forwarded-Host`) | `pkg/h5p/src/routes.php:8-13`, `pkg/h5p/src/Services/H5PServiceClient.php:16-120` | Migration is PostgreSQL-only (`…/2026_10_08_000000_create_h5p_contents_table.php:24-50`); `.env.example:66` leftover `H5P_AGGREGATE_ASSETS` |
| `api/h5p` service (GPL) | Lumi player/editor, REST `/contents`, iframe `/embed/play|edit/:id`, per-tenant via `.env.<host>` files, Postgres schema `h5p` (`contents`, `content_user_data`, `finished_data`), S3 per tenant, shared libraries | `api/h5p/README.md`, `api/h5p/src/routes/contents.ts:88-297`, `api/h5p/src/tenancy/EnvFileTenantResolver.ts` | Per-tenant internal token, library admin limited to platform, idle-tenant eviction (TODO 1.4); Laravel never reads `finished_data` |
| `scorm` | Upload/parse SCORM 1.2/2004 zips (`devianl2/laravel-scorm`), public `play/{uuid}`, `show`, `zip` routes, authenticated `track` routes writing `scorm_sco_tracking` | `pkg/scorm/src/routes.php:9-26`, `pkg/scorm/src/Services/ScormService.php:15-21` | Player/zip public by uuid; tracking not emitted as xAPI; dependency blocks Laravel 12 (plan) |
| `cmi5` | Upload/parse cmi5 packages into `cmi5s`, `cmi5_aus`; `GET api/cmi5/player/{auId}` | `pkg/cmi5/src/routes.php:7-17`, `pkg/cmi5/src/Services/Cmi5UploadService.php:25-65` | No admin screen; no import/export strategy (`ExportImportService.php:44-47`); launch fails until LRS is seeded by hand (inference) |
| `lrs` | Embeds the Trax2 LRS (`trax2/framework`, GPL) in-process: `trax_xapi_*` tables, xAPI endpoint `trax/api/{access}/xapi/std`, cmi5 launch params (`GET api/cmi5/courses/{id}`), admin statement list | `pkg/lrs/src/UlamsLrsServiceProvider.php:30-55`, `pkg/lrs/src/Services/LrsService.php:15-102` | JWT signature not verified (`AccessTokenGuard.php:78-106`); seeded creds `Ulams/Ulams`, CORS `*` (`database/seeders/LrsSeeder.php:17-50`); `/lrs/{any?}` uses undefined `auth:token` guard; actor homePage hard-coded `https://ulams.app`; GPL licence |
| `tracker` | **Does not exist.** Added as `escolalms/tracker` in `95ea9d0e` (2022), removed from composer in `de92b1ea` (2024-12-04); not vendored. Logged API requests per user (inference) | leftovers: `api/config/l5-swagger.php:61`, `api/phpunit.xml:41,166-167`, `api/database/seeds/PermissionsSeeder.php:39,84-85`, `pkg/video/src/routes.php:3` | Admin "Logs" screen calls non-existent `GET /api/admin/tracks/routes` (`admin/src/services/ulams/tracker.ts:17-18`, `admin/config/routes.ts:437-443`, widget in `admin/src/pages/Users/User/index.tsx:107`) |
| `reports` | Metrics (`reports`, `measurements`) and on-demand stats for courses, topics, cart, date ranges; XLSX export/import of finished topics | `pkg/reports/src/config.php:7-101`, `pkg/reports/src/routes.php:8-23` | All 9 metrics `history=false` → **nothing is scheduled**; `ActiveUsers` = users with any DB notification; reports count only `PAID`, not `TRIAL_PAID`; XLSX import writes `course_progress` |
| `payments`, `cart`, `vouchers`, `invoices` | See §10 | | |
| `translations` | `language_lines` (spatie translation loader), admin CRUD, public `GET api/translations` | `pkg/translations/src/routes.php`, `pkg/translations/config/config.php:6` | Languages `en`, `pl` only, admin also ships `fr-FR` |
| `settings` | `settings` + `config` tables; `AdministrableConfig::registerConfig()` used by ~27 packages; cached forever in `ulams_config_cache`; public `api/settings`, `api/config` | `pkg/settings/src/Services/AdministrableConfigService.php:19-23,167-169` | Tenant isolation relies on the tenant cache prefix (`pkg/tenancy/src/Support/TenantNaming.php:94`) |
| `templates` (+ `templates-email`, `-pdf`, `-sms`) | Wildcard `Event::listen('Ulams*')` → `TemplateEventListener`; channels: MJML email (`qferr/mjml-php`), PDF via ReportBro, SMS via `tzsk/sms` | `pkg/templates/src/UlamsTemplatesServiceProvider.php:40-42`, `pkg/templates-email/src/Services/MjmlService.php:17-31`, `pkg/templates-pdf/src/Services/ReportBroService.php:16-28` | ReportBro default is the **external SaaS** `https://www.reportbro.com/report/run` (`pkg/templates-pdf/src/config.php:4`), registered as public config; ReportBro → pdfme planned |
| `notifications` (+ `bulk-notifications`) | Wildcard `Ulams*` listener stores every user-bearing event as a DB notification (only Login/Logout excluded from notifying, but Login is still the last-login source); bulk push via Firebase | `pkg/notifications/src/UlamsNotificationsServiceProvider.php:33-35`, `pkg/bulk-notifications/src/Channels/PushNotificationChannel.php:11-25` | De facto event log with no retention; usable as a backfill source for Learner Insights |

Other modules, briefly: `courses-import-export` (clone = export + import, strategies only for H5P and SCORM;
`RichTextTopicTypeStrategy` imported but missing, `ExportImportService.php:18`), `questionnaire` (ratings used by
reports), `webinar`/`consultations`/`stationary-events` (enrolment tables, reminder jobs), `mattermost`, `mailerlite`,
`jitsi`, `pencil-spaces`, `youtube`, `video` — see §9 for their runtime footprint.

### Status / recommendations
- Done as a report. Follow-ups to add to the TODO: remove the admin Logs screen and tracker leftovers; decide the LRS
  future (fix guard now; replace Trax for licence reasons, e.g. an LRS as a separate service, before Phase 4);
  change the ReportBro default away from the SaaS until pdfme lands.

---

## 3. Recommender

### Findings
- Removed from the API in `9fbded1a` (108 files, −6573 lines; drop migration
  `api/database/migrations/2026_10_08_130000_drop_recommender_tables.php` drops `aggregated_frames`, `term_analytics`,
  `meet_recording_screens`, `meet_recordings`) and from admin/front in `ec4f0965` (38 files). ADR
  `docs/decisions/0006-remove-recommender.md`.
- What it did (`git show 9fbded1a^:api/packages/recommender/…`): POSTed course/topic datasets to an external ML
  service for "completion of course" and "match topic type" recommendations; processed meeting recordings into
  emotion/attention frames and satisfaction scores; scheduled `RebuildTermAnalyticJob` every 15 min.
- What depended on it: admin program-editor recommendation panel, consultation/webinar "effectiveness analysis"
  screens, front `MeetingAnalyticsOverlay`, settings listener on `SettingPackageConfigUpdated`. No other PHP package.
  Grep for "recommender" in config/app/packages/admin/front now finds only the drop migration.

### Gap
- **The webcam frame capture survived the removal** (top-findings table). It still uploads one frame per second
  during consultations for consenting students, through unauthenticated endpoints.

### Status: done (removal), with one open follow-up
- Recommendation: remove `startScreenshotFlows`, `saveImageWorker.ts`, the `save-screen`/`signed-screen-urls`
  endpoints in `consultations` and `webinar`, and delete stored frames; tick the TODO item after that.

---

## 4. Multitenancy

### Findings
- **Wiring**: `api/bootstrap/app.php:14-22` creates `Gecche\Multidomain\Foundation\Application`; host from
  `X-Forwarded-Host` else `Host`; console via `--domain=`. Kernels extend gecche's (`api/app/Http/Kernel.php:6`,
  `api/app/Console/Kernel.php:10`); queue provider `api/config/app.php:160`.
- **Per tenant**: env file `api/.env.<host>` (gitignored), PostgreSQL role + database `ulams_<slug>`, S3 bucket
  `ulams-<slug>`, Redis prefix `ulams_<slug>_`, storage dir `storage/<host>/` (Passport keys, caches, logs), H5P via
  the same env files. Registry of hosts: `api/config/domain.php` (tracked in git, rewritten at runtime; currently dirty
  with 3 demo tenants).
- **`pkg/tenancy`** (`82bfd675`): `ulams:tenant:create|list|delete|sync-env|seed-demo`; resumable provisioning steps
  database → bucket → env → migrate → passport keys → client → permissions → demo
  (`pkg/tenancy/src/Services/TenantProvisioner.php:21-30,114-139`); `tenants` table with encrypted secrets;
  `RejectUnknownHost` middleware returns 404 for unknown hosts (`pkg/tenancy/src/Http/Middleware/RejectUnknownHost.php:21-39`).
- **Workers** (`9c2a6f58`): `api/queue.sh`, `api/broadcast.sh`, `api/scheduler.sh` re-read `api/domains.sh` each
  pass, so new tenants are picked up without restart. Horizon is platform-only.
- **H5P** (`f101917e`, `95875445`): service resolves tenants from the same env files, hot-reloads on change.

### Can it support dynamically created subdomains?
Yes in development, by CLI, without restarts (Caddy wildcards `*.localhost`, `*.app.localhost`, `*.admin.localhost`
in `api/docker/conf/Caddyfile`). Not production-ready:

| Gap | Ref / note |
|---|---|
| Provisioning is CLI only, synchronous, 900 s timeout; no API/admin/self-serve | `pkg/tenancy/src/Console/*` |
| No production edge config (wildcard DNS, on-demand or wildcard TLS); host patterns default to `*.localhost` | `api/docker/conf/Caddyfile` is dev-only |
| `config/domain.php` + `.env.<host>` + `storage/<host>` are per-container files; multiple replicas need `sync-env`; `config:cache` would freeze the list (inference) | `pkg/tenancy/src/Console/SyncTenantEnvCommand.php` |
| Tenant video jobs never consumed: `ProcessVideo` uses `redis-long-job`/`queue-long-job`, tenant loops use `default,broadcast,video` on the default connection (inference) | `pkg/video/src/config.php:18-19`, `api/queue.sh:6-12` |
| Worker loops boot artisan once per tenant per pass (linear cost); scheduler starts N processes per minute, no overlap guard | `api/queue.sh`, `api/scheduler.sh` |
| Isolation: shared MinIO credentials, **public-read** buckets (`pkg/tenancy/src/Services/S3BucketProvisioner.php:29-58`), shared Redis server, tenants inherit all platform `.env` secrets via the stub, shared H5P libraries and internal token | |
| Tenant chosen from `X-Forwarded-Host`: safe only behind a proxy that overwrites it | `api/bootstrap/app.php:16` |
| H5P and Laravel decide "known tenant" differently (env file vs env file + `domain.php`) | |
| Docs drift: `api/docs/multidomain.md:54` says H5P is single-tenant; ADR `api/docs/adr/0013` predates `tenancy`; no ADR for `tenancy` | |
| No per-endpoint tenant isolation tests (quality bar); integration test is opt-in (`TENANCY_INTEGRATION=1`) | `pkg/tenancy/tests/Integration/TenantIsolationTest.php:18-39` |

### Status: partial
### Recommendations
- Write ADR 0007 "tenancy package on top of gecche" (current state) and decide whether `tenancy` should own env
  resolution later (removes the mutable `config/domain.php`).
- Fix the video queue mismatch; make buckets private with signed URLs; per-tenant MinIO/H5P credentials.
- Answer the open decision "one deployment, tenant per subdomain for the POC" with this table.

---

## 5. Learner activity data inventory (input for Learner Insights, Phase 4)

| Source | Storage (table / columns) | Granularity | Written by | Retention | Gaps |
|---|---|---|---|---|---|
| Course progress | `course_progress` (`user_id`, `topic_id`, `status` 0/1/2, `started_at`, `finished_at`, `seconds`, `attempt`); FKs cascade on user and topic (`pkg/courses/database/migrations/2021_05_04_084059_create_course_progress_table.php:24-25`) | 1 row per user × topic, **latest state only** | `PATCH /api/courses/progress/{course}` → `ProgressService::update` → `CourseProgressRepository::updateInTopic` (`:45-83`); lazily created INCOMPLETE rows on read (`ValueObjects/CourseProgressCollection.php:69-97`); XLSX import in reports | none (deleted with topic) | No history; no unique index (`updateOrCreate` only) |
| Time on topic | `course_progress.seconds` (cumulative); `user_topic_times` last-ping marker (no FK) | per user × topic; pings within 60 min | `PUT /api/courses/progress/{topic}/ping` (`CourseProgressCollection.php:99-125`) | none | Gaps > 60 min dropped; `updateUserTimeInApp` never called |
| Daily attendance | `course_user_attendances` (`course_progress_id` cascade, `attendance_date`, `attempt`, `seconds`) | snapshot per progress update | `CourseProgressRepository.php:76-82` | none | Closest thing to a time series |
| Enrolment / completion | `course_user` (`finished`, `deadline`, `end_date`), `course_group` | per user × course | course-access, cart grants, `ProgressService` | cascade on course | Group enrolment has no per-user row |
| H5P (Laravel) | `h5p_user_progress` (`topic_id`, `user_id`, `event`, `data` JSON; no FKs) | **last statement per verb** per user × topic | `POST /api/courses/progress/{topic}/h5p` from the iframe `ulams-h5p:xapi` message (`front/src/lib/sdk/services/courses.ts:235-243`) | none, orphaned on delete | Lossy; model imports the *test* User class (`pkg/courses/src/Models/H5PUserProgress.php:5`) |
| H5P (service) | `h5p.content_user_data` (resume state), `h5p.finished_data` (`score`, `max_score`, `opened/finished_timestamp`, `completion_time`) (`api/h5p/src/db/migrate.ts:42-73`) | per content × user; finished data **overwritten** per attempt | Lumi, every 5 s (`H5P_STATE_SAVE_INTERVAL_MS`) | cascades on content delete | **Laravel never reads it** |
| xAPI / cmi5 | `trax_xapi_statements` (+ `_activities`, `_agents`, `_states`, …) | per statement (full xAPI) | Trax LRS endpoint; cmi5 AUs | none | Actor = email, hard-coded homePage; not linked to `course_progress`; only cmi5 uses it |
| SCORM CMI | `scorm_sco_tracking` (`progression`, `score_*`, `lesson_status`, `completion_status`, `session_time`, `total_time_*`, `suspend_data`, `details`, …) (`pkg/scorm/database/migrations/2021_07_21_110218_create_scorm_tables.php:56-84`) | per user × SCO (not per topic) | `POST /api/scorm/track/{uuid}` | none | Latest state only; not xAPI |
| Quizzes (GIFT) | `topic_gift_quiz_attempts` (`started_at`, `end_at`, `tutor_feedback`), `topic_gift_attempt_answers` (`answer`, `score`, `feedback`, `graded_at`) | per attempt / per answer | `POST /api/quiz-attempts`, `/quiz-answers`, `MarkAttemptAsEnded` | cascade on quiz and **on question** | Keyed by quiz (topicable), not topic; editing questions deletes answer history |
| Projects | `topic_project_solutions` (`score`, `graded_by`, `graded_at`, `tutor_feedback`) | per submission | project endpoints | cascade on topic | |
| Questionnaires | `question_answers` (`rate`, `note`) | per answer | questionnaire API | none | |
| Logins | **no table**; `Login` event stored in `notifications`; last login = `MAX(notifications.created_at)` (`pkg/auth/src/Repositories/Criteria/LastLoginCriterion.php`); `oauth_access_tokens` as proxy | per login | `pkg/auth/src/Services/UserService.php:154` | none | No failed-login or session data |
| Event log | `notifications` (`event`, `data`, `notifiable`) for every `Ulams*` event with a user | per event | wildcard listener | none | Usable for backfill; also drives "active users" |
| Consultations, webinars, events, tasks, bookmarks | `consultation_user_terms` (`executed_at`, `finished_at`), `webinar_user` (enrolment only), `stationary_event_users`, `tasks`, `bookmarks` | per item | respective packages | none | No webinar attendance |
| Tracker (API request log) | **none** (package removed 2024) | — | — | — | — |

**Events available** for a Learner Insights event stream: `TopicFinished` (first completion only), `LessonFinished`
(bug: child-lesson topics ignored, `pkg/courses/src/Jobs/CheckFinishedLessons.php:72`), `CourseStarted`,
`CourseFinished` (also fired on **unassignment**, `pkg/course-access/src/Services/CourseAccessService.php:114`),
`CourseAssigned`, `CourseDeadlineSoon`, `QuizAttempt*`, `ProjectSolution*`, `TopicTypeChanged`, `Login`, `Logout`.

**Retention**: no `Prunable` models, no `model:prune`, no purge jobs; data lives until cascades delete it.

### Status: done
### Recommendations (Phase 4 input)
- One append-only learner event store (xAPI-shaped) fed by listeners on the events above plus adapters for SCORM
  tracking, H5P `finished_data` and the LRS; keep source tables as they are.
- Define retention and GDPR export/erase per store (users are soft-deleted, so FK cascades do not fire).

---

## 6. How content updates preserve learner progress today

### Findings
There is **no explicit mechanism** (no content versions, no progress migration). Progress survives only because it is
keyed by `topic_id` and most edits keep that id.

| Operation | Effect on learner data | Ref |
|---|---|---|
| Edit topic, same type | Updated in place; progress kept | `pkg/courses/src/Repositories/TopicRepository.php:212-238,287-329` |
| Change topic type (or same type without content keys) | New topicable created, topic id kept → `course_progress` kept; **old topicable orphaned** and data keyed by it detached (quiz attempts, H5P `finished_data`, SCORM tracking) | `TopicRepository.php:216-228` |
| Edit H5P content in the editor | Same content id; Lumi deletes user states flagged `invalidate`, keeps `finished_data` (inference) | `api/h5p/src/storage/PgContentUserDataStorage.ts` |
| Deactivate topic | Excluded from completion; rows kept | `pkg/courses/src/ValueObjects/CourseProgressCollection.php:64-67,137-141` |
| Add topic | Existing learners get INCOMPLETE rows lazily; `course_user.finished` is unset if the course becomes unfinished | `CourseProgressCollection.php:69-97`, `pkg/courses/src/Services/ProgressService.php:97-129` |
| Delete topic | **Hard delete**; cascades `course_progress` → `course_user_attendances`, `topic_project_solutions`; `h5p_user_progress`, `user_topic_times` orphaned; topicable row kept | `TopicRepository.php:331-354` |
| Delete lesson / course | Recursive delete of topics as above | `LessonRepository.php:55-75`, `CourseRepository.php:314-339` |
| Reorder | `order` only; no effect | `pkg/courses/src/Services/CourseService.php:96-108` |
| Move topic / lesson | Progress follows the topic id; no same-course check (inference) | `Topic.php:112-126` |
| Clone topic/lesson/course, export/import | **New ids**; no learner data carried | `pkg/courses/src/Services/{LessonService,TopicService}.php`, `pkg/courses-import-export/src/Jobs/CloneCourse.php:41-53` |
| Edit GIFT question by recreating it | Cascade deletes historical answers | `topic_gift_attempt_answers` FK |

### Gaps
- Wellms' "progress preserved" claim holds only for in-place edits. Living Course (Phase 3) needs: stable element ids,
  content versions, a migration rule per change type (keep / reset / re-check), soft delete for topics, and
  attempt history keyed by version.

### Status: done (finding); design belongs to Phase 3.

---

## 7. Tests, CI, code style, queues, storage, AI code

| Area | Finding | Ref |
|---|---|---|
| PHP tests | PHPUnit 9.6, 46 suites (packages + `Integrations` + `tenancy`), run in the `api` container against **PostgreSQL** (`make test-phpunit`); package suites boot **Orchestra Testbench**, not the real app (no gecche, no tenancy wiring) | `api/phpunit.xml:52-203`, `pkg/core/tests/TestCase.php:8`, `api/makefile:6-7,19` |
| Baseline | Known failures: core 6, auth 3 (from earlier run in this session) | — |
| JS tests | front: Node test runner (`front/tests/*.test.ts`, uncommitted switch from Jest); admin: 1 Jest unit test, 12 Playwright specs; h5p: vitest | `front/package.json:94`, `admin/src/e2e/*.spec.ts`, `api/h5p/package.json:19` |
| CI | **Nothing runs**: workflows are in `api/.github`, `admin/.github`, `front/.github`; GitHub reads only root `.github/`. The API workflow tests PHP 8.2–8.4 on `postgres:12`; coverage and swagger jobs still use MariaDB/MySQL | `api/.github/workflows/phpunit-tests.yml:102-244` |
| PHP style / analysis | No php-cs-fixer config, no phpstan/larastan/psalm/pint; composer `pre-commit` hook runs `php-cs-fixer` which is not installed; deptrac config is a stub | `api/composer.json` `extra.hooks`, `api/deptrac.yaml` |
| JS style | husky + lint-staged (prettier/eslint for admin and front, skips `src/lib`); eslint bans GPL H5P imports; `front lint:gpl` | `.lintstagedrc.mjs`, `front/scripts/check-no-gpl-imports.cjs` |
| Queues | Redis/Valkey; connections `redis` and `redis-long-job`; Horizon only in single-domain mode (`supervisor-1` default, `supervisor-long-job`); tenants use bash `queue:work` loops | `api/config/queue.php:61-75`, `api/config/horizon.php:167-215`, `api/docker/conf/supervisor/services/*` |
| Scheduler | `scheduler.sh` per domain every 60 s; jobs: webinar/consultation reminders, video stuck detection, task overdue, course deadlines/activation, cart abandoned/renew/expire, report metrics (none enabled) | `api/app/Console/Kernel.php:31-43`, package `ScheduleServiceProvider`s |
| Storage | Disks `local`, `public`, `s3` (default s3/MinIO); per-tenant bucket, public-read policy; SCORM/cmi5/video disks configurable | `api/config/filesystems.php:16,44-77` |
| AI / LLM code | **None** (grep for openai/anthropic/llm/embedding/langchain/ollama in api, admin, front) | — |

### Status: partial
### Recommendations
- Move the API PHPUnit workflow to root `.github/workflows` with path filters (already a TODO item) before the
  upgrade; switch to `postgres:16`.
- Add Pint (or php-cs-fixer) and Larastan at a low level with a baseline, wired into lint-staged.
- Add an app-level smoke test (boots gecche + tenancy), since package suites cannot catch wiring breaks.

---

## 8. Licence audit (summary of `LICENSING.md`)

| Component | Licence | Open-core impact |
|---|---|---|
| Root, `docs/`, `front/`, `front/src/lib/*`, `api/packages/*` (incl. `h5p`, `tenancy`) | MIT | Fine for closed paid modules, SaaS, redistribution (keep notices) |
| `api/` application | Apache-2.0 (`api/LICENSE`; `api/composer.json` still says MIT — align) | Fine; NOTICE/patent clauses |
| `scorm` package | upstream licence mismatch (Apache vs MIT metadata) | Clarify; both permissive |
| `front/src/lib/scorm-player` | **no licence upstream** | Needs relicensing from authors or replacement |
| `admin/` | **undeclared** | Decide and add |
| `admin/src/lib/markdown-editor` | BSD-3-Clause | Ship notice |
| `api/h5p` (Lumi) | GPL-3.0-or-later | OK as separate service over HTTP/iframe (ADR 0003); source offer when distributed |
| `trax2/framework` (lrs) | GPL-3.0 **linked in the API** | Blocks closed distribution of the API; replace or isolate |
| `laraveldaily/laravel-invoices` | GPL-3.0-only **linked** | Being replaced now |
| `smalot/pdfparser` | LGPL-3.0 | Acceptable via composer (dynamic) |
| ReportBro (server, designer in admin) | AGPL-3.0 | Replace with pdfme (planned) |
| MinIO | AGPL-3.0, upstream archived | Separate process; replace default (SeaweedFS/RustFS/Garage) |
| Soketi | AGPL-3.0 | Effectively unused (`BROADCAST_DRIVER=log`, only `app/Events/TestBroadcast.php`); drop or Reverb after upgrade |
| Redis 8 | RSAL/SSPL/AGPL | **Replaced by Valkey 8 (BSD-3)** in `ba74ef3f` |
| ffmpeg, pngquant, gifsicle, jpegoptim in the PHP image | GPL builds | Separate processes; source offer when distributing the image |
| Sylius core (future) | MIT; Sylius Plus commercial | See §10 |

### Status: partial
Remaining: `admin/` licence, `scorm-player` licence, `api/composer.json` licence field, trax2 decision.

---

## 9. Runtime dependency inventory (input for Phase 8)

| Dependency | Image / version | Required? | Config | Notes |
|---|---|---|---|---|
| PHP-FPM API | `escolalms/php:8.3-alpine` (+ excimer in dev) | yes | `LARAVEL_*` → `.env` (`api/docker/envs/envs.php:41-54`) | Upstream-owned image (TODO: replace) |
| Caddy | `caddy` (unpinned) | yes | `api/docker/conf/Caddyfile` | Dev only; no production config |
| PostgreSQL | `postgres:12` (**EOL**) | yes | `DB_*`, `DB_ADMIN_*` (tenancy) | MySQL still referenced in config fallbacks and CI |
| Valkey | `valkey/valkey:8-alpine` | yes (cache, queue, health check, H5P locks) | `REDIS_*`, prefixes per tenant | Session uses cookies |
| Workers | supervisor: php-fpm, Horizon (platform), `queue.sh` ×3, `broadcast.sh`, `scheduler.sh` | yes | `DISABLE_*` toggles in `api/init.sh:16-53` | Long-job queue not served for tenants |
| H5P service | `ulams/h5p:dev` (Node 22) | if H5P used | `H5P_*`, `TENANCY_MODE=env-files` | Mounts `api/` read-only; H5P Hub fetch on by default |
| S3 storage | `bitnamilegacy/minio:latest` (AGPL, archived) | yes in default setup | `AWS_*`, `*_DISK` | Any S3 works |
| Mail | `mailhog/mailhog` (dev) | dev | `MAIL_*` (legacy `MAIL_DRIVER`) | |
| MJML | `danihodovic/mjml-server` or binary or mjml.io API | for MJML templates | `MJML_*` | Not on the compose network |
| ReportBro | `escolalms/reportbro-server:latest` (AGPL) / SaaS default | being removed | `REPORTBRO_URL` | Default sends data to reportbro.com |
| Soketi | `quay.io/soketi/soketi:latest` (AGPL) | **unused** | — | Remove or Reverb |
| ffmpeg | in PHP image | optional (`VIDEO_PROCESSING_ENABLE`) | `VIDEO_*` | |
| Jitsi / JaaS | external | optional | `JITSI_*`, `JAAS_*` | Insecure defaults |
| Pencil Spaces | external API | optional | `PENCIL_SPACES_*` | |
| YouTube Live | Google API | optional | lowercase env keys (`client_id`, …) | |
| Mattermost | external | optional | `MATTERMOST_*` | Driver connects in constructor (breaks `route:list`) |
| MailerLite | external (v2 SDK) | optional, off | admin settings | |
| Firebase push | external | optional | admin setting `push.service_account`; front `VITE_APP_FIREBASE_*` | Proprietary SaaS |
| Stripe | external | optional | `PAYMENTS_STRIPE_*` | Omnipay |
| Przelewy24 | external | optional | `PAYMENTS_PRZELEWY24_*` | vendored `pkg/przelewy24-php` |
| RevenueCat | external | optional, **enabled by default** | — | see top findings |
| SMS (Twilio etc.) | external | optional | `pkg/templates-sms/config/sms.php` | |
| Social login | external | optional | `FACEBOOK_*`, `GOOGLE_*` | |
| Sentry | external | optional | `SENTRY_*` | `.env.example:47` and front env files hard-code `sentry.etd24.pl` DSNs |
| Adminer | dev | no | | |

Phase 8 candidates: drop Soketi, ReportBro, MinIO-by-default, mailhog in prod; make H5P, ffmpeg, MJML optional
profiles; one PHP process model for tenants (Horizon or a tenant-aware worker) instead of bash loops; remove the
default third-party endpoints (ReportBro SaaS, Sentry DSNs, H5P Hub) for offline installs.

### Status: done

---

## 10. Commerce audit and Sylius 2.x

### What Wellms commerce does today

| Package | Function | Key refs |
|---|---|---|
| `cart` | Products (`single`, `bundle`, `subscription`, `subscription-all-in`; prices in minor units; `tax_rate`; trials; limits), productables registry (Course, Webinar, Consultation, StationaryEvent, Dictionary), cart (`treestoneit/shopping-cart`), orders (`PROCESSING`…`TRIAL_CANCELLED`), access grants, subscription renew/expire jobs | `pkg/cart/src/Enums/ProductType.php:9-12`, `api/app/Providers/ShopServiceProvider.php:32-125`, `pkg/cart/src/Services/ProductService.php:431-581` |
| `payments` | `Payment` model, gateway manager, drivers Free, Stripe (Omnipay PaymentIntents), Przelewy24 (incl. card renewals), RevenueCat; callbacks; admin list/export | `pkg/payments/src/Gateway/GatewayManager.php:34-67` |
| `vouchers` | Coupons `cart_fixed`, `cart_percent`, `product_fixed`, `product_percent` with dates, usage limits, min/max cart, include/exclude products and categories, allowed users | `pkg/vouchers/src/Enums/CouponTypeEnum.php:9-12`, `pkg/vouchers/src/Services/CouponService.php:198-336` |
| `invoices` | Streams a PDF for any viewable order; number = order id; hard-coded sample seller; not stored | `pkg/invoices/src/Services/InvoicesService.php:35`, `pkg/invoices/config/invoices.php:70-97` |

**Enrolment after purchase**: `POST /api/cart/pay` or `/api/product/{id}/pay` → `Order::process()` → payment
→ `PaymentSuccess` (sync, or via public callback) → `PaymentSuccessListener` → `OrderService::setPaid`
(`pkg/cart/src/Services/OrderService.php:168-214`) → `ProductBought` → `ProductService::attachProductToUser` →
productable `attachToUser` (Course: `course_user` with `end_date`, fires `CourseAssigned`;
`api/app/Models/Course.php:20-28`). Errors in `attachToUser` are only logged (`ProductService.php:575-579`). Other grant
paths: admin attach, `assign-without-account`, `course-access` (admin/group, bypasses products).

**Pricing**: no price on courses; course responses get `product` / `related_product` from the cart
(`ShopServiceProvider.php:99-125`). Taxes: flat 23 % in the cart flow (`api/config/shopping-cart.php:19-24`) vs.
per-product `tax_rate` in single-product checkout; `orders.tax` stores the rate, not the amount
(`OrderService.php:114`). No tax zones, OSS or reverse charge. Currencies PLN/USD/EUR/GBP, one global default.

**Refunds/cancellations**: never revoke access; only trial verification charges are refunded (P24).

**Dependent flows**: email templates on order/payment/product events, MailerLite on `ProductBought`/`OrderCreated`,
reports on `orders` (`PAID` only), front pages `/cart`, `/user/my-orders`, `/user/my-subscriptions`,
`/subscriptions`, `/package/:id` (`front/src/components/Routes/routes.ts:12-40`, SDK
`front/src/lib/sdk/services/cart.ts`, `products.ts`, hook `front/src/hooks/usePayment.ts`), admin `/sales/*`
(`admin/config/routes.ts:188-234`), RevenueCat in-app purchases from the mobile shell.

**Defects** (besides the critical ones at the top): no transactions or locks; `setPaid` idempotent only for `PAID`;
late callback moves a CANCELLED order to PAID; P24 signature not checked
(`pkg/payments/src/Gateway/Drivers/Przelewy24Driver.php:55-71`); Stripe 3-D Secure redirects ignored by the front
(`usePayment.ts:38-62`) and no Stripe webhook; coupon usage counts cancelled orders.

### Sylius 2.x (web research, 2026-10-08)

| Question | Answer | Source |
|---|---|---|
| Current version | **v2.3.0** (2026-09-28); 2.2.x and 2.1.x still patched; PHP ^8.3, Symfony ^6.4 / ^7.4 / ^8.0 | [Packagist](https://packagist.org/packages/sylius/sylius), [releases](https://github.com/Sylius/Sylius/releases) |
| API coverage | API Platform Shop API (`/api/v2/shop`): cart, items, addressing, shipping/payment method selection, complete; Admin API (`/api/v2/admin`): products, variants, taxons, promotions/coupons, tax categories/rates/zones (inference: standard resources) | [Customizing API](https://docs.sylius.com/the-customization-guide/customizing-api), [Sylius 2 is live](https://sylius.com/blog/sylius-2-is-live/) |
| Digital goods | Shipment step can be skipped/disabled for digital-only shops (inference from docs) | [Disable guest checkout guide](https://docs.sylius.com/the-customization-guide/how-to-disable-guest-checkout) |
| Not in core | Subscriptions/recurring billing, entitlements, outgoing webhooks (inference) | — |
| Payments model | Payment Requests replace Payum; a refund-via-Shop-API CVE was fixed in 2.1.16 / 2.2.9 → stay patched | [proposal](https://github.com/Sylius/Sylius/issues/16237), [advisory](https://www.vulncheck.com/advisories/sylius-2-x-before-2.1.16-and-2.2.9-arbitrary-payment-action-via-shop-api) |
| Stripe | Official `Sylius/StripePlugin` (`flux-se/sylius-stripe-plugin`, MIT), Checkout + Elements via Payment Requests, Sylius 2.0–2.2 | [GitHub](https://github.com/Sylius/StripePlugin), [addon](https://addons.sylius.com/en_US/products/stripe-plugin) |
| Przelewy24 | BitBag plugin 2.1.2 supports Sylius 1.12/1.13 only; `dev-sylius-2.0-support` branch unreleased → **no released Sylius 2 P24 plugin**; options: port BitBag, write a Payment Request gateway, or Mollie (P24 method; Sylius 2 support unverified) | [Packagist](https://packagist.org/packages/bitbag/przelewy24-plugin), [addon](https://addons.sylius.com/en_US/products/bitbag-przelewy24-plugin) |
| MCP | **Sylius Admin MCP Server Plugin** (`sylius/admin-mcp-server-plugin`, MIT, v0.1.2, Sylius 2.1+): 171 tools in 34 groups, OAuth 2.0 + PKCE, `ROLE_API_ACCESS`; shop-side `sylius/mcp-server-plugin` v0.2.0, experimental, licence metadata conflicting | [addon](https://addons.sylius.com/en_US/products/sylius-admin-mcp-server-plugin), [GitHub](https://github.com/Sylius/McpServerPlugin) |
| B2B | No "B2B Kit" found. **B2B Suite** is a Sylius Plus (commercial) module (organisations, custom pricing, quick order); Elesto B2B accelerator for 2.1+ (licence unchecked); RFQ plugin exists | [B2B Suite](https://addons.sylius.com/en_US/products/b2b-suite), [Elesto](https://store.sylius.com/products/elesto-b2b-accelerator-by-sylius) |
| Licence | Core MIT; Sylius Plus commercial modular licence | [LICENSE](https://github.com/Sylius/Sylius/blob/2.3/LICENSE), [Plus licence](https://sylius.com/blog/business/sylius-plus-modular-license) |

### What replacing Wellms commerce with Sylius would require

- **LMS keeps**: an `entitlements` table (user or seat pool, granted item, valid from/to, source = Sylius order item /
  admin / invite / subscription, status, unique source key); `Productable::attachToUser/detachFromUser` become
  grant/revoke handlers behind `CommerceProvider`; mapping Sylius variant → LMS items (replaces
  `products_productables`, incl. consultation credits and all-in); `course-access` and `assign-without-account`;
  template/MailerLite triggers re-pointed to entitlement events; reports from a local order projection.
- **Moves to Sylius**: catalogue, prices, channels/currencies, cart, checkout, promotions/coupons, taxes, payments,
  refunds, order states, invoices (needs a plugin or KSeF integration for Polish VAT — inference).
- **Gaps to fill**: subscriptions/trials (keep a small LMS subscription service or Stripe Billing), P24 gateway for
  Sylius 2, outgoing order events (Symfony listener on order state-machine transitions → signed HMAC message via
  outbox/Messenger → LMS endpoint that checks signature + timestamp, stores event id uniquely, grants in a
  transaction; refunds/cancellations revoke), reconciliation job pulling paid orders from the Admin API.
- **Migration**: map products/coupons to Sylius or archive; **backfill entitlements** from `products_users` and
  productable pivots so nobody loses access; clean duplicate grants and the `orders.tax` data first; run both paths
  with a reconciliation report before retiring `cart`, `payments`, `vouchers`, `invoices`.
- **Frontends**: replace `front/src/lib/sdk/services/cart.ts` and `front/src/components/Cart/*` with Shop API calls;
  link the Sylius customer to the LMS user (token exchange); admin `/sales/*` becomes links or thin wrappers over the
  Admin API; the product ↔ productable mapping UI stays in the LMS; RevenueCat needs its own webhook into the same
  entitlement service.

### Status: done
### Recommendations
- Hotfix the callback/RevenueCat issues now; do not wait for Sylius.
- Prototype order → entitlement first (open decision in the TODO), with a P24 spike, since P24 is the gap.

---

## Proposed TODO updates (for approval, not applied)

- 0.1 items 1, 2, 5, 6, 9, 10 → can be ticked once this report is accepted; items 3, 4, 7, 8 stay partial with the
  notes above.
- New items: (new) security hotfix for payment callbacks/RevenueCat; (new) remove consultation webcam capture and its
  endpoints; (new) authenticate the Jitsi webhook; (new) verify JWT signatures in the LRS guard; (new) fix
  `CourseAccessService::getUserCourseIds` grouping; (new) remove the tracker Logs screen and leftovers; (new) fix the
  tenant video queue; (new) `enforceMorphMap` for topic types; (new) ADR for the tenancy package.
