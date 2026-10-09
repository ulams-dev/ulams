# Plan: the Interactive topic type and three new demo academies

Status: **draft, waiting for the product owner's approval** (#146). M1 and M2 (the bridge, the Interactive
topic type and its learner, admin, CLI and docs surfaces) are merged. M3 (gravity) is merged and M4 (poland)
is implemented, both under the amendment below.

**Amended 2026-10-09 (product owner decisions in chat and on #147, #148, #149, #150).** These override the text
below where they differ:

- **Gravity is the owner's own code and is used under MIT inside this repository.** There is no GPL
  separation, no release zip in `qunabu/Gravity` and no checksum download. The adapted source lives in
  `demo-content/gravity/` (a yarn workspace, built with Vite) and is packaged by
  `yarn workspace @ulams/demo-gravity package`. Three outside commits of the upstream history are left out:
  bc9d770 and 9db0edc (David Frankel: Docker files, deletions) and 4adaa1b (jin: the Chinese tour
  translation, so the package is English and Polish). The music track and the Moon photograph have no
  clear licence and are not shipped (the Moon is procedural). The adapter PR in `qunabu/Gravity` is optional
  and, if ever opened, is a pull request on that repository, never a push to its main.
- **poland** is the owner's own code (MIT). The saved third-party article copy, the copied
  `world.topo.json` and the mp4 are not used. The map is regenerated from Natural Earth through
  `world-atlas` (ISC, public domain), the reply framing is dropped, only figures with primary sources stay,
  and the package is produced in EN and PL.
- **Licences:** MIT for code, CC BY 4.0 for course text (#148). The Interactive topic type is on for every
  tenant, with network access off (#150).

The product owner asked on 2026-10-09:

> "Create free courses for [the gravity and poland] applications. Create a new topic type where you can
> put a JS element, so there are courses that look like gravity (3D elements) and poland (map in the
> background), but they are treated as course item elements. The third free course I would like to see
> is about Stanisław Ulam, where the ulams name is taken from: the Lwów School of Mathematics, the
> Scottish Book, etc. All those courses should contain visually appealing content plus quizzes and
> other learning elements based on the content. After all, there should be 6 demos with courses in
> summary."

The plan is written for implementers who were not in the planning session. Every milestone names its
files, tests and definition of done (DoD). The rules of `CLAUDE.md`, `AGENTS.md` and
`docs/plans/leftovers-0-2.md` section 1 apply to every PR:

- Conventional Commits, with tests in the same commit, and no AI attribution.
- A docs-site page in the same branch.
- A policy, OpenAPI and a tenant isolation test for every new endpoint.
- `HUSKY=0` only when the hooks cannot run.

Branches are named `demos/mN-<short-name>`.

**Records:**
- ADRs proposed with this plan: **0086–0089** (section 16).
- Owner questions:
  - #146 approve the plan
  - #147 the gravity repo
  - #148 content licences
  - #149 the poland scope
  - #150 Interactive on by default
  - #151 Ulam fact review

  Where a section depends on one of them, it says **"default, pending #N"**.
- Companion files:
  - `docs/plans/interactive-demos-ulam-facts.md`: the starting fact sheet for the Ulam course.
  - `front/docs/design/stitch/interactive-demos/`: the landing design prompts.

---

## 1. Goal and scope

**Goal.** Make author-supplied JavaScript a first-class course item, and use it to add three free demo
academies, so the platform shows six demos.

**In scope**

1. **A new Interactive topic type** (ADR 0086). It is an uploaded zip package with a manifest, played
   in an opaque sandbox on the per-tenant content origin. It supports steps, a text alternative, a
   background mode, admin upload and editing, a CLI command and an MCP tool.
2. **The bridge.** The `ulams-ix` v1 protocol and the `@ulams/interactive-bridge` library (MIT,
   ADR 0087).
3. **The Layout topic type** (ADR 0052, rendering only). It lets flip cards, timelines and practice
   activities be course items.
4. **Three content packages.**
   - gravity: the owner's simulator under MIT, adapted in `demo-content/gravity/` (amended 2026-10-09).
   - poland: the owner's code, cleaned of third-party material.
   - Five small MIT packages for the Ulam course.
5. **Three demo tenants**, `gravity`, `poland` and `ulam`. Each gets a theme preset, a landing,
   certificates, free courses, demo users and the hourly reset (ADR 0089).
6. **The summary.** The platform landing shows six demos, `make demo-seed-tenants` seeds all six, and
   the README and docs site are updated.

**Out of scope**

- AI generation of interactive packages. Model-written code stays in the `simulation` component,
  which is off by default (#56).
- A multilingual course model (#149).
- Generation of Layout topics. ADR 0052's generation half stays in L2-21.
- Changes to the gravity simulator's physics or tour content beyond the adapter.

---

## 2. Facts this plan is built on (read on `main` at `289bc241` and in the two app folders, 2026-10-09)

| Area | Fact | Where |
|---|---|---|
| Topic types | A topic type is a model implementing `TopicContentContract`. It is registered with `Topic::registerContentClass()` and `Topic::registerResourceClasses($class, ['client','admin','export'])`; export/import goes through `ExportImportService::registerTopicStrategy()`. Validation is `topicable_type ∈ Topic::availableContentClasses()` plus `$class::rules()` | `api/packages/courses/src/Repositories/TopicRepository.php`, `api/packages/courses/src/Models/Topic.php` |
| Best template | **LiaScript**: its own package, zip uploads through `UploadGuard` + `SafeExtractor`, versions, a launch endpoint, a progress endpoint, an export strategy, admin library pages, a CLI command and docs | `api/packages/liascript/**` (anatomy in section 3.3) |
| Morph map | Not implemented (ADR 0043 Proposed). The full class name is stored in `topicable_type` and matched with `endsWith("\\X")` in the front | `front/web/src/components/LessonPlayer.astro`, `front/sdk/src/topics.ts` |
| Content origin | `GET api/content/{path}` (`Ulams\Uploads\Http\ContentFileController`) serves only the prefixes in `config('ulams_uploads.content_disks')` (`scorm`, `cmi5`, `liascript`), and only with `X-Ulams-Content-Origin: 1`. Caddy's `(content_origin)` snippet proxies `/scorm/* /cmi5/* /adapt/* /liascript/*` and sets **one CSP for all of them, with `'unsafe-eval'`** | `api/packages/uploads/src/**`, `api/docker/conf/Caddyfile`, `front/docs-site/examples/production/Caddyfile` |
| S3 adapter | `package_prefixes` lists the prefixes that are not forced to `Content-Disposition: attachment` | `api/packages/uploads/src/config.php` |
| Sandbox constants | `SANDBOX_SCORM`, `SANDBOX_LIASCRIPT`, `SANDBOX_H5P` and `SANDBOX_THIRD_PARTY` all use `allow-same-origin`. `SANDBOX_OPAQUE` exists but nothing uses it. `isTrustedFrameMessage(event, {frame, origin: "null"})` and `frameTargetOrigin("null") === "*"` support opaque frames | `front/sdk/src/frames.ts`, `front/sdk/tests/frames.test.ts`, `front/ui/tests/frames.test.ts` |
| postMessage precedent | `<ulams-h5p>` checks source and origin, handles `ulams-h5p:*` messages, posts theme CSS from `--ulams-*` values, and completes the topic with `announceComplete()` → `ulams:complete` → `<ulams-progress>` | `front/ui/src/elements/h5p.ts`, `front/ui/src/elements/bff.ts`, `front/ui/src/elements/progress.ts` |
| Lesson player | `LessonPlayer.astro` runs launches on the server and passes them to `topicDoc()` (`front/web/src/lib/page-docs.ts`). The content column is at most 880 px; **there is no full-bleed slot** | `front/web/src/components/LessonPlayer.astro` |
| Catalogue | Every component needs: a registry entry (`front/ui/src/registry.ts`), an `.astro` file, an entry in `Node.astro`, an example `catalogue/examples/<Name>.json` with `props` and `invalid`, and, for frames, a sandbox constant. `LEARNER_LAYOUT_COMPONENTS` has 9 members and a test asserts the length | `front/ui/**` |
| Learning components | Timeline, FlipCards, PracticeActivity, Callout, Steps, ComparisonTable and CodeBlock exist only as catalogue components. **No LMS topic can hold them**: the Layout topic type (ADR 0052) is not built. The existing demos use H5P `dialog-cards` for flip cards | `front/ui/src/registry.ts`, `api/database/seeds/Demo/**` |
| Demo seeding | One PHP class per demo extends `Database\Seeders\Demo\DemoExperience`. They are registered in `DemoCoursesSeeder::EXPERIENCES`. Topic specs are arrays and `TopicFactoryHelper::TYPES` lists the allowed types. GIFT questions are checked by type | `api/database/seeds/DemoCoursesSeeder.php`, `api/database/seeds/Demo/**` |
| Free courses | `CoursesPolicy::attend()` returns true for `public && is_published`. A free course is `public = true` with no products | `api/packages/courses/src/Policies/CoursesPolicy.php` |
| GIFT | Eight question types (multiple choice, multiple right answers, true/false, short answer, matching, numerical with tolerance, essay, description). `min_pass_score` is **points**, not a percentage | `api/packages/topic-type-gift/src/Enum/QuestionTypeEnum.php` |
| Tenants | `ulams:tenant:create {slug} --name --theme --accent --users --demo`. Slugs match `/^[a-z][a-z0-9]{1,29}$/`, so `gravity`, `poland` and `ulam` are valid. Caddy uses wildcards only; no per-slug config | `api/packages/tenancy/**` |
| Demo mode | `ulams:demo:reset` runs hourly per demo tenant (`DEMO_RESET_CRON`): `migrate:fresh`, permissions, `ulams:tenant:seed-demo`, `ulams:demo:seed` | `api/packages/demo/**` |
| Themes | A landing document is chosen **by theme name** (`landingDocs[theme]`). `themeFor()` accepts only `coffee`, `oncall` and `nightsky` and otherwise falls back to `coffee`. Many `Record<ThemeName, …>` maps must be extended (section 9) | `front/web/src/lib/theme.ts`, `front/web/src/lib/docs.ts` |
| Certificates | `CertificateTemplates::THEMES = ['default','coffee','oncall','nightsky']`; any other key throws (the seeder swallows the error with a warning). Templates are generated by `api/pdf/scripts/certificate-templates.mjs` and must use fonts from `api/pdf/fonts` | `api/packages/templates-pdf/**`, `api/pdf/**` |
| i18n | `front/web` has none. `<html lang>` comes from the landing document's `Page.props.lang`. A course has one `language` (2 characters) | `front/web/src/lib/docs.ts`, `api/packages/courses/src/Models/Course.php` |
| Platform demos | `platformModel()` loops over `ULAMS_DEMO_TENANTS` (default `coffee,oncall,nightsky`) and uses a hard-coded `STYLE[slug]` and a theme check. The e2e test expects **3** `#demos li` | `front/web/src/lib/platform.ts`, `front/web/src/lib/config.ts`, `front/web/tests/e2e/smoke.spec.ts` |
| Large files | CI rejects new files over 2 MB (#48) | `.github/workflows/ci.yml` |
| Gravity | Owner's own code (`qunabu/Gravity`, 38 of 41 commits by the owner; the three others are left out, see the amendment). Upstream `package.json` said ISC and the repo GPL-3.0; **relicensed MIT by the owner for ulams (2026-10-09, #147)**. Three.js 0.184 (MIT), Vite 8. **44 steps** in the owner's working tree (`stopped-galaxy` is uncommitted upstream, and is included). Steps are `STEPS_SOURCE` + `TOUR_ORDER` in `src/ui/tour.ts`, with deep links by `#<step-id>`. `localStorage` was used unguarded, Google Fonts were loaded from the CDN, and `base` was `/Gravity/`. The music MP3 and the Moon texture have no stated licence and are not shipped; Earth is Solar System Scope CC BY 4.0. No reduced-motion handling. The build is about 0.8 MB of JS | `/Users/mateuszwojczal/Desktop/localhost/gravity` |
| poland | The owner's code, no LICENSE, public repo. Plain inline ES5 with no libraries: hand-written SVG charts and a canvas map with its own projection. Only Google Fonts load over the network. 49 steps in 10 chapters, `{en,pl}` strings, `?lang=`. **`src/world.topo.json` was copied from the third-party article page.** `sources.json` has 193 entries, 56 of them `reported_in_uploaded_document`, and some cite the essay, Hacker News or Wikipedia. The article copy, its `_files`, the mp4 and the research dumps are git-ignored and not used | `/Users/mateuszwojczal/Desktop/localhost/poland` |
| ADR numbers | `main` has ADRs up to 0085 and 0090. 0086–0089 are free on every remote branch | `docs/decisions/README.md` |

---

## 3. The Interactive topic type

### 3.1 Package format

A package is a zip whose root contains `index.html` (the entry; the manifest may name another),
assets, and **`ulams-interactive.json`**. The JSON Schema (draft 2020-12) lives at
`api/packages/interactive/resources/schemas/ulams-interactive/v1.json` and is mirrored to
`front/interactive-bridge/schema/ulams-interactive.v1.json` (a test checks the two are identical).

```json
{
  "$schema": "https://ulams.dev/schemas/ulams-interactive/v1.json",
  "id": "gravity",
  "title": { "en": "Gravity: a guided solar system", "pl": "Grawitacja: Układ Słoneczny z przewodnikiem" },
  "version": "1.0.0",
  "entry": "index.html",
  "licence": "MIT",
  "attribution": "© 2026 Mateusz Wojczal. Earth texture: Solar System Scope, CC BY 4.0. Three.js (MIT). Inter and Roboto Mono (SIL OFL 1.1).",
  "source": { "url": "https://github.com/ulams-dev/ulams/tree/main/demo-content/gravity", "ref": "main" },
  "locales": ["en", "pl"],
  "defaultLocale": "en",
  "bridge": 1,
  "capabilities": { "steps": true, "reducedMotion": false, "score": false, "background": true },
  "requires": ["webgl"],
  "network": [],
  "steps": [
    {
      "id": "what-is-gravity",
      "title": { "en": "What is gravity?", "pl": "Czym jest grawitacja?" },
      "text": { "en": "Two bodies, Sun and Earth, with equal and opposite force arrows …", "pl": "…" },
      "poster": "posters/what-is-gravity.webp"
    }
  ],
  "a11y": {
    "keyboard": "Tab reaches all buttons; the 3D view itself is pointer-only.",
    "notes": "Each step has a text alternative; posters are shown when motion is reduced."
  }
}
```

Validation rules, enforced on upload by `ManifestValidator`:

- `id`: `^[a-z0-9-]{2,40}$`.
- `steps[].id`: `^[a-z0-9][a-z0-9-]{0,63}$` and unique.
- `steps[].text`: required for every locale listed in `locales`. This is the text alternative.
- `licence`: a valid SPDX id (from a short allow-list in config: `MIT`, `Apache-2.0`, `BSD-2-Clause`,
  `BSD-3-Clause`, `ISC`, `GPL-3.0-only`, `GPL-3.0-or-later`, `CC-BY-4.0`, `CC-BY-SA-4.0`, `CC0-1.0`,
  `LicenseRef-Proprietary`).
- `entry`, every `poster` and every path the manifest names must exist in the zip.
- `network[]`: exact `https://host[:port]` origins only, with no wildcards and no paths.
- At most 200 steps.

### 3.2 Storage and versioning

A new package, `api/packages/interactive` (namespace `Ulams\Interactive`), modelled on
`api/packages/liascript`.

**Tables** (one migration each, in `api/packages/interactive/database/migrations/`):

| Table | Columns |
|---|---|
| `interactive_packages` | `id`, `title` string(255), `storage_key` uuid unique, `current_version` unsignedInteger default 0, `author_id` nullable FK `users` nullOnDelete, timestamps |
| `interactive_package_versions` | `id`, `interactive_package_id` FK cascadeOnDelete, `version` unsignedInteger, `manifest` json, `entry` string(255), `files` json (`{path: {size, sha256}}`), `total_bytes` unsignedBigInteger, `licence` string(64), `change_note` string(500) nullable, `author_id` nullable FK nullOnDelete, `created_at`; unique (`interactive_package_id`, `version`) |
| `topic_interactives` | `id`, `value` FK `interactive_packages` restrictOnDelete (the package; the name `value` follows the topic API convention), `version` unsignedInteger nullable (null = always the current version), `start_step` string(64) nullable, `end_step` string(64) nullable, `completion_rule` string(16) default `on_range_end`, `pass_score` unsignedTinyInteger nullable (a percentage), `display` string(16) default `inline`, `height` unsignedSmallInteger default 640, `text` longText nullable (Markdown), timestamps |
| `interactive_progress` | `id`, `topic_id` FK cascade, `user_id` FK cascade, `last_step` string(64) nullable, `max_progress` decimal(5,4) default 0, `score_raw` decimal(8,2) nullable, `score_max` decimal(8,2) nullable, `completed_at` timestamp nullable, `updated_at`; unique (`topic_id`, `user_id`) |

**Files** go on the disk `config('ulams_interactive.disk') ?: config('filesystems.default')` (env
`INTERACTIVE_DISK`), at `interactive/<storage_key>/v<version>/<path>`. Versions are immutable. A
re-upload creates `v<n+1>` and bumps `current_version`. Deleting a package answers 409 while a topic
references it.

**Pinning.** A topic with `version = null` follows `current_version`. The admin editor offers "Pin to
v3" and "Follow latest", and new topics pin the current version by default, so an upload never changes
what learners see without the author's action ("AI proposes, the author approves" applies in spirit to
human uploads too). If a pinned version no longer has a step the topic uses, saving the topic fails with
a validation error naming the step.

### 3.3 API (package anatomy, copied from LiaScript)

```
api/packages/interactive/
  database/migrations/2026_10_10_100000_create_interactive_tables.php
  database/migrations/2026_10_10_200000_create_topic_interactives_table.php
  database/seeders/InteractivePermissionSeeder.php      (interactive_manage → admin, tutor)
  resources/schemas/ulams-interactive/v1.json
  resources/schemas/ulams-ix/v1/*.json                  (copied from front/interactive-bridge/schema/v1)
  src/config.php                                        (key ulams_interactive)
  src/routes.php
  src/UlamsInteractiveServiceProvider.php
  src/Enums/InteractivePermissionsEnum.php              (INTERACTIVE_MANAGE = 'interactive_manage')
  src/Enums/CompletionRule.php                          (on_open|on_range_end|on_complete|on_score)
  src/Enums/DisplayMode.php                             (inline|background)
  src/Http/Controllers/InteractivePackageController.php (admin library)
  src/Http/Controllers/InteractiveLearnerController.php (launch, events)
  src/Http/Controllers/Swagger/*Swagger.php             (OpenAPI attributes or docblocks, whichever main uses at M1 time)
  src/Http/Requests/InteractivePackageCreateRequest.php (file: required, file, new SafeUpload('interactive'))
  src/Http/Requests/InteractivePackageUpdateRequest.php
  src/Http/Requests/InteractiveEventsRequest.php        (validates the batch against the ulams-ix event schemas)
  src/Http/Resources/InteractivePackageResource.php, InteractiveVersionResource.php
  src/Http/Resources/InteractiveTopicResource.php       (client + admin)
  src/Http/Resources/InteractiveTopicExportResource.php (interactive_folder, interactive_title, interactive_version, step fields)
  src/Import/InteractiveTopicImportStrategy.php
  src/Models/{InteractivePackage, InteractivePackageVersion, InteractiveTopic, InteractiveProgress}.php
  src/Services/Contracts/InteractivePackageServiceContract.php
  src/Services/InteractivePackageService.php            (create, addVersion, delete, manifest)
  src/Services/ManifestValidator.php
  src/Services/InteractiveProgressService.php           (apply events → progress)
  src/Services/InteractiveCsp.php                       (builds the CSP for a version)
  src/Observers/InteractiveTopicObserver.php            (steps exist in the pinned/current manifest)
  tests/TestCase.php
  tests/Feature/{InteractivePackageApiTest, InteractiveTopicTest, InteractiveLearnerTest, InteractiveContentOriginTest, InteractiveExportImportTest, ManifestValidatorTest}.php
  tests/Fixtures/packages/{minimal, steps, bad-manifest, traversal, php-file, too-big-manifest}/
  README.md
```

The test fixtures are generated as zips in `setUp()` from the folders, so no binary zips are committed.

**Provider `boot()`:**

```php
Topic::registerContentClass(InteractiveTopic::class);
Topic::registerResourceClasses(InteractiveTopic::class, [
    'client' => InteractiveTopicResource::class,
    'admin'  => InteractiveTopicResource::class,
    'export' => InteractiveTopicExportResource::class,
]);
if (class_exists(ExportImportService::class)) {
    ExportImportService::registerTopicStrategy(InteractiveTopic::class, InteractiveTopicImportStrategy::class);
}
InteractiveTopic::observe(InteractiveTopicObserver::class);
AdministrableConfig::registerConfig('ulams_interactive.enabled', ['required', 'boolean'], true);
AdministrableConfig::registerConfig('ulams_interactive.allow_network', ['required', 'boolean'], false);
```

**`InteractiveTopic`** extends `Ulams\TopicTypes\Models\TopicContent\AbstractTopicContent`.
- Table: `topic_interactives`. `EXPORT_FOLDER = 'interactive'`.
- `getMorphClass()` returns `self::class` (ADR 0043 alias `topic.interactive` once the morph map lands).
- `rules()`:
  - `value`: required, integer, `exists:interactive_packages,id`
  - `version`: nullable, integer, min:1
  - `start_step`, `end_step`: nullable, string, max:64
  - `completion_rule`: in:on_open,on_range_end,on_complete,on_score
  - `pass_score`: nullable, integer, 0–100, required_if:completion_rule,on_score
  - `display`: in:inline,background
  - `height`: integer, 240–2000
  - `text`: nullable, string, max:20000
- `fixAssetPaths()` writes the package's version files to `course/<c>/topic/<t>/interactive/`.

**Routes** (`src/routes.php`):

| Method and path | Auth | What |
|---|---|---|
| `GET api/admin/interactive` | `auth:api`, `interactive_manage` | List packages (paginated, `?search=`) |
| `POST api/admin/interactive` | same | Upload a zip (`file`, optional `title`, `change_note`) → package + v1 |
| `GET api/admin/interactive/{id}` | same | Package with its current manifest and topic count |
| `PUT api/admin/interactive/{id}` | same | Rename |
| `DELETE api/admin/interactive/{id}` | same | 409 if topics reference it |
| `GET api/admin/interactive/{id}/versions` | same | Versions |
| `POST api/admin/interactive/{id}/versions` | same | Upload a new version (`file`, `change_note`) |
| `GET api/admin/interactive/{id}/preview` | same | `{url, nonce}` for an admin preview on the content origin (no tracking) |
| `POST api/interactive/launches/{topic}` | `auth:api` | `attend` gate; 503 without a content origin; 404 when `ulams_interactive.enabled` is false; creates IN_PROGRESS; returns `{url, version, manifest: {title, steps[id,title,text,poster], capabilities, requires, locales, licence, attribution, source}, topic: {start_step, end_step, completion_rule, pass_score, display, height}}` |
| `POST api/interactive/topics/{topic}/events` | `auth:api`, throttle `60,1` | Batch of ≤ 40 bridge events (`stepChanged`, `progress`, `complete`, `score`, `event`); returns `{status, progress}` |

No route takes a tracking token. Only the learner's own session, through the BFF, can report progress
(ADR 0086).

**Progress rules** (`InteractiveProgressService::apply(Topic $topic, User $user, array $events)`):

| `completion_rule` | Completes when |
|---|---|
| `on_open` | the launch succeeds (the learner opened the topic) |
| `on_range_end` | a `stepChanged` reaches `end_step` (or the last manifest step when `end_step` is null), or `complete` arrives |
| `on_complete` | the package sends `complete` |
| `on_score` | a `score` arrives with `raw / max * 100 >= pass_score` |

Completion calls `CourseProgressRepositoryContract::updateInTopic($topic, $user, ProgressStatus::COMPLETE)`
once, after an IN_PROGRESS row exists (ADR 0018). Scores are kept as the best ever recorded.
`stepChanged` to a step outside `[start_step, end_step]` is recorded but never completes the topic.
xAPI-like `event` messages are stored as xAPI statements through `XapiStatementServiceContract::store()`
when the tenant has an LRS `Access` row, and dropped otherwise. The verb must be an IRI, and objects are
prefixed `urn:ulams:interactive:<package>:<step>`.

**App wiring outside the package**, mirroring LiaScript (section 2):

- PSR-4 entries in `api/composer.json`
- the provider in `api/config/app.php`
- `InteractivePermissionSeeder` in `api/database/seeds/PermissionsSeeder.php`
- the test suite `interactive` in `api/phpunit.xml` and the CI suite list in `.github/workflows/ci.yml`
- token scopes in `api/packages/auth/resources/token-scopes.php`: `['api/admin/interactive*','courses']`
  and `['api/interactive/*','learner']`
- the tenant isolation case in `api/packages/tenancy/tests/Integration/TenantIsolationTest.php`
- the Swagger scan path in `api/config/l5-swagger.php`
- `topicTypes` in the docs-site frontmatter (section 3.8)

### 3.4 Delivery on the content origin and the CSP

1. **Config.** Add `'interactive' => 'ulams_interactive.disk'` to `ulams_uploads.content_disks`, and
   `interactive/` to `ulams_uploads.package_prefixes`.
2. **Upload policy.** Add a policy kind `interactive` to `ulams_uploads.policies`: extensions `zip`,
   mimes `application/zip`, `max_size` from `UPLOADS_INTERACTIVE_MAX_MB` (default **50**), zip profile
   `package`. `InteractivePackageService` also rejects entries whose extension is not in
   `ulams_interactive.allowed_extensions`:

   `html htm js mjs css json map txt md svg png jpg jpeg webp avif gif ico woff woff2 ttf otf mp3 ogg wav mp4 webm glb gltf bin wasm csv tsv geojson topojson xml`

   It also rejects `.php`, `.phar`, `.htaccess`, `.svgz` and dotfiles. `wasm` is allowed as a file
   type, but the CSP does not allow `'wasm-unsafe-eval'` in v1, so a package needing it fails visibly.
3. **CSP from the API.** In `ContentFileController::show()`, when the first segment is `interactive`,
   load the version by `storage_key` and version number and add a `Content-Security-Policy` header
   built by `InteractiveCsp::for(InteractivePackageVersion $v)`:

   ```
   default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';
   img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' data:;
   connect-src 'self'{ allow-list}; worker-src 'self' blob:; frame-src 'none';
   frame-ancestors {tenant front origin} {tenant admin origin}; form-action 'none'; base-uri 'none';
   object-src 'none'; report-uri {api}/api/csp-report
   ```

   `{ allow-list}` is the manifest's `network` origins, added only when `ulams_interactive.allow_network`
   is true. The frame ancestors come from the same config as the Caddy snippet's third argument
   (`FRONTEND_URL`, `ADMIN_URL`). There is no `'unsafe-eval'`.
4. **Caddy.** Add `/interactive/*` to the `@package` matcher in both Caddyfiles
   (`api/docker/conf/Caddyfile` and `front/docs-site/examples/production/Caddyfile`). Change the
   snippet's CSP line to `header ?Content-Security-Policy "…"`, so it applies only when the upstream
   did not set one; the other prefixes keep exactly the current policy. Add `POST
   /api/admin/interactive*` to `@large_uploads`. Do **not** add anything to `@tracking`: the events
   endpoint is a normal authenticated API call from the BFF.
5. **Front CSP.** `front/web/src/lib/csp.ts` already allows the content origin in `frame-src`; no
   change is needed.

### 3.5 The bridge (`front/interactive-bridge`, ADR 0087)

A new workspace, `front/interactive-bridge`, package `@ulams/interactive-bridge`, MIT, `"private":
false` (published when #77 is done). Add it to the root `package.json` workspaces and to `turbo.json`
pipelines like `front/sdk`.

```
front/interactive-bridge/
  LICENSE (MIT)  README.md  package.json  tsconfig.json  vitest.config.ts
  schema/v1/{envelope,init,goToStep,ready,resize,stepChanged,progress,complete,score,event,error}.json
  schema/ulams-interactive.v1.json          (copy of the manifest schema; test keeps them equal)
  src/protocol.ts     (types + type guards generated by hand from the schemas; PROTOCOL = 1)
  src/package.ts      (connect(options) for packages)
  src/host.ts         (createHost(frame, options) for the lesson page)
  src/index.ts
  scripts/bundle.mjs  (esbuild → dist/interactive-bridge.js, one ESM file with the MIT header)
  tests/{protocol,package,host}.test.ts
```

**Package side.**

```ts
const bridge = connect({
  steps: ["what-is-gravity", "inertia"],
  capabilities: { steps: true, reducedMotion: false },
  onInit(init) { /* locale, theme tokens, display, chrome, startStep, range, reducedMotion */ },
  onGoToStep(step) { /* navigate */ },
  onTheme(tokens) {}, onPause() {}, onResume() {},
});
bridge.stepChanged("inertia");
bridge.progress(0.5);
bridge.complete();
bridge.score(8, 10);
bridge.event("http://adlnet.gov/expapi/verbs/interacted", "slider-velocity", { response: "11.2" });
bridge.resize(document.documentElement.scrollHeight);
```

`connect()`:
- listens only for messages from `window.parent`;
- stores the nonce from `init`;
- answers `ready`;
- queues calls made before `init`;
- is a no-op when `window.parent === window`, so the package still runs standalone.

**Host side.** `createHost(iframe, { nonce, init, onMessage, readyTimeoutMs: 10000 })` sends `init`
on the iframe's `load` event and validates every incoming message with `isTrustedFrameMessage(event,
{frame: iframe.contentWindow, origin: "null"})`, the nonce, the size (16 KB) and the schema. It calls
`onMessage` only for valid messages and exposes `goToStep()`, `setTheme()`, `setLocale()`, `pause()`
and `resume()`.

**Size budget.** Under 3 KB min+gzip for `dist/interactive-bridge.js`, enforced by a test with
`zlib.gzipSync`.

### 3.6 Learner UI in `front/web` and `@ulams/ui`

**SDK** (`front/sdk`):
- `TopicKind` gains `"interactive"` (`src/types.ts`).
- `KIND_BY_CLASS` gains `interactivetopic: "interactive"` (`src/topics.ts`).
- `src/frames.ts` gains `SANDBOX_INTERACTIVE = "allow-scripts allow-popups allow-popups-to-escape-sandbox"`
  (no `allow-same-origin`, no forms, no modals, no downloads), with a test in
  `front/sdk/tests/frames.test.ts`.
- `src/interactive.ts` re-exports the bridge's host types for the web app.

**View model.** `FORMAT_BY_KIND.interactive = "interactive"` (`front/web/src/lib/view-model.ts`). The
existing format label "Interactive" is reused, so the kind and the format share a name on purpose.

**Launch.**
- `interactiveLaunch(tenant, token, topicId)` in `front/web/src/lib/data.ts` calls `POST
  {api}/api/interactive/launches/{topic}` on the server, like `liascriptLaunch`.
- `LessonPlayer.astro` calls it when `topic.topicable_type.endsWith("\\InteractiveTopic") && access
  && tracked`.
- In preview it calls the admin preview endpoint instead, with the author token the studio already
  uses, and nothing is tracked.
- `topicDoc()` gains `case "interactive"`, which returns an `InteractiveLesson` node. On a launch
  error it returns a `Callout` with the text alternative.
- `completionMode()` returns `"external"` for interactive topics. Add this new value to the union;
  `<ulams-progress>` treats it like `h5p`: no "mark complete" button, and it waits for `ulams:complete`.

**BFF.** Add the rule `POST /bff/interactive/:topic/events → POST /api/interactive/topics/:topic/events`
to `BFF_RULES` in `front/web/src/lib/bff.ts`. The learner session adds the token server-side.

**Catalogue component `InteractiveLesson`** (registry category `learning`, `interactive: true`):

| Prop | Schema | Notes |
|---|---|---|
| `src` | href, required | Content-origin URL of the entry file |
| `title` | text, required | iframe `title` and heading |
| `topicId` | int | For the BFF events call; omitted in preview |
| `courseId` | int | |
| `display` | oneOf `inline`, `background`; default `inline` | |
| `height` | int 240–2000, default 640 | inline only |
| `startStep`, `endStep` | text, optional | |
| `steps` | list of `{id, title, text, poster?}`, required, at least 1 | Text alternatives and posters |
| `text` | text (Markdown), optional | The lesson's own explanation |
| `requires` | list of `webgl` | |
| `reducedMotionSupported` | bool | From `capabilities.reducedMotion` |
| `locale` | text, default `en` | |
| `licence`, `attribution`, `sourceUrl` | text | Shown in "About this interactive" |

Files:
- `front/ui/src/components/InteractiveLesson.astro` and `InteractiveLesson.module.css`
- `front/ui/src/elements/interactive.ts` (the `<ulams-interactive>` web component with the bridge host)
- `front/ui/catalogue/examples/InteractiveLesson.json`
- an entry in `Node.astro`
- an entry in `frame-components.test.ts` (`SANDBOX_INTERACTIVE`)

It is **not** added to `LEARNER_LAYOUT_COMPONENTS`: Layout topics may not embed raw packages, so the
"length 9" test stays.

**Rendering:**

- **Inline.** The text sits above, the frame below at `height` (or the height from `resize`, capped at
  2000). Below the frame:
  - a `<details>` "Text version of this interactive" listing every step in the topic's range, with its
    title and text;
  - "About this interactive", with the licence, attribution and source link.
- **Background** (the "map in the background" look). The lesson page switches to a full-bleed layout:
  - `LessonPlayer.astro` gets a `layout="immersive"` variant (`u-player--immersive`). The program tree
    collapses into its existing `<details>`, and `.u-player__content` loses its 880 px cap.
  - The iframe is `position: fixed; inset: 0; z-index: 0`.
  - The lesson text is a card (`.u-interactive__overlay`) at `max-width: 34rem`, on the left on
    desktop and as a bottom sheet on phones. Its background is `color-mix(in srgb,
    var(--ulams-color-surface) 88%, transparent)` with a backdrop blur, so text contrast stays AA
    over any frame content.
  - The overlay holds the step text, a stepper ("Step 3 of 5", previous/next buttons that send
    `goToStep`), the text-version disclosure and "Explore freely".
  - "Explore freely" hides the overlay and moves focus into the frame. A visible "Back to the lesson"
    button and Escape (handled by the host page, outside the frame) restore it.
  - While the overlay is shown, the frame has `tabindex="-1"` and pointer events pass only outside the
    card. The overlay is a `region` with an `aria-label`.
- **Accessible fallbacks**, all handled in `interactive.ts`:
  1. If `prefers-reduced-motion: reduce` is set and `reducedMotionSupported` is false, show the
     step's `poster` image (with the step text as `alt`) instead of the live frame, plus a "Play the
     animation anyway" button.
  2. If `requires` includes `webgl` and `document.createElement("canvas").getContext("webgl2")` is
     null, show the poster and the text.
  3. If `ready` does not arrive within 10 s, or `error` arrives, show the text alternative and an
     error note. The topic can still be completed with `on_open`.
  4. Keyboard: the stepper is native buttons, the frame gets focus only on "Explore freely", and
     Escape returns. Step changes are announced through an `aria-live="polite"` region ("Step 3:
     Too slow — it falls in").
  5. Theme tokens are sent in `init` (the same token list as `UlamsH5P.themeCss()`, as a
     name-to-value map). `reducedMotion` is sent too.
- **Events.** Bridge messages are queued and sent with `fetch("/bff/interactive/{topic}/events",
  {method: "POST", keepalive: true})` every 2 s, and on `pagehide`. When the response has `status ===
  1`, the component calls `announceComplete("interactive")`.

### 3.7 Admin, CLI and MCP

**Admin (umi):**

| Piece | File |
|---|---|
| Topic type enum | `admin/src/services/ulams/enums.ts`: `TopicType.Interactive = 'Ulams\\Interactive\\Models\\InteractiveTopic'` |
| API client | `admin/src/services/ulams/interactive.ts` (list, upload, get, rename, delete, versions, addVersion, preview) |
| Library pages | `admin/src/pages/Interactive/index.tsx` (table: title, versions, licence, topics; upload button) and `admin/src/pages/Interactive/editor.tsx` (manifest summary, steps table with posters, versions list with "upload new version", preview in an iframe using `SANDBOX_INTERACTIVE` and the preview endpoint) |
| Routes | `admin/config/routes.ts`: `/courses/interactive` and `/courses/interactive/:id`, `access: 'interactiveListPermission'` |
| Access | `admin/src/access.ts` and `admin/src/consts/permissions.ts` (`InteractiveManage = 'interactive_manage'`) |
| Topic editor | `admin/src/components/ProgramForm/ThreeColProgram/TopicForm/media/interactive.tsx`: package select (with "upload new"), version (pin or follow latest), start and end step selects filled from the manifest, completion rule, pass score (only for `on_score`), display (inline or background), height, and the text (the existing Markdown editor). Wired in `TopicForm/index.tsx` and `List/TopicTypesSelector.tsx`; icon in the class-basename switch |
| Statistics | `admin/src/components/CourseTopicsStatistics/index.tsx`: a case for the new type |
| Strings | `admin/src/locales/en-US.ts` and `pl-PL.ts` |
| Settings | `admin/src/pages/Settings/global.tsx` already hides topic types per `disableTopicType-*`. Add nothing there; the API flag `ulams_interactive.enabled` is shown in the generic config screen |

**CLI** (`front/cli`):

- `spec/overrides.yaml` gives the generated nouns these ids: `"POST /api/admin/interactive": { id:
  interactive.create, upload: { file: { field: file, accept: [".zip"] } } }`, plus `interactive.list`,
  `interactive.get`, `interactive.versions`, `interactive.add-version` (upload) and `interactive.delete`.
- Add `interactive` to the scope area regex in `scripts/gen-commands.mjs`.
- Add `"POST /api/interactive/launches/*"` and `"POST /api/interactive/topics/*/events"` to
  `spec/exclusions.yaml` under `content-runtime`.
- Hand-written `topics.create-interactive` in `front/cli/src/commands/topics.ts`:
  - It mirrors `topics.create-liascript`.
  - Input: `...base` plus `file` (a local .zip) **or** `package` (an existing id), `version?`,
    `startStep?`, `endStep?`, `completion` (enum, default `on_range_end`), `passScore?`, `display`
    (enum), `height?`, `text?` (with `fileInput: true` for `@path`).
  - Endpoints: `["POST /api/admin/interactive", "POST /api/admin/topics"]`.
  - Example: `ulams topics create-interactive --lesson 12 --title "Too slow" --file gravity.zip
    --start-step too-slow --end-step too-slow --display background --json`.
  - Add `interactive: "Ulams\\Interactive\\Models\\InteractiveTopic"` to `TOPIC_CLASSES`.
  - Test in `front/cli/tests/unit/topics.test.ts` (it uploads first, then creates the topic, and the
    `plan` lists both calls).
- **MCP.** No new code. The tool `topics_create_interactive` is generated from the registry (toolset
  `topics`). Add it to the expected tool list in the MCP snapshot test if one exists
  (`front/cli/tests/unit/mcp*.test.ts`).
- Regenerate the docs page `front/docs-site/src/content/docs/developers/cli.mdx` with the generator
  `70-cli.mjs`.

### 3.8 Docs site

- **New page** `front/docs-site/src/content/docs/creators/interactive.mdx`. Frontmatter:
  `modules: [interactive, uploads]`, `topicTypes: [InteractiveTopic]`, `adminRoutes:
  [/courses/interactive/**]`. It covers: what a package is, the manifest reference, the bridge quick
  start, steps and ranges, background mode, accessibility duties, licences, limits and the CSP.
- **New page** `front/docs-site/src/content/docs/developers/interactive-bridge.mdx`: the protocol
  reference, generated in part from `front/interactive-bridge/schema/v1`.
- **Updates:**
  - `operators/content-origin.mdx` (the new prefix and the CSP set by the API)
  - `creators/lessons-and-topics.mdx` and `creators/index.mdx` (the new type in the list)
  - `admin/settings.mdx` (the two settings)
  - `LICENSING.md` (`demo-content/`, the bridge)
- **Catalogue.** `/catalogue/` gets `InteractiveLesson` automatically from the example file.

---

## 4. Security and availability

| Topic | Decision |
|---|---|
| Untrusted code | A package is untrusted, even from an author. It runs in an opaque origin (`SANDBOX_INTERACTIVE`, no `allow-same-origin`), so it has no cookies, storage or same-origin requests to the app. It gets no credentials (no token in the URL or the messages). It talks to the page only through validated `ulams-ix` messages with a per-launch nonce |
| CSP | Set per version by the API (section 3.4): no `'unsafe-eval'`, `connect-src 'self'` only, `frame-src 'none'`, `form-action 'none'`, `frame-ancestors` limited to the tenant's front and admin |
| Network | None by default. The manifest `network` allow-list is honoured only with `ulams_interactive.allow_network = true` (default false). The admin upload screen lists the origins and needs a confirmation checkbox when the list is not empty |
| Upload limits | The upload guard policy `interactive` (50 MB, `UPLOADS_INTERACTIVE_MAX_MB`), the zip `package` limits (entries, ratio, size), the extension allow-list, the manifest schema, path normalisation (`ZipInspector::normalise`) and the virus scanner when configured |
| Prompt injection | Manifest texts and file names are untrusted. They are rendered as text (escaped), never as HTML, and never sent to an LLM. The course builder does not read packages |
| Availability | On by default, like SCORM, with a per-tenant switch `ulams_interactive.enabled` (default, pending #150). When off: the launch answers 404, the admin hides the type (it reads the public config), and existing topics show the text alternative |
| Model-written code | Unchanged: off by default (#56). The builder never creates or edits interactive packages, and Interactive is not in the builder's content-type registry (ADR 0050) |
| Relation to `simulation` | L2-22 later reuses `SANDBOX_INTERACTIVE`, `InteractiveCsp` (with a `simulation` profile: `script-src 'unsafe-inline'` only, no `'self'`, `connect-src 'none'`, 200 KB single file), the `ulams-ix` protocol and `InteractiveLesson` (inline only). ADR 0053 stays the record for the AI-specific gates |
| Tenant isolation | Packages live under the tenant's disk and content origin. `storage_key` is a random UUID. Every new endpoint has a cross-tenant 404 test |

---

## 5. Layout topic type (ADR 0052, rendering only)

The request asks for "at least one other learning element per module (flip cards, timeline, practice
activity)". These exist as catalogue components but cannot be LMS topics today. This plan builds the
LMS half of ADR 0052 now. Generation stays in L2-21.

- **Package** `api/packages/topic-type-layout` (namespace `Ulams\TopicTypeLayout`, same anatomy as
  `topic-type-project`).
  - Model `LayoutTopic`, table `topic_layouts`: `id`, `document` json, `schema_version` string(16)
    default `1`, `markdown_fallback` longText, timestamps.
  - `rules()`: `document` required array; `schema_version` in `1`; `markdown_fallback` required string.
  - A FormRequest-level validation of `document` against `front/ui/catalogue/learner-layout-manifest.json`,
    which is copied to `api/packages/topic-type-layout/resources/learner-layout-manifest.json` by
    `yarn workspace @ulams/ui learner-manifest` (a `--check` fails CI when they differ). It uses
    `opis/json-schema`, which is already a dependency.
  - Export resource: `document` + `markdown_fallback`. The import strategy is not needed (plain JSON).
- **Front.**
  - `KIND_BY_CLASS.layouttopic = "layout"` and a `"layout"` `TopicKind`.
  - `topicDoc()` case `layout` renders the stored document through the existing `Node.astro` renderer
    inside the content column. An invalid document renders `markdown_fallback` as `Prose`.
  - `completionMode` is `"view"`, except when the document contains a `PracticeActivity`: then it is
    `"external"`, and the activity's `ulams:complete` is fired after the first checked attempt.
- **Admin.** `TopicForm/media/layout.tsx`: a JSON editor (the existing code editor component) with
  validation against the manifest, plus a read-only preview link. Authors normally get layouts from
  the builder later; the demo seeder writes them directly through `TopicRepositoryContract`.
- **Seeder support.** `TopicFactoryHelper::TYPES` gains `layout` and `interactive`.
- **Docs.** `creators/layout.mdx` (`topicTypes: [LayoutTopic]`), marked "authored by the course builder
  later; hand-written JSON today".

---

## 6. Adapting the two apps

### 6.1 Gravity (MIT, the owner's own code; ADR 0088 as amended; #147)

**Where the work happens.** In this repository, `demo-content/gravity/`, a yarn workspace
(`@ulams/demo-gravity`, private). It is the owner's simulator adapted for the bridge. It is not linked into
any other workspace (the boundary lint enforces that).

**What was imported.** The working tree of the owner's checkout after commit 28e912b (including the
uncommitted `stopped-galaxy` step: 44 steps), minus: the Docker files and deletions (bc9d770, 9db0edc), the
Chinese tour translation (4adaa1b), the music track, the Moon photograph and the upstream GitHub Pages
workflow. `NOTICE` records the origin and what was left out.

**Changes, one commit each in M3:**

1. `fix: guard browser storage`: every `localStorage` call goes through `src/ui/storage.ts` (try/catch), so
   the opaque-origin frame does not throw.
2. Self-hosted fonts: `@fontsource-variable/inter` and `@fontsource/roboto-mono` (OFL-1.1 fonts, MIT package
   code), only the latin and latin-ext subsets; the Google Fonts links are gone.
3. A public step API on the tour: `steps()`, `goTo(id)`, `setLanguage(lang, persist)`, `embed(range)` and a
   `gravity:step` window event.
4. The bridge adapter, `src/ulams/adapter.ts`, using `@ulams/interactive-bridge` from the workspace. When
   `window.parent !== window` it:
   - maps `goToStep` to `tour.goTo` and `gravity:step` to `stepChanged` (and `progress`);
   - on `init` applies the locale, the chrome (`none` keeps only the scene and the controls a step needs:
     time speed, the Lagrange shots, the stopped-galaxy buttons; `minimal` adds Back and Next inside the step
     range), the first step and the range, and slows the whole clock under reduced motion;
   - sends `error {code: "webgl-unavailable"}` when the renderer cannot be created;
   - sends `ready` with the 44 step ids and `capabilities {steps, background, locales: ["en","pl"]}`.
   It never sends `complete`: a topic completes on `on_range_end`, and `complete` would end a partial range
   early. `reducedMotion` is declared **false**, so the lesson page shows the step poster to a reduced-motion
   learner (with a "play anyway" button); the adapter slows the clock for the learner who plays it anyway.
5. `scripts/ulams-package.mjs`: `vite build` (relative base), one 1280x720 WebP poster per step
   (Chromium, SwiftShader), the manifest generated from `STEPS` and `PL` in `tour.ts` (so it cannot drift),
   `LICENSE.txt`, `NOTICE.txt` and the font and Three.js licence texts, zipped without directory entries into
   `release/gravity-ulams-<version>.zip` (git-ignored, about 1.6 MB).

**`stopped-galaxy`.** Included (44 steps). If the owner drops it, module 8 has one topic fewer.

**Seeding (M8).** The seeder gets the zip by running the package script (or from the cache,
`api/database/seeds/Demo/assets/cache/`); no download, no checksum. `Demo/Support/ContentPackages.php`
(`fromFolder`, for the folder packages) is still planned for M8.

**Boundary check.** `demo-content/scripts/check-demo-content-boundary.mjs` (part of the `demo-content`
lint, so of the root `lint` task) fails if any file outside `demo-content/` imports or requires a path inside
it, or depends on a `demo-content` workspace. PHP seeders may read files from it as data (`file_get_contents`,
`ZipArchive::addFile`, `glob`); `require` and `include` are refused.

### 6.2 poland (owner's code; ADR 0088; MIT code and CC BY 4.0 text, #148; scope, #149)

**Built in M4 (2026-10-09).** The result differs from the sketch below in these ways, all from the owner's
decisions:
- The data is fetched (`data/steps.json`, `data/sources.json`, `data/world.topo.json`); the story has 40 steps
  (the outlook chapter and the outro are dropped) in nine chapters, EN and PL in one package.
- Only sources that publish the figure are kept: every entry that came from the article, press, an aggregator
  or an encyclopedia is gone (`scripts/check.mjs` enforces it), and figures were re-sourced or removed
  (`demo-content/poland/README.md`, "What changed in the figures"). Where a number could not be confirmed the
  publisher's number replaced it; each change is recorded there. Steps keep their ids.
- The map: `world-atlas@2.0.2` through `topojson-server` and `topojson-simplify` (ISC), simplified harder
  outside Europe: 358 KB, 170 countries. `topojson-server` and `topojson-simplify` are two more dev dependencies
  (section 14).
- The map engine is `demo-content/shared/atlas.js` (MIT); poland and the future `lwow-map` each get a copy in
  `vendor/` from `sync-bridge`, and the lint fails when a copy differs.
- The bridge gained `whenReady` and a `steps` function, because a package that loads its data asynchronously
  must register its `init` listener at start and answer `ready` only once it knows its steps.
- Posters are committed (40 WebP stills, about 630 KB); the zip is 1.0 MB (`scripts/pack.mjs`).
- Step mode shows the figures in a right-hand panel (`chrome: none`); on a phone a native button opens it.

The original sketch:

**Where the work happens.** In the ulams repo, `demo-content/poland/`. The files are a cleaned copy of
`src/template.html` and `src/sources.json` at commit `<HEAD of qunabu/poland-october-2026>` (recorded
in `NOTICE`). No build step is needed; `build.py` is not used.

```
demo-content/poland/
  LICENSE            (MIT, pending #148)
  LICENSE-content    (CC BY 4.0, pending #148)
  NOTICE             (origin commit; map data credits)
  ulams-interactive.json
  index.html         (from src/template.html: CSS + markup; the script moved out)
  app.js             (the inline ES5 script, moved out unchanged except for the hooks below)
  data/world.topo.json   (regenerated, see below)
  data/sources.json      (cleaned)
  data/steps.json        (the STEPS array moved out of the script, cleaned)
  vendor/interactive-bridge.js
  fonts/             (Barlow, Barlow Semi Condensed, JetBrains Mono woff2 subsets with OFL.txt)
  scripts/build-topo.mjs (dev only: world-atlas@2.0.2 countries-50m → filtered, quantised topo; run once, output committed)
  scripts/check.mjs      (validates steps against sources, no essay references, sizes)
```

**Changes to the code** (each its own commit in M4):

1. **Remove third-party material.**
   - Delete every reference to the essay: the struck-through "The essay's chapter" titles, the
     `tomwojcik.com` links (3), the outro button, and the hero line "as seen by just another Polish
     guy" (replace it with a neutral title; see section 7.2).
   - Remove the HN and essay entries from `sources.json`, and every entry with status
     `reported_in_uploaded_document`, unless an extra primary source is added.
   - Delete any step whose figures lose their only source.
   - `scripts/check.mjs` fails if `tomwojcik`, `news.ycombinator` or `reported_in_uploaded_document`
     appears anywhere.
2. **Regenerate the map.**
   - `scripts/build-topo.mjs` reads `world-atlas@2.0.2/countries-50m.json` (ISC, Natural Earth public
     domain). It is a dev dependency of the `demo-content` workspace (section 6.4).
   - It keeps ISO numeric `id` and `properties.name`, drops Antarctica, crops to `[-170,-56,179,82]`,
     renames the object to `c` (the code expects it) and quantises to 1e4.
   - The output must stay under 400 KB.
   - `NOTICE`: "Map data: Natural Earth (public domain), via world-atlas (ISC)".
3. **Fetch the data instead of inlining it.** `app.js` loads `data/world.topo.json`,
   `data/sources.json` and `data/steps.json` with `fetch()` (allowed by `connect-src 'self'`).
4. **Self-host the fonts** (OFL) and remove the Google Fonts links.
5. **Bridge.**
   - Keep the scroll story for standalone use. Under the bridge (`window.parent !== window`), switch
     to **step mode**:
     - the story column is hidden when `chrome` is `none` (the ulams overlay shows the text), or
       shown as one card at a time when `chrome` is `minimal`;
     - `goToStep(id)` calls the existing `setView(VIEWS[step.view])` and renders that step's chart into
       an in-frame panel, positioned to the right of the overlay;
     - `stepChanged` fires on every change;
     - `init.locale` sets the language via the existing `setLang()` without touching `localStorage`;
     - `reducedMotion` is mapped to the existing `?instant` behaviour;
     - `ready` sends all step ids with `capabilities {steps: true, reducedMotion: true, locales:
       ["en","pl"], background: true}`.
   - Charts get their accessible tables (they already exist). The map canvas gets a per-step
     `aria-label` from the step's view name.
6. **Reduced-motion fixes.**
   - Stop the SMIL `<animateMotion>` particles (`pauseAnimations()` on the SVG).
   - Stop the map `requestAnimationFrame` loop when `reducedMotion` is set, or when the bridge sends
     `pause`.
7. **Manifest.**
   - `steps[]` holds one entry per kept step: id, title `{en,pl}`, text `{en,pl}` (the step's body
     text without HTML) and a poster (rendered once with `scripts/posters.mjs` using Playwright from
     the web workspace, committed as WebP under 60 KB each).
   - Total package size must stay under 3 MB.

### 6.3 The five Ulam packages (MIT, new; licence pending #148)

**Built in M5 (2026-10-09).** Shared by the five: `demo-content/shared/ulam-shell.{js,css}` (the step card, Back
and Next inside the lesson's step range, the bridge wiring with `whenReady`, reduced motion, poster mode and the
notebook look), copied into each package's `vendor/` by `sync-bridge`, and `tests/e2e/ulam-common.mjs` (the checks
every package must pass). The package fetches its own manifest, which is the one source of step titles and texts, and hands the shell any
data it loads with `load` so `ready` waits for it.
The lesson page asks for `chrome: full` when it plays a package inline, so the package shows its step card;
with `none` (background display) only the interactive shows. Done so far: `spiral` (M5a), `monte-carlo` (M5b), `automaton` (M5c), `scottish-book` (M5d, placeholder data).

```
demo-content/ulam/
  LICENSE (MIT)  LICENSE-content (CC BY 4.0)  CREDITS.md  sources.json
  spiral/       index.html  main.js  style.css  ulams-interactive.json  vendor/interactive-bridge.js
  monte-carlo/  …
  automaton/    …
  scottish-book/ … data/problems.json
  lwow-map/     … data/route.json data/places.json (map engine from ../../shared/atlas.js)
```

These are plain ES2020 modules with `// @ts-check` and JSDoc types, no framework and no build. Each is
at most 150 KB. Every package works with keyboard only, honours `reducedMotion` (no animation, final
state drawn) and sends `ready`, `stepChanged` and `complete` (and `score` for the explorer).

| Package | Steps | What the learner does | Completion |
|---|---|---|---|
| `spiral` | `grid`, `primes`, `diagonals`, `explore` | Draws the integers 1…N on a square spiral on a `<canvas>`. Primes are highlighted (sieve of Eratosthenes). Controls: N (100…40 000; slider plus number input), start value, highlight `n²+n+41` diagonals, and a click or Enter on a cell to read its number and whether it is prime. A text summary gives the count of primes and the share on the highlighted diagonals | `complete` after `explore` is visited |
| `monte-carlo` | `idea`, `throw`, `converge`, `error` | Throws random points into a unit square and estimates π from the share inside the quarter circle. Buttons: +1, +100, +10 000, reset. A live estimate, absolute error and a log-log convergence chart. A seeded PRNG (mulberry32) makes runs reproducible, and the seed is shown | `complete` when at least 10 000 points have been thrown |
| `automaton` | `rule30`, `rule90`, `rule110`, `life`, `ulam-growth` | Elementary 1D automata (rule number input, 0…255) and Conway's Life with presets, plus a "growth from a single cell" pattern in the style Ulam studied (cited only once fact 7.2 is cleared; otherwise the step is labelled as an illustration, not a historical reconstruction). Step, play and pause, with keyboard controls | `complete` after three steps are visited |
| `scottish-book` | one step per cleared problem (at least 3: 19, 153, 193) plus `intro` | Cards styled as notebook pages: number, poser, date, a plain-language summary, the prize and the outcome, each with source ids. Filter by poser. A "guess the outcome" choice per card sends `score` | `on_score` (pass 60 %) |
| `lwow-map` | `lwow`, `princeton`, `harvard`, `madison`, `los-alamos`, `boulder`, `santa-fe` | A journey map: the poland map engine (`setView`, projection, TopoJSON decoding, extracted into `demo-content/shared/atlas.js`, MIT pending #148) with Natural Earth countries, a route line and city points (coordinates from Wikidata, CC0). The `lwow` step adds a schematic inset of central Lwów (no basemap; points for the university, the Polytechnic and the Scottish Café building with their present-day addresses, from cleared fact 3.3). No historical borders are drawn, and the caption says so | `on_range_end` |

`demo-content/shared/atlas.js` is extracted from the poland `app.js` in M4 so that both packages use one
copy.

### 6.4 The `demo-content` workspace

- `demo-content/package.json` (`@ulams/demo-content`, private).
- Dev dependencies: `world-atlas@2.0.2` (ISC) and `topojson-client@3.1.0` (ISC) for `build-topo.mjs`,
  plus `typescript` for `tsc --noEmit --allowJs --checkJs` over `ulam/**` and `poland/app.js`.
- Scripts:
  - `lint`: the boundary check, the type check and `scripts/check-manifests.mjs` (validates every
    `ulams-interactive.json` against the schema from `front/interactive-bridge/schema`).
  - `sync-bridge`: copies `front/interactive-bridge/dist/interactive-bridge.js` into every `vendor/`
    folder. Lint fails when a copy differs.
- Not part of any app build.

---

## 7. Course content plans

**Common shape for all three courses** (ADR 0089 sourcing rules apply):

- Every module has:
  - one or more **Interactive** topics (one step or a short range each), each with the explanation in
    `text` and `display: background` for gravity and poland, `inline` for most Ulam packages;
  - one **Layout** topic with at least one of FlipCards, Timeline, PracticeActivity, Steps,
    ComparisonTable or Callout;
  - a **GIFT quiz** (3–6 questions; multiple choice, numerical with tolerance, matching, true/false;
    `max_attempts` 3, `min_pass_score` set in points, about 60 %; `counts_to_grade` true, `weight` 1).
- A last lesson, **Final test**: 12–15 questions across all modules, `counts_to_grade` true, `weight`
  10, `min_pass_score` about 70 % in points, `max_attempts` 2, `max_execution_time` 20.
- A **certificate** (themed, section 8.5), assigned through `DemoExperience::certificate()`.
- A course page from the API (title, subtitle, summary, description from
  `Demo/content/<key>/course-description.md`, level, `language`, duration, target group, cover image
  drawn by `Demo/Art/<Key>Art.php`).
- Citations: every `text`, card and question cites `[n]` ids from `demo-content/<demo>/sources.json`.
  The seeder appends a "Sources" list to each text from that file (helper
  `DemoExperience::withSources(string $markdown): string`).

### 7.1 Gravity Lab: "How gravity shapes the solar system" (`en`, free, about 4 h)

Sources: the tour text of `qunabu/Gravity` (the app's own content) plus NASA/JPL for every number:

- the planetary fact sheets (nssdc.gsfc.nasa.gov/planetary/factsheet);
- JPL "Keplerian Elements for Approximate Positions of the Major Planets" (Standish), the source the
  app cites in `src/data/bodies.ts`;
- NASA Science pages on orbits and Kepler's laws, Lagrange points, the Voyagers, LIGO and the Event
  Horizon Telescope.

**Research step M8a** checks each number in the tour text that the course uses against these, and
records mismatches in `demo-content/gravity/FACTCHECK.md`. The course uses the NASA value, and an
upstream fix is proposed to the owner.

| Module | Interactive steps (one topic each unless grouped) | Learning element | Quiz focus |
|---|---|---|---|
| 1. What gravity is | `what-is-gravity`; `early-universe` + `birth-of-sun` + `birth-of-earth` (one topic, range) | FlipCards: force, mass, weight, inverse square | Inverse-square numerical (double the distance → ¼ force), true/false on Newton's 3rd law |
| 2. Falling around: orbits | `inertia`, `why-no-fall`, `too-slow`, `too-fast` | PracticeActivity: "predict the path" with tiered hints and a worked solution | Matching speed → outcome (fall, orbit, escape) |
| 3. Kepler's laws | `solar-system` (start), `venus-rose` | Steps: Kepler's three laws, with the orbital elements from JPL | Numerical: Kepler's 3rd law with T² ∝ a³ (Earth 1 AU → Mars 1.52 AU, period ≈ 1.88 yr, tolerance 0.05) |
| 4. Cosmic velocities | `rocket-too-slow`, `first-cosmic`, `second-cosmic`, `third-cosmic` | ComparisonTable: 7.9 / 11.2 / 16.7 km/s and what each does | Numerical: escape = √2 × orbital (tolerance 0.1 km/s) |
| 5. Earth and Moon | `earth-moon`, `moon-no-fall`, `tides`, `geoid` | Callout plus FlipCards: barycentre, tides twice a day | Multiple choice: why two tides |
| 6. Orbits in 3D and a moving Sun | `into-3d`, `self-rotation`, `polaris`, `sun-moving` + `sun-moving-vectors` + `sun-moving-moons` (range) | Timeline: precession over 26 000 years | True/false and multiple choice |
| 7. The solar system as a machine | `light-lag`, `magnetosphere`, `sphere-of-influence`, `lagrange`, `resonance` | PracticeActivity: "which Lagrange point for a solar observatory?" | Matching missions → Lagrange points (SOHO, DSCOVR → L1; Webb, Euclid → L2) |
| 8. Travelling by gravity and beyond | `gravity-assist-1`, `gravity-assist-2`, `heliosphere`, `exoplanet`, `milky-way`, `cosmic-motion`, `stopped-galaxy` (if kept, #147), `sagittarius-a`, `dark-matter` | Timeline: Voyager 1 and 2 milestones (dates from NASA JPL Voyager pages) | Multiple choice and numerical |
| 9. Einstein's gravity | `spacetime`, `mercury-precession`, `lensing`, `time-dilation`, `black-hole`, `gravitational-waves` | Timeline: 1915 → 1919 eclipse → GPS → 2015 LIGO; FlipCards | Multiple choice |
| Final test | — | — | 15 questions |

The table has 9 modules, while the Stitch prompt mentions 8; the course follows this table. Each
interactive topic uses `completion_rule: on_range_end` and `chrome: none`. Its `text` is a 120–200 word
explanation with the step's key number and one "try this" prompt for the live scene.

### 7.2 Poland, Measured / Polska w liczbach (`en` and `pl`, free, about 3 h each; pending #149)

The title "Poland, measured" is neutral. The subtitle is "35 years of change in public statistics:
what improved and what is still hard". Sources are the cleaned `demo-content/poland/data/sources.json`
(primary only: Eurostat, GUS / Statistics Poland, the European Commission, NATO, ministries, regulators
URE / UTK / ULC, GDDKiA, the Polish Police, CBOS, BLIK, InPost, and company annual reports).

**Research step M8b:**
- re-open every kept source URL;
- record the figure, period and URL in `demo-content/poland/FACTCHECK.md`;
- remove figures whose source no longer shows them.

The course states the reference period for every figure ("in 2025", "June 2025"), since the data is
recent and will date.

| Module (app chapter) | Interactive topics (step ids from the app) | Learning element | Quiz focus |
|---|---|---|---|
| 1. Energy | `gas`, `solar`, `coal`, `food` | ComparisonTable: gas storage PL / EU / DE | Numerical: coal share change in percentage points (tolerance 0.5) |
| 2. Prosperity | `pps`, `eight`, `growth`, `consumption`, `poverty` | FlipCards: GDP per capita in PPS, AIC, price level | Matching country → PPS index |
| 3. Security | `flank`, `defence`, `yards`, `safe` | Callout: what "% of GDP on defence" measures | Numerical and multiple choice (figures only) |
| 4. Made in Poland | `ai`, `parcels`, `brands`, `orbit`, `jobs` | Timeline of the events listed, dated from their sources | Multiple choice |
| 5. Daily life | `mob`, `pit`, `blik` | Steps: a digital day (from the app's "day" chart) | True/false |
| 6. Mobility | `roads`, `transit`, `travel` | ComparisonTable: rail, air, sea | Numerical |
| 7. Health | `life`, `roaddeaths`, `air` | PracticeActivity: "read the chart" (compute a percentage change from two cited values; hints; worked solution) | Numerical |
| 8. People | `happy`, `social` | FlipCards | Multiple choice |
| 9. Unfinished work | `ledger` | ComparisonTable: "still hard" vs "what is moving" | Multiple choice |
| Final test | — | — | 12 questions |

- Chapter openers (`c-*`) and `hero` become the module intros: the first topic of each module, using
  the opener step.
- `c-outlook` and `outro` are dropped: they are opinion, not measurement.
- Every interactive topic uses `display: background`, `chrome: none` and `completion_rule:
  on_range_end`.
- There is **no education module**: the app has no education data, and adding one would need new
  research (#149).
- The PL course is the same table with the `pl` strings. Quiz questions are written in both languages
  in the lesson table (`['en' => '…', 'pl' => '…']`).

### 7.3 The Scottish Book: "Stanisław Ulam and the Lwów School of Mathematics" (`en`, free, about 3.5 h)

Sources and facts: only the cleared version of `docs/plans/interactive-demos-ulam-facts.md`.

**Research step M9a** produces it and the owner reviews it (#151). The step:

1. re-fetches every source;
2. replaces the (2nd) and pointer sources with primary ones where possible (MacTutor, LANL *Los Alamos
   Science* 15 (1987), the Mauldin 2015 edition, the *Bull. AMS* 1958 memorial, Britannica);
3. records every fact as `{id, text, source ids, quote?}` in `demo-content/ulam/sources.json` and
   `demo-content/ulam/facts.json`;
4. confirms each image's licence on its Commons page and records it in `CREDITS.md`.

Lessons may only state facts from `facts.json`. The seeder test asserts that every `[n]` in the Ulam
texts resolves to an entry there.

| Module | Topics | Interactive | Learning element | Quiz focus |
|---|---|---|---|---|
| 1. Lwów | Life to 1935 (facts 1.1–1.4); the city and its universities | `lwow-map` (`lwow`) | Timeline: 1909 → 1927 → 1929 → 1933 → 1935 | Multiple choice (avoid the disputed birth day and advisor) |
| 2. The Lwów School and the Scottish Café | Banach, Steinhaus and the school (2.1–2.6); the café and the book (3.1–3.10) | `scottish-book` (`intro`, then the problems) | FlipCards: the people (Banach, Steinhaus, Mazur, Schauder, Ulam), each with sourced facts; drawn portraits, no Polish-PD photos | Matching problem → poser; true/false on the goose |
| 3. America and the war | Princeton, Harvard, Madison (1.5–1.7); the family's fate (1.11, MacTutor's words) | `lwow-map` (`princeton` … `los-alamos`) | Callout: the disputed dates and why sources differ | Multiple choice |
| 4. Monte Carlo | The solitaire question, von Neumann, ENIAC, the name (4.1–4.5) | `monte-carlo` (all steps) | PracticeActivity: "estimate the error after N throws" (hints; worked solution) | Numerical (π estimate from given counts, tolerance 0.01) |
| 5. Los Alamos, briefly: the Teller–Ulam design | 5.1–5.4, about 200 words, factual | none | Callout | One multiple choice (date of the first test) |
| 6. Patterns: the spiral and cellular automata | 6.1–6.3 and 7.1–7.2 | `spiral`, `automaton` | Steps: build a spiral by hand | Multiple choice and true/false |
| 7. Fermi–Pasta–Ulam–Tsingou | 8.1–8.6, with Mary Tsingou's role | `automaton` (reused only for the "computer experiment" idea); no FPUT simulation in v1 | Timeline: 1953–1955 → 1965 → 2008 | Matching |
| 8. Legacy and the name | Borsuk–Ulam, Hyers–Ulam, Project Orion and the "singularity" passage (only cleared items, 9.x); why the product is called ulams (the tribute sentence) | `lwow-map` (`boulder`, `santa-fe`) | FlipCards | True/false |
| Final test | — | — | — | 14 questions |

**Images** (confirmed per file in M9a): the Los Alamos ID badge photo (PD-USGov-DOE, credit LANL),
Ulam with the FERMIAC (PD, LANL), the café building at 27 Shevchenka Avenue (CC BY-SA 4.0, Rbrechko)
and a Scottish Book page (CC BY-SA 3.0, Stako). Photos are downloaded once by `scripts/fetch-images.mjs`
into `demo-content/ulam/images/` (each under 300 KB, WebP) with their Commons URL in `CREDITS.md`.
Every image is shown with its credit line.

### 7.4 Learning elements: how they are written

Layout documents are hand-written PHP arrays in each `DemoExperience`, validated by the
`LayoutTopic` rules at seed time (a test seeds each demo into a test tenant and asserts no fallback
was used). PracticeActivity documents must fill every scaffolding slot (intro, toolbox, challenges,
hints, feedback, worked solution), as its schema requires.

---

## 8. Demo tenants and landings

### 8.1 Tenants

| Slug | Name | Preset | Accent | Course(s) |
|---|---|---|---|---|
| `gravity` | Gravity Lab | `gravity` | `#3DD6F5` | How gravity shapes the solar system |
| `poland` | Poland, Measured | `poland` | `#C8102E` | Poland, measured (en); Polska w liczbach (pl) |
| `ulam` | The Scottish Book | `ulam` | `#1D3B8F` | Stanisław Ulam and the Lwów School of Mathematics |

**Create** with `php artisan ulams:tenant:create gravity --name="Gravity Lab" --theme=gravity
--accent="#3DD6F5" --demo=on`, and similarly for the other two.

**Users.** The standard demo users (`admin@<slug>.ulams.app`, `tutor@…`, `student1..5@…`, password
`TENANT_DEMO_PASSWORD`). Course people (`people()`): one tutor each, a fictional name with an initials
avatar, as in the other demos. There are no real people as tutors. In particular, Ulam's name is never
used as an account.

**Free.** `commerce()` is a no-op, and `courseFields()` sets `public: true`. No webinars or events.

### 8.2 Theme presets

Three new presets, `gravity`, `poland` and `ulam` (named after the slug, like the existing three):

| Preset | Background | Text | Surface | Accent | Fonts (self-hosted via Astro `<Font>`) | Character |
|---|---|---|---|---|---|---|
| `gravity` | `#05070F` | `#E8ECF5` | `#0E1424` | `#3DD6F5` (gold `#FFB547` as secondary) | Space Grotesk (headings), Inter (body), JetBrains Mono (numbers) | Dark space, starfield `background-image` (like nightsky but calmer), thin orbit rules |
| `poland` | `#F4EFE6` | `#1B2A3A` | `#FBF8F2` | `#C8102E` (teal `#5E8C8A` as secondary) | Source Serif 4 (headings), IBM Plex Sans (body), tabular numerals | Cartographic paper, graticule hairlines, legend-style badges |
| `ulam` | `#F7F3E8` with a 5 mm squared-paper grid (`linear-gradient` background) | `#1E2230` | `#FFFDF7` | `#1D3B8F` (marginal red `#B23A2E` as secondary) | EB Garamond (headings), IBM Plex Sans (body), IBM Plex Mono (formulas) | Archival notebook, ruled margin, paper-corner frames |

- The new web fonts (Source Serif 4, IBM Plex Sans and Mono, EB Garamond, Inter) are all SIL OFL 1.1,
  self-hosted through Astro's `<Font>` like Comfortaa and Quicksand for nightsky (section 14).
- `front/ui/tests/contrast.test.ts` must pass for each preset: text on background and on surface at
  4.5:1 or more; accent as a UI colour at 3:1 or more; `primary-on-light` at 5:1, computed by
  `accentCss()`.

### 8.3 Landings (`front/web/src/docs/{gravity,poland,ulam}.json`)

These are built only from existing catalogue components. New variants are added where the look needs
them:

| Landing | Sections (component, variant) |
|---|---|
| gravity | SiteHeader; Hero (**new variant `cosmos`**: a full-bleed live `InteractiveLesson` in background mode on the landing, using the gravity package's `solar-system` step, `chrome: none`, no tracking, with a poster fallback); FeatureList (tiles: "Explore the live simulation", "Read the short explanation", "Check yourself"); Syllabus (**new variant `orbits`**: modules on concentric rings, with a list fallback on phones); Badges (Free, Certificate, 9 modules, about 4 h); FeatureList (checks: data sources, with citation chips to NASA/JPL); Faq; CtaBand; SiteFooter with "Simulation: GPL-3.0, source on GitHub" |
| poland | SiteHeader (with an EN/PL link pair to the two course pages); Hero (**new variant `atlas`**: the poland package in background mode at `hero`, with a poster fallback); FeatureList (tiles: map, charts, quizzes); Syllabus (**new variant `atlas`**: an atlas table of contents with chapter numbers); Badges (Free, EN/PL, Certificate); FeatureList (checks: sources as footnote chips); Faq; CtaBand; SiteFooter (with the map data credits) |
| ulam | SiteHeader; Hero (**new variant `notebook`**: the `spiral` package inline on the right page, with a poster fallback); FormatGrid (the five interactives); Syllabus (**variant `timeline`**, as oncall uses, restyled by the preset); Quotes (**only verbatim, sourced quotes from `facts.json`**, with the source shown; omit the section if none is cleared); FeatureList (checks: sources); Badges; Faq (including "Why is the product called ulams?"); CtaBand; SiteFooter |

- `Page.props.lang` is `en` for all three. The poland landing has a link to `/courses/<pl-id>`
  labelled "Polski".
- The **landing hero interactives** use a new public endpoint, `GET api/interactive/showcase` (no auth,
  `public` courses only, throttled). It returns `{url, steps, posters}` for the first interactive topic
  of the first public course, with no tracking. It gets a tenant isolation test, a policy (public),
  OpenAPI docs and a CSP check.
- Design references: `front/docs/design/stitch/interactive-demos/` (prompts saved; the screens timed
  out on 2026-10-09 and can be regenerated from `PROMPTS.md`).

### 8.4 Per-demo copy in `front/web`

- `page-docs.ts`: `SYLLABUS_VARIANT` `{gravity: "orbits", poland: "atlas", ulam: "timeline"}`,
  `HERO_VARIANT` `{gravity: "cosmos", poland: "atlas", ulam: "notebook"}`, `LESSON_NOUN` `{gravity:
  "Module", poland: "Chapter", ulam: "Notebook"}`.
- The finish page headlines in `pages/learn/[courseId]/finish.astro`:
  - gravity: "Escape velocity reached";
  - poland: "Measured and done" / "Zmierzone";
  - ulam: "Problem solved — no goose required".

### 8.5 Certificates

Add `gravity`, `poland` and `ulam` to the `themes` object in `api/pdf/scripts/certificate-templates.mjs`
and run it to generate `api/packages/templates-pdf/resources/pdfme/certificate-{gravity,poland,ulam}.json`.
Add the keys to `CertificateTemplates::THEMES`. Only bundled fonts are used:

- gravity: SpaceGrotesk-Bold + JetBrainsMono-Regular;
- poland: PlayfairDisplay-Bold + NotoSans-Regular;
- ulam: PlayfairDisplay-Bold + JetBrainsMono-Regular.

All of them cover Polish diacritics, which "Stanisław" and the PL course need. Extend
`api/pdf/test/render.test.ts`'s `it.each` to the three keys.

---

## 9. Checklist: every place a new demo and preset touches

From grepping `nightsky` on `main` (two explorations agree). Each new slug and preset is added to all
of these in M7.

**API and seeding**
- `api/database/seeds/DemoCoursesSeeder.php` (`EXPERIENCES`, the error message)
- `api/database/seeds/Demo/{Gravity,Poland,Ulam}Experience.php`
- `Demo/Art/{Gravity,Poland,Ulam}Art.php`
- `Demo/content/{gravity,poland,ulam}/*.md`
- `Demo/Support/TopicFactoryHelper.php` (`TYPES`)
- `api/makefile` (`demo-seed-tenants`, `demo-mode-on`)
- `api/packages/demo/README.md`, `src/Console/SeedDemoCommand.php` (help),
  `tests/Mocks/FakeDemoCoursesSeeder.php`
- `api/packages/tenancy/src/Console/CreateTenantCommand.php` (`--theme` help) and its tests
- `api/packages/course-builder/src/Pipeline/BriefService.php` (`THEMES`), `src/Apply/SiteTheme.php`,
  `resources/schemas/course-brief/v2.json` (`preset` enum), `tests/Feature/BriefV2Test.php`
- `api/packages/templates-pdf/src/Pdfme/CertificateTemplates.php`, `README.md`;
  `api/pdf/scripts/certificate-templates.mjs`, `api/pdf/fonts/README.md`, `api/pdf/test/render.test.ts`
- `api/h5p/test/tenancy.test.ts` (only if it enumerates slugs)
- `api/tests/Integrations/DemoPricesTest.php` (free courses: expect no products)

**front/ui**
- `src/registry.ts` (`THEMES`, the new Hero and Syllabus variants)
- `src/theme/presets.ts`
- `src/styles/themes/{gravity,poland,ulam}.css`
- `src/components/{SiteHeader,Showcase,Hero,Syllabus}.astro`
- `tests/{contrast,theme-presets,builder-fixtures,brief-editor}.test.ts`
- `catalogue/examples/{Showcase,WorkflowShowcase,Hero,Syllabus}.json`

**front/web**
- `src/docs/{gravity,poland,ulam}.json`
- `src/lib/theme.ts` (`themeFor` known list, `THEME_COLOR`, `DEMO_TENANTS`)
- `src/lib/accent.ts` (`THEME_BACKGROUND`)
- `src/lib/page-docs.ts`
- `src/lib/platform.ts` (`STYLE`, the theme check)
- `src/lib/config.ts` and `.env.example` (`ULAMS_DEMO_TENANTS`, `ULAMS_WARM_TENANTS` defaults: all six)
- `src/layouts/Base.astro` (theme CSS and fonts)
- `src/components/LessonPlayer.astro` (`lessonNoun`)
- `src/pages/learn/[courseId]/finish.astro`
- `src/pages/studio/s/[id]/frame/[...page].astro`
- `tests/e2e/{smoke,a11y,csp}.spec.ts`, `tests/perf/measure.mjs`
- `tests/fixtures/{gravity,poland,ulam}.json`, `tests/unit/{fixtures.ts,docs.test.ts,accent.test.ts,view-model.test.ts,server.test.ts}`
- `README.md`

**Other**
- `front/sdk/src/course-builder.ts` (preset union)
- `front/cli/src/commands/tenants.ts` (help)
- `front/docs-site/src/pages/catalogue/preview/[component].astro` (theme CSS)
- **Legacy React front: not extended.** `themeFor` there falls back to coffee. A note in
  `front/src/lib/components/theme/README.md` says the three new demos are `front/web` only.

---

## 10. The summary deliverable (M10)

- **Platform landing.**
  - `ULAMS_DEMO_TENANTS` defaults to `coffee,oncall,nightsky,gravity,poland,ulam`.
  - `STYLE` gets the three new cards:
    - gravity: label "3D simulation", text "A real-data solar system you learn inside";
    - poland: label "Map and charts", text "35 years of Poland in cited public data, in English and Polish";
    - ulam: label "Mathematics and history", text "Ulam, the Lwów School and the Scottish Book, with five live interactives".
  - Facts show "Free" instead of a price.
  - The `#demos` grid shows 6 cards with a 3×2 layout on desktop and 1 column on phones.
  - `smoke.spec.ts` expects 6.
- **One-click login.** Each card keeps "Open as learner" (front auto-login) and "Open as admin" (admin
  demo login). No new mechanism.
- **Make targets** (`api/makefile`):
  - `demo-seed-tenants` adds the three new `$(MAKE) demo-seed-tenant DOMAIN=<slug>.localhost
    EXPERIENCE=<slug>` lines.
  - `demo-mode-on` adds the three `ulams:tenant:create <slug> --demo=on` lines.
  - New target `demo-create-tenants`: the six `ulams:tenant:create` commands with names, themes and
    accents, so a fresh install is one command.
  - `demo-seed` (platform tenant, `EXPERIENCE=all`) seeds six courses (seven with PL) into the
    platform tenant.
- **README** (`### Demo tenants`): six rows, the create commands, the make targets, and a note that
  gravity is built once (`yarn workspace @ulams/demo-gravity package`, needs Chromium for the posters).
- **Docs site.**
  - `getting-started/demos.mdx`: six academies, one section each.
  - `getting-started/quick-start.mdx` and `developers/local-development.mdx`: the targets.
  - `admin/demo-mode.mdx`: six tenants.
  - `admin/settings.mdx` and `admin/tenants.mdx`: theme lists.
  - `learners/home.mdx` and `learners/course-page.mdx`: theme lists.
  - `creators/interactive.mdx` and `creators/layout.mdx` (from M2 and M6).
- **Design brief.** `front/docs/design/experiences.md` gains sections 6–8 for the three experiences
  (concept, course content, lesson table, quiz, landing content, prompts), in its existing format.

---

## 11. Milestones (small PRs, in this order)

Dependencies: M1 → M2. M3, M4 and M5 need M1 (the bridge) and can run in parallel. M6 is independent.
M7 needs M2 and M6 (for the hero variants with InteractiveLesson). M8 needs M2–M4, M6 and M7. M9 needs
M5–M7. M10 needs M8 and M9.

### M1: Bridge and Interactive API (2 PRs)

- **M1a** `feat(interactive-bridge): ulams-ix v1 protocol and library`
  - Files: `front/interactive-bridge/**` (section 3.5) and the root workspace entries.
  - Tests: schema validation, guards and the nonce, size and type rejection, the queue before
    `init`, the standalone no-op, and the bundle size under 3 KB.
  - DoD: `corepack yarn turbo run typecheck build lint test --filter=@ulams/interactive-bridge` passes.
- **M1b** `feat(interactive): Interactive topic type, package library and content origin delivery`
  - Files: `api/packages/interactive/**` (section 3.3) plus the app wiring, the uploads config, both
    Caddyfiles, `ContentFileController` and `.github/workflows/ci.yml`.
  - Tests:
    - package upload: valid, bad manifest, traversal, a PHP file, too big, wrong licence id, a missing
      step text for a locale;
    - versions are immutable; the 409 on delete;
    - topic create and update with step validation; pinned versus latest;
    - launch: access gate, 503 without an origin, 404 when disabled, `IN_PROGRESS` created;
    - events: each completion rule; out-of-range steps do not complete; best score kept; throttle;
      batch limit; schema rejection;
    - CSP per version: no `unsafe-eval`, `connect-src` with and without `allow_network`,
      `frame-ancestors`;
    - `ContentFileTest` serves `interactive/*` only with the header;
    - `ContentOriginHeadersConfigTest` updated (snippet `header ?`, prefix list, the `@tracking` string
      unchanged);
    - export/import round trip;
    - the cross-tenant isolation test for every route;
    - the token scope map coverage.
  - DoD: the `interactive`, `uploads`, `courses` and `tenancy` suites are green, and the OpenAPI
    snapshot (if L0-11 has landed) is updated.

### M2: Learner, admin, CLI and docs surfaces (3 PRs)

- **M2a** `feat(web): InteractiveLesson with background mode and accessible fallbacks`
  - Files: the SDK changes, `front/ui` (component, element, example, registry, `Node.astro`), and
    `front/web` (`data.ts`, `page-docs.ts`, `LessonPlayer.astro` immersive variant, `bff.ts`,
    `view-model.ts`, `progress.ts` `external` mode).
  - Fixture: `front/web/tests/fixtures/interactive/minimal/` (a 30-line package using the bridge,
    served by a throwaway server like `content-frames.spec.ts`).
  - Tests:
    - unit: the host validation, event batching, reduced-motion poster, WebGL fallback, ready timeout;
    - `registry.test.ts` and `frame-components.test.ts` pass;
    - Playwright `interactive-frame.spec.ts`: an opaque origin (the frame cannot read
      `document.cookie` or `localStorage`, `fetch` to the API fails); `goToStep` round trip;
      completion reaches `<ulams-progress>`; background mode keyboard flow (Tab into the stepper,
      "Explore freely", Escape back); axe on both modes with `reducedMotion: "reduce"` and without.
- **M2b** `feat(admin): interactive package library and topic editor`
  - Pages, routes, access, editor and strings.
  - Tests: an admin Jest test for the editor's step selects from a manifest (if admin Jest runs for
    that folder; otherwise a typed unit test of the helper).
- **M2c** `feat(cli): topics create-interactive and the interactive nouns; docs pages`
  - The CLI (section 3.7), `creators/interactive.mdx`, `developers/interactive-bridge.mdx`,
    `operators/content-origin.mdx` and `LICENSING.md`.
  - Tests: CLI unit tests, docs coverage.

### M3: Gravity package (1 PR here; amended 2026-10-09)

`feat(demo-content): gravity content package`, branch `feat/demo-packages`.

- Files: `demo-content/gravity/**` (section 6.1), `demo-content/{README.md,package.json,scripts/**,tests/**}`,
  the root workspace entries, the lint wiring, `LICENSING.md`, ADR 0088 (amended) and the docs page.
- Tests:
  - `demo-content` unit tests (the boundary lint with a violating fixture, the manifest rules) and gravity
    unit tests (44 steps, English and Polish text, no network references, guarded storage, nothing left out
    by the owner is present);
  - Playwright on a throwaway host and content origin (`demo-content/tests/e2e/gravity.spec.mjs`): the zip
    is valid; the package loads in the opaque sandbox and says `ready` with 44 steps; `goToStep` produces
    `stepChanged` for every step; a range limits navigation; no request leaves the origin; keyboard-usable
    controls; `webgl-unavailable`; axe (WCAG 2.2 AA) on the page and on the package alone.
- The package is uploaded to the local coffee tenant with `ulams topics create-interactive` to try it.

### M4: poland package (1 PR)

`feat(demo-content): poland map and charts package (EN/PL)`.

- Covers section 6.2, the `demo-content` workspace (section 6.4) and `demo-content/shared/atlas.js`.
- Tests:
  - `demo-content` lint: manifests, bridge copies, `check.mjs` (no essay or HN references, every
    step's source ids resolve, topo under 400 KB, package under 3 MB);
  - a Playwright smoke run reusing the M2a fixture host: `ready`, steps in EN and PL, reduced motion
    stops the animation loop, no external requests.
- DoD: licence pending #148, merged with the default if there is no answer at merge time and the owner
  has approved #146.

### M5: Ulam interactives (5 small PRs, one per package)

`feat(demo-content): ulam spiral`, `… monte-carlo`, `… automaton`, `… scottish-book`, `… lwow-map`.

- Each PR holds the package, its manifest, posters, unit tests of the pure logic (sieve and spiral
  coordinates; π estimate and seeded PRNG; rule application and Life step; score; route data), a
  Playwright keyboard-only run, and axe on the package page.
- `scottish-book` and `lwow-map` contain only placeholder text until M9a clears the facts. Their data
  files are filled in M9b.

### M6: Layout topic type, rendering only (1 PR)

`feat(topic-type-layout): Layout topic type rendered from the learner catalogue`.

- Covers section 5.
- Tests:
  - API: create and update with a valid and an invalid document, the fallback required, export, the
    tenant isolation of the topic API (existing), and the manifest copy check;
  - web: `topicDoc` layout case, the invalid document falls back to Prose, PracticeActivity
    completion, axe.

### M7: Tenants, presets, landings and certificates (2 PRs)

- **M7a** `feat(ui): gravity, poland and ulam theme presets`
  - CSS, presets, fonts, the contrast tests, the new Hero and Syllabus variants (`cosmos`, `atlas`,
    `notebook`, `orbits`, `atlas`) with catalogue examples, and the docs-site preview CSS.
- **M7b** `feat(demo): three demo tenants with landings and certificates`
  - Section 9 checklist (API, front/web, other) for the three slugs.
  - Skeleton `DemoExperience` classes with one module each, so the tenants seed.
  - The landing JSONs, the `GET api/interactive/showcase` endpoint (with policy, isolation test and
    OpenAPI), the certificates and the make targets.
  - Tests: `docs.test.ts` (6 landings), the e2e `smoke`, `a11y` and `csp` specs with the three
    tenants, `render.test.ts` for the three certificates, `CreateTenantCommandTest` for the presets,
    and `ResetDemoCommandTest` for one new tenant.

### M8: Gravity and poland course content (4 PRs)

- **M8a** `docs(demo-content): gravity fact check`
  - `demo-content/gravity/FACTCHECK.md` and `sources.json`, plus an upstream issue or PR to the
    gravity repo for any wrong number.
- **M8b** `docs(demo-content): poland source check`
  - The cleaned `sources.json` and `FACTCHECK.md`, applied to the package.
- **M8c** `feat(demo): Gravity Lab course`
  - `GravityExperience` in full: modules, interactive topics, layouts, quizzes, the final test,
    `course-description.md`, the art and the certificate.
- **M8d** `feat(demo): Poland, measured (EN) and Polska w liczbach (PL)`
  - `PolandExperience`, which seeds two courses from one table.
- Tests (each content PR):
  - `DemoCoursesSeederTest`-style feature test seeding the experience into a test tenant: counts per
    module, every GIFT question parses as its declared type, every layout validates (no fallback),
    every `[n]` resolves, every interactive topic's steps exist in the manifest, and the course is
    `public`;
  - Playwright: open the course as the demo student, complete one interactive topic through the
    bridge, pass one quiz, axe on the lesson pages.
- DoD: the owner (or a delegate) has read the course against `FACTCHECK.md` (ADR 0089, rule 6).

### M9: Ulam research and course (2 PRs)

- **M9a** `docs(demo-content): Ulam fact sheet, sources and image credits`
  - The research step of section 7.3. Output: `demo-content/ulam/{facts.json,sources.json,CREDITS.md,images/}`.
  - The PR description lists every ⚠ item and how it was resolved.
  - The owner reviews (#151).
- **M9b** `feat(demo): The Scottish Book course`
  - `UlamExperience`, plus the data for `scottish-book` and `lwow-map`.
  - Tests: as in M8, plus "every fact id used is in `facts.json`" and "every image has a credit line".

### M10: Six demos (1 PR)

`feat(web): six demo academies on the platform landing; docs and make targets`.

- Covers section 10.
- Tests:
  - `smoke.spec.ts` expects 6 demo cards, each with a learner link that loads a lesson and an admin
    link;
  - `make demo-create-tenants demo-seed-tenants` on a clean stack, run once and recorded in the PR;
  - `docs` coverage.
- DoD: the TODO items in section 17 are ticked or noted.

---

## 12. Decisions taken (to confirm)

Each line gives the default the plan uses and, where there is one, the issue that holds the question.

1. **Interactive is a new topic type**, a versioned zip package with a manifest on the content origin,
   in an opaque sandbox with a CSP set per version (ADR 0086; #146).
2. **On by default**, with a per-tenant switch; network off unless both the tenant setting and the
   manifest allow it (#150).
3. **Bridge protocol `ulams-ix` v1** and the MIT library `@ulams/interactive-bridge`, vendored as one
   file until npm publishing (#77) (ADR 0087; #146).
4. **No tracking token for interactive packages.** Progress goes through the BFF with the learner's
   session (ADR 0086).
5. **New topics pin the current package version.** Upgrading is the author's action.
6. **Gravity (amended 2026-10-09):** the owner's own code, MIT, adapted in `demo-content/gravity/` in this
   repository; no download and no checksum; the music, the Moon photograph and the zh translation are
   left out; `stopped-galaxy` is included (ADR 0088; #147).
7. **poland:** a cleaned copy in `demo-content/poland/`; the map is regenerated from world-atlas /
   Natural Earth; the essay framing and weak sources are removed; two courses EN/PL; no education
   module (ADR 0088, 0089; #148, #149).
8. **Content licence:** MIT for code, CC BY 4.0 for text and data in `demo-content/poland` and
   `demo-content/ulam` (#148).
9. **The Layout topic type is built now**, rendering only (ADR 0052; #146).
10. **Presets are named after the slugs** (`gravity`, `poland`, `ulam`), as the existing three are
    (ADR 0089; #146).
11. **The Lwów interactive** is a journey map plus a schematic inset, with no historical basemap (#146).
12. **The Ulam course** uses only facts from the reviewed fact sheet. Disputed facts are taught as
    disputed or left out. Polish-law PD photos are not used (#151).
13. **The legacy React front is not extended** with the three new demos.
14. **Hero interactives on the landings** use a public, untracked showcase endpoint limited to public
    courses.

---

## 13. Tests (summary)

| Kind | Where |
|---|---|
| PHPUnit, new suites | `interactive`, `topic-type-layout` |
| PHPUnit, updated | `uploads` (`ContentFileTest`), `tests/Integrations/ContentOriginHeadersConfigTest.php`, `core` (`EnforceTrustedOriginTest`, unchanged list), `tenancy` (isolation, create command), `demo` (reset, fake seeder), `templates-pdf`, `auth` (token scope map) |
| Tenant isolation | Every route in section 3.3, `GET api/interactive/showcase`, and the Layout topic through the existing topic API test |
| Sandbox and CSP | `front/sdk/tests/frames.test.ts` (`SANDBOX_INTERACTIVE`), `front/ui/tests/frames.test.ts` (no `allow-same-origin` in `InteractiveLesson`), `InteractiveContentOriginTest` (headers per version), `ContentOriginHeadersConfigTest` (Caddy), Playwright `interactive-frame.spec.ts` (opaque origin, no network, nonce) |
| Bridge | `front/interactive-bridge/tests/*` |
| Packages | `demo-content` lint, the per-package unit tests, Playwright smoke per package (keyboard only, reduced motion, no external requests) |
| Per demo (Playwright) | `smoke.spec.ts` `TENANTS` gains gravity, poland and ulam with `kinds: ["InteractiveTopic","LayoutTopic","GiftQuiz"]`; `a11y.spec.ts` `PAGES` gains landing, course, an interactive lesson (background and inline) and a layout lesson per new tenant; `csp.spec.ts` gains the three tenants |
| Accessibility | axe (WCAG 2.2 AA tags) on every new page type and on the five Ulam packages; manual keyboard pass recorded in the M2a and M5 PRs |
| Certificates | `api/pdf/test/render.test.ts` for the three themes |

---

## 14. New dependencies

| Package | Licence | Where | Why | Maintenance and size | Self-hosting impact |
|---|---|---|---|---|---|
| `@ulams/interactive-bridge` (new, ours) | MIT | `front/interactive-bridge` | The protocol on both ends | ours; under 3 KB | none |
| `esbuild` (if not already in the lockfile) | MIT | dev only, bridge bundle | One-file ESM build | very active | none |
| `world-atlas@2.0.2` | ISC (data: Natural Earth, public domain) | dev only, `demo-content` | Regenerate the poland map | stable (2020), data only | none |
| `topojson-client@3.1.0`, `topojson-server@3.0.1`, `topojson-simplify@3.0.3` | ISC | dev only, `demo-content` | Filter, simplify and re-quantise the topology (the output is committed) | stable | none |
| `@fontsource/barlow`, `@fontsource/barlow-semi-condensed`, `@fontsource/jetbrains-mono` | MIT (code) + SIL OFL 1.1 (fonts) | dev only, `demo-content/poland` (the woff2 subsets are committed) | The poland package's own fonts, self-hosted | very active | none |
| Fonts: Inter, Source Serif 4, IBM Plex Sans and Mono, EB Garamond | SIL OFL 1.1 | `front/web` via Astro `<Font>` (self-hosted) | The three presets | Google Fonts / upstream | about 40 KB per subset; no external requests |
| `@fontsource-variable/inter`, `@fontsource/roboto-mono` | MIT (code) + SIL OFL 1.1 (fonts) | `demo-content/gravity` | Self-hosted fonts in the package | very active | none |
| `three@0.184`, `vite@8`, `typescript@6`, `@types/three` | MIT / Apache-2.0 | `demo-content/gravity` (the simulator and its build) | The simulator is the owner's Three.js app | very active | none; dev and build only except Three.js, which ships inside the package |
| `sharp` | Apache-2.0 | `demo-content` (posters to WebP, dev only) | Converts Chromium screenshots to WebP | very active; already in the lock file | none |
| The gravity package zip | MIT (Earth texture CC BY 4.0) | built from `demo-content/gravity`, git-ignored | The demo content | ours | about 1.6 MB per tenant on the package disk |

No PHP dependencies: `opis/json-schema` (already present) validates the manifest and the layout
documents. No GPL or AGPL code is linked into the API or bundled into the admin or front (the gravity package is MIT since 2026-10-09).

---

## 15. Risks

| Risk | Mitigation |
|---|---|
| **Content packages leaking into the product** (an import of a `demo-content` file by a workspace, or a bundled copy) | ADR 0088 layout; the boundary lint; packages are played only as sandboxed content |
| **Third-party content in poland** (the article, its map, the essay framing) | Map regenerated; `check.mjs` bans the strings; the essay links and the chapter titles removed; `reported_in_uploaded_document` sources removed |
| **Image rights** (Ulam photos, Polish PD) | Per-file Commons check in M9a; Polish-law PD not used; drawn illustrations as the fallback; credits shown |
| **Factual accuracy** (dates, quotes, statistics) | Fact sheets with citations, review by the owner (#151), ⚠ items taught as disputed, a seeder test that every `[n]` resolves, the reference period stated for every statistic |
| **Gravity has no reduced-motion or keyboard support for the 3D view** | Posters plus text alternatives; the adapter slows time under reduced motion; the stepper is outside the frame |
| **WebGL unavailable** (old devices, some VMs, CI) | `requires: ["webgl"]`, the poster fallback, `error` from the adapter; Playwright runs with SwiftShader (Chromium default) |
| **Opaque origin breaks apps** that assume storage | The adapter guards storage; the docs page lists the constraints; the admin preview shows errors from the frame |
| **The same-site content origin** (ADR 0014 amendment) | Not relevant to this type: the opaque sandbox removes same-site cookie and storage access |
| **Seeding builds gravity** (needs Chromium for the posters) | The seeder uses the cached zip when present; the build script prints what is missing |
| **The hourly reset re-uploads packages** (about 1.5 MB gravity, under 3 MB poland) | The seeder reuses the cached zip; uploads take seconds; storage is wiped with `--wipe-files` |
| **Political sensitivity** (poland security chapter, Ulam and the H-bomb) | Figures only, primary sources, no commentary; the Teller–Ulam section is brief and factual (ADR 0089) |
| **The stated step count drifts** (43 or 44) | The course uses step ids, never numbers; the manifest is generated from `STEPS` |

---

## 16. Architecture decision records (Proposed)

| ADR | Title | Milestones |
|---|---|---|
| 0086 | Interactive topic type: author-uploaded JavaScript packages in an opaque sandbox on the content origin | M1, M2 |
| 0087 | The `ulams-ix` bridge protocol and the `@ulams/interactive-bridge` library (MIT) | M1 |
| 0088 | Content packages under `demo-content/`, played only as sandboxed content; gravity relicensed MIT by its owner (amended 2026-10-09) | M3, M4, M5 |
| 0089 | Six demo academies: three free interactive courses, one theme preset each, sourced content, EN/PL as two courses | M7–M9 |

ADR 0052 (Layout topic type, Proposed) is implemented in part by M6 and gets no new record.

---

## 17. TODO changes

`(new)` items this plan adds to `docs/ROADMAP-TODO.md`:

- **Phase 1, new subsection "1.5 Interactive packages (new)":**
  - the Interactive topic type (ADR 0086)
  - the bridge (ADR 0087)
  - admin, CLI and MCP
  - the docs
- **Phase 2.7, "Learner layouts":** the Layout topic type, rendering only (ADR 0052, M6).
- **Phase 5:**
  - three new demo academies (gravity, poland, ulam) with free courses
  - six demos on the platform landing
  - the `demo-content/` licensing boundary (ADR 0088)
- **Open decisions:** #146–#151.
