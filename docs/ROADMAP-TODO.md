# ULAMS roadmap: TODO

AI-native headless LMS on Wellms (Escola LMS). Differentiator: **courses that stay in sync with
their sources ("Living Course") and adapt to each learner**, with every element cited.

Full spec for Claude Code: `docs/ROADMAP-PROMPT.md`. Working rules: `CLAUDE.md`. Run sessions with
"Read docs/ROADMAP-PROMPT.md and start Phase N". Every phase: explore → plan → **approval** →
small commits → tests → summary.

---

## Decisions made

- [x] (2026-10-09) Phase 3 plan (`docs/plans/phase-3.md`) approved with all 17 decisions of its section 18 as recommended (issue #27 closed)

- [x] (2026-10-09) Phase 1 decisions 1–52, Phase 2 decisions 1–28 and the Phase 3 plan with its 17 decisions
      confirmed by the product owner
- [x] (2026-10-09) Brand identity "Orbital Folio" chosen by the product owner (indigo #0F2B46, orange #FF7A2E);
      logo drawn as SVG, applied to platform and product surfaces, not to tenants; orange is an accent only
      (ADR 0038, Proposed; #25). A trademark check on the name and mark is still recommended before launch
- [x] (2026-10-09) ADRs 0013–0034 accepted
- [ ] (2026-10-09) Post-Phase 2 bug batch: ADRs 0063–0070 proposed, awaiting acceptance (tenant AI settings,
      studio applied state, tutor demo login, APP_KEY, quiz time limit key, scheduler lock, CI scope, admin on Node 24)
- [x] (2026-10-09) Phase 1 defaults confirmed: students get `scorm_track-update`; SVG served as attachment
      with CSP (no sanitiser); LTI Instructor → tutor, never admin, no e-mail account linking; LiaScript player
      fetched at image build time; production content origin on a separate registrable domain
- [x] (2026-10-09) Production content origin is a same-site subdomain (`{slug}.content.ulams.app`), not a
      separate domain (supersedes the earlier default); mitigations shipped, ADR 0014 amended
- [x] (2026-10-09) GHCR images are public; the upstream EscolaLMS security reports stay as public issues
- [x] (2026-10-09) Replace the illustrative incident log on the On-Call landing with real course content
- [x] (2026-10-09) Phase 1 and Phase 2 plans approved; ADRs 0008 (reference frontend: Astro SSR, plain TS SDK,
      agent UI catalogue), 0009 (LLM layer), 0010 (Course Blueprint), 0011 (AG-UI over SSE) and 0012 (LTI 1.3)
      accepted
- [x] Base: Wellms (Laravel, `escolalms/*` packages), headless
- [x] Killer feature: **Living Course** (source sync with diff + citations, progress preserved)
- [x] Second pillar: personalisation via new `learner-insights` package
- [x] **Do not use `escolalms/recommender`**
- [x] Generative UI: **A2UI v0.9 + own component catalogue, transported over AG-UI**; declarative
      by default, model-written code only in the sandboxed `simulation` component
- [x] Commerce: **Sylius 2.x as a separate headless service** behind one frontend and one admin;
      LMS owns entitlements, Sylius owns catalogue/cart/checkout/taxes/invoices; `CommerceProvider`
      interface; Wellms `payments`/`cart`/`vouchers` retired after migration
- [x] Business model: open core (free self-hosted core; paid cloud, enterprise, support)
- [x] Niche: developer education / customer education for dev tools
- [x] (new) One monorepo `admin/` + `api/` + `front/` (+ `docs/`), all `escolalms/*` packages vendored
      as source, Turborepo + Yarn workspaces (ADR 0001, 0005)
- [x] (new) Rename EscolaLMS / Wellms to ulams in code, config and infrastructure (ADR 0002)
- [x] (new) H5P only in the separate GPL service `api/h5p` (Lumi), embedded via iframe; no GPL code in
      the API or frontend bundles (ADR 0003)
- [x] (new) styled-components replaced by CSS custom properties (`--ulams-*`) (ADR 0004)
- [x] (new) Remove `recommender` from the API composition, not just stop using it (ADR 0006)
- [x] (new) Repository: public `github.com/ulams-dev/ulams`, no AI attribution in history
- [x] (2026-10-09) Build the agent-first `ulams` CLI core now (login and tokens, `ulams api`, main nouns,
      `ulams mcp`); course-as-code after Phase 3. Plan `docs/plans/cli.md` (draft, waiting for approval),
      ADRs 0072–0079 Proposed; open owner questions #74–#79

## Open decisions

- [ ] Final name (favourite **ULAMS**; alternatives Wellam, Monte, Spiral, Automata, UlamOS)
  - [x] GitHub organisation: `ulams` is taken; register `ulams-dev` (fallbacks: `ulams-hq`,
        `ulamslabs`, `ulams-ai`) (note: `ulams-dev/ulams` created and pushed 2026-10-08)
  - [ ] Check domains (ulams.ai, ulams.dev) and trademarks
  - [ ] Check legal aspects of using the Ulam name
  - [x] (new) Copyright of the original EscolaLMS/Wellms code and `scorm-player`: owned by the product owner; admin and scorm-player licensed MIT
- [x] Move MCP server (7.5) right after Phase 2? Cheap to build, strong demo (yes, 2026-10-09: the local
      `ulams mcp` ships with the CLI core; `docs/plans/cli.md`, #73)
- [ ] Move certificates (6.1) earlier if compliance is the priority segment
- [ ] Multitenancy for the POC: one deployment, tenant per subdomain with own theme?
- [ ] Prototype the Sylius order → entitlement flow early (highest-risk commerce piece)
- [x] Add the spec file to the repo as `docs/ROADMAP-PROMPT.md`
- [x] (new) Approve the Phase 1 and Phase 2 plans (`docs/plans/phase-1.md`, `docs/plans/phase-2.md`)
      and ADRs 0009–0011 (LLM layer, Course Blueprint, AG-UI over SSE) (approved 2026-10-09; ADR 0008 and 0012 too)

## Product principles (tie-breakers)

UX over feature count · human approves every AI change (diff) · grounded and cited ·
standards over lock-in · cost-aware (log tokens/cost from day one) · developer-first ·
agent-ready · easy self-hosting · open core.

Market context: buyers rank UX 70%, price 63%, integrations 59%, AI 30%; most-wanted AI feature
is personalisation (65%); trust is the new competitive axis; compliance and extended enterprise
are top buyer needs. Competitor Coursebox already does doc → course; their weakness is generic,
stale content.

---

## Phase 0: Foundation and audit

Plan (new): `docs/plans/leftovers-0-2.md` (draft, waiting for approval; ADRs 0040–0056 Proposed) covers every
open Phase 0, 1 and 2 item as work packages L0-01…L2-24; owner questions #41–#44, #46–#63.

### 0.1 Explore and report (no code)
- [x] Map repo, packages, versions; course → lesson → topic model and topic types (see docs/reports/phase-0-audit.md)
- [x] Report on `headless-h5p`, `scorm`, `cmi5`, `lrs`, `tracker`, `reports`, `payments`,
      `cart`, `vouchers`, `translations`, `settings`, `templates`, `notifications` (see docs/reports/phase-0-audit.md)
- [x] Can `recommender` be safely disabled or removed? What depends on it? (removed from API, admin and front; nothing else depended on it; ADR 0006; webcam-capture leftover tracked in 0.1c)
- [ ] Multitenancy via `gecche/laravel-multidomain`: current setup, dynamic subdomains possible? (partial: dynamic subdomains work via the tenancy package (`ulams:tenant:create`); fixed shared Redis keys, unknown-host fallback and boot-time worker lists; remaining: tenant video queue, per-tenant storage credentials, production DNS/TLS)
- [x] Inventory of learner activity data (tracker, xAPI/cmi5, SCORM CMI, H5P, quizzes, progress,
      logins): storage, granularity, retention, gaps (see docs/reports/phase-0-audit.md)
- [x] How content updates preserve learner progress today (see docs/reports/phase-0-audit.md)
- [x] Tests, CI, code style, queues (Horizon), storage, existing AI code (explored; no AI code exists; the baseline failures (core 6, auth 3) no longer reproduce and the quarantine list `api/phpunit.quarantine.xml` is empty)
- [x] Licence audit of all `escolalms/*` and key dependencies for open core (LICENSING.md and docs/reports/phase-0-audit.md; remediation items below)
- [x] Runtime dependency inventory (input for Phase 8) (see docs/reports/phase-0-audit.md)
- [x] Commerce audit: what Wellms commerce does, dependent flows, Sylius 2.x API coverage,
      Stripe / Przelewy24 gateways, Sylius MCP admin tool, B2B options (see docs/reports/phase-0-audit.md)

### 0.1b Monorepo foundation (new)
- [x] (new) Monorepo with the full history of the three repositories under `api/`, `admin/`, `front/`
- [x] (new) Vendor the 50 PHP packages into `api/packages`; no `escolalms/*` in `composer.json`
- [x] (new) Vendor the JS libraries into `front/src/lib` and `admin/src/lib`; Yarn workspaces + Turborepo
- [x] (new) Rename to ulams, with data migration for existing databases
- [ ] (new) H5P as the isolated Lumi service `api/h5p` (partial: service, Laravel index package, Caddy
      routing and admin/front iframe embedding done; multi-tenant resolver in progress)
- [x] (new) Remove the PHP H5P server completely and replace it with the Node.js service: no
      `h5p/h5p-core`, `h5p/h5p-editor` or `headless-h5p` left in `composer.json`/`composer.lock` or the
      code; Laravel keeps only the read-only `api/packages/h5p` index and HTTP client (ADR 0003)
- [x] (new) Remove `recommender` and its admin/front screens
- [x] (new) Replace styled-components with CSS custom properties everywhere (front, its component
      library and the admin markdown editor; blocked by lint; verified with the visual regression harness)
- [x] (new) Demo content seeder for the three experience courses (`front/docs/design/experiences.md`)
- [x] (new) Root README, AGENTS.md and per-package READMEs for the monorepo
- [ ] (new) Documentation site (Astro Starlight, `front/docs-site`): guides per audience, reference pages generated from the code, every ADR and the roadmap rendered from `docs/`, coverage check over packages, admin routes, learner routes and topic types, GitHub Pages deploy (partial: on branch `docs/starlight-site`, not merged; Pages source and private vulnerability reporting to be enabled)
- [ ] (new) Remaining legacy references (partial: `escolalms/php` replaced by a base built in-repo, ReportBro removed and replaced by pdfme): replace the `escolalms/php` and `escolalms/reportbro-server`
      images, decide on upstream provenance links, reword ADR prose, retarget Docker Hub publishing
      workflows, replace the `ulams.app` placeholder domain, recreate SQL views in pre-rename databases
- [x] (new) Fix `php artisan route:list` (Mattermost client connects in its constructor)
- [x] (new) CI (partial: root `ci.yml` with path filters, PHP shards, licence guards and Dependabot committed; not yet run on GitHub — the branch is unpushed; publishing workflows intentionally dropped): move workflows to the root `.github/` with path filters; drop MySQL services; run Jest
      in admin/front; Dockerfiles build from the repo root
      (done: workflows are on `main` and run on GitHub; admin Jest (3 suites) and the front tests run in the `js` job;
      yarn installs are frozen; the quarantine is empty)
- [x] (new) Remove the non-existent `packages/tracker/src` path from Swagger (done); consider Git LFS for
      large test fixtures; revisit exact pins (`faker-markdown-generator`, `tzsk/sms`) (no LFS: CI rejects new files over 2 MB
      and the 24 MB and 6.8 MB SCORM mocks are generated minimal packages; `faker-markdown-generator` moved to
      `require-dev`; `tzsk/sms` stays `^10.0`; owner confirmation of no LFS pending #48)

### 0.1c Security and audit follow-ups (new)
- [x] (new) Replace the GPL PHP libraries `trax2/framework` (lrs) and `laraveldaily/laravel-invoices` with first-party code
- [x] (new) Payment callbacks must verify the payment with the provider (Stripe signature/status, P24
      verification); RevenueCat off by default and server-verified
- [x] (new) Remove the consultation webcam capture and its unauthenticated upload endpoints (recommender leftover)
- [x] (new) Authenticate the Jitsi recording webhook and restrict the downloaded URL (SSRF)
- [x] (new) Verify JWT signatures in the LRS guard
- [x] (new) Fix the ungrouped `orWhere` in `CourseAccessService::getUserCourseIds` and similar queries
- [x] (new) Remove the tracker Logs screen in admin and other tracker leftovers
- [x] (new) Fix the tenant video processing queue (jobs dispatched to a queue no tenant worker consumes)
- [ ] (new) `Relation::enforceMorphMap` for topic types so class renames never orphan data
- [x] (new) ADR for the tenancy package (docs/decisions/0007)
- [ ] (new) Upgrade PostgreSQL 12 (EOL) to 16/17 with a tested dump/restore path
- [ ] (new) Drop Soketi until realtime is needed (broadcast driver is `log`); Laravel Reverb after 0.2

- [x] (new) cmi5 for learners: give students the cmi5 launch permission and serve AU files from
      object storage (they sit on the local disk that Caddy does not serve) — found by the demo seeders
      (students have `cmi5_read`; `CMI5_DISK` follows `SCORM_DISK`; `cmi5:move-to-bucket` copies old
      packages; AUs play from the content origin in `front/web`; ADR 0046)
- [ ] (new) Containers cannot reach `storage.localhost` (it resolves to the container itself); use the
      internal MinIO endpoint for server-side fetches (e.g. Image topic creation)
- [x] (new) Platform bucket publicly readable by default (`MINIO_DEFAULT_BUCKETS=ulams:download`)
- [x] (new) Demo course seeders for the three experiences (`make demo-seed`, `demo-seed-tenants`)

- [x] (new) Security follow-ups (medium) (done and merged: `auth:api` and `tags_list` on admin tag
      routes, `POST api/images/img` limits and throttle, client payment parameters allow-listed with server
      price/currency/trial values winning, `payProduct` purchasability, vouchers search grouping,
      `GroupTree` depth limit and cycle safety, `_ignition` absent from demo and production images
      (ADR 0071); `POST api/cmi5/fetch` no longer echoes a token: it exchanges a one-time launch token for an
      LRS-only session token (ADR 0046))
- [ ] (new) Stripe: handle the 3-D Secure redirect in the front and document the webhook setup
      (`PAYMENTS_STRIPE_WEBHOOK_SECRET`, `/api/payments-gateways/webhook/stripe`); RevenueCat receipt verifier
      (partial: 3-D Secure redirect in the legacy front and webhook docs done; the RevenueCat verifier is
      obsolete by default, pending owner decision #46)
- [x] (new) Jitsi: confirm the JaaS webhook signature format against the JaaS docs; configure
      `JITSI_RECORDING_HOSTS`
- [x] (new) Drop the unused `analyze_enabled` columns (consultations, webinars) and clean up stored meeting
      frames in tenant buckets
- [x] (new) Remove the Stripe test key committed in `api/docker/envs/*.example` (keys emptied in the six env
      files; rolling the key at Stripe is an owner action, #49)
- [ ] (new) Responsible disclosure: the payment-callback, LRS-token, webcam-upload and course-access issues
      exist in the upstream EscolaLMS packages; notify upstream users
      (partial: notice drafted in `docs/security/upstream-notice.md`; sending it is an owner action, #50)

- [ ] (new) mjml: the `mjml` compose service is not on the `ulams` network and `MJML_API_URL` is not set
      (templates fall back silently); wire it or drop the service
- [ ] (new) Publish images to GHCR (`ghcr.io/ulams-dev/*`, decided 2026-10-08); publish the base image as `ulams/php:8.3` with source offers for its GPL programs (see
      `api/docker/php/NOTICE`)

- [ ] (new) Delete `front/src/style/` (two unused styled-components helpers; excluded from tsconfig,
      eslint and the guard until removed)
- [x] (new) Cart on tenants crashes without a Stripe publishable key (`stripe.tsx` calls
      `stripeKey.includes` on null); show a configuration message instead
- [ ] (new) Yarn install on Node 23 needs `--ignore-engines` (vitest engines); CI pins Node 22
- [ ] (new) Dependency holds (Dependabot ignore rules in `.github/dependabot.yml`): `sharp` 0.35 fails to load
      in the docs-site build on the CI runner (MissingSharp; Astro depends on `sharp ^0.34`); `@ant-design/pro-components`
      2.8.5 to 2.8.10 break admin typecheck (`@ant-design/pro-form` 2.31.5+ declaration files import `src/...`).
      Revisit when Astro supports sharp 0.35 and when a pro-form release fixes its typings
- [ ] (new) ESLint 10 in `admin` (umi lint, fabric config) and `front` (vite, `@typescript-eslint` 7, legacy `.eslintrc`);
      the other five packages are on eslint 10 already. The Dependabot major ignore for `eslint` and `@eslint/js`
      comes off when both move to flat config
- [ ] (new) Replace MinIO with SeaweedFS (or RustFS) and give each tenant its own S3 identity (ADR 0041, plan L0-03)
- [x] (new) `ulams:upgrade`: one idempotent per-tenant upgrade command (plan L0-19) (ADR 0081; the cmi5 and frame steps run once their commands land)
- [x] (new) Fix `Cmi5Policy::delete` checking the read permission (new `cmi5_delete` permission, admins only; plan L0-09)
- [ ] (new) Five packages with `@OA\` annotations are missing from the Swagger scan paths (plan L0-11)

### 0.2 Framework upgrade
- [x] Upgrade plan from Laravel 9 (EOL) to supported Laravel/PHP: order, breaking changes,
      forks/patches needed, risks (docs/plans/phase-0.md: 9 → 10 → 11 → 12 → 13 on PHP 8.4)
- [x] Implement after approval with test suite green at every step
      (steps 1–4 done and merged to main in PR #1 — Laravel 13.35 on PHP 8.4 (Passport 13 with data migration for the
      platform and every tenant, Testbench 11, PHPUnit 12; query cache dropped, `treestoneit/shopping-cart` vendored
      as `api/packages/shopping-cart`, Mattermost Laravel wrapper replaced), no new test failures; see
      docs/plans/phase-0.md B.11–B.14)
- [x] (new) Decide on Passport 13's device-code routes (`oauth/device*`, exposed by default, unused): keep or disable
      (disabled, `e6c21b9e`)
- [ ] (new) Move the `@OA\` docblock annotations (223 files) to PHP attributes and drop the abandoned
      `doctrine/annotations`
- [x] (new) Smaller admin and front images: nginx-unprivileged instead of Apache+PHP, with runtime settings
      injected without PHP (approved 2026-10-09; after Phase 1)

---

## Phase 1: Content formats and integrations

Plan (new): `docs/plans/phase-1.md` (approved 2026-10-09; decisions to confirm in its section 14): M1.1
upload hardening and content origin → M1.2–M1.4 LTI 1.3 → M1.5 LiaScript → M1.6–M1.7 Adapt → M1.8 H5P
items → M1.9 conformance. Work branch: `phase-1/content-formats`. Open items: `docs/plans/leftovers-0-2.md` section 4.

### 1.1 LiaScript
- [x] Versioned Markdown + assets as course source (`packages/liascript`)
- [x] CRUD API (create from Markdown, upload `.md`/zip, update, delete, fetch source) (plus versions list and
      restore)
- [x] Rendering decision: self-hosted LiaScript vs export to SCORM/xAPI; no dependency on
      liascript.github.io (the LiaScript SCORM 1.2 build, fetched at image build time with a pinned version
      and SHA-256, runs on the tenant content origin with our SCORM API page; completion at the last section
      or on completed/passed; `docs/plans/phase-1.md` 5.5)
- [x] (new) LiaScript topic type (learners), admin editor with preview and version diff, export/import strategy
      (topic type, Astro `LiaScriptLesson`, admin editor with versions, diff, restore and a live preview of
      unsaved text; course export carries the current text and assets, import creates a new document; ADR 0016)
- [x] (new) Run `sh packages/liascript/bin/fetch-player.sh` in the dev api container once (the image build does
      it; the bind mount hides it)

### 1.2 Adapt Learning
- [x] Path A: import built SCORM zip (`adapt-contrib-spoor`) (detected on upload, `scorm.source_format = adapt`,
      admin tag; generated spoor-style fixture)
- [x] Path B (feature flag): JSON source, schema-validated, isolated build worker (partial: `packages/adapt`
      behind `ADAPT_SOURCE_ENABLED` with versioned sources, structural validation, queued build and import
      through Path A; GPL worker `api/adapt-builder` (adapt_framework v5.56.2, compose profile `adapt`,
      real build round trip in the nightly conformance workflow); ADR 0013 (Proposed); an admin screen pending)

### 1.3 LTI 1.3 (high priority)
- [x] LTI Platform: launch external tools, AGS grade passback, deep linking (API, admin screens and topic
      form with "pick content from the tool", players in both fronts, ADR 0012; launching a Moodle 5.0
      course and receiving Moodle's grade verified in the nightly conformance workflow; the saLTIre job
      needs an operator run)
- [x] LTI Tool: expose our courses to Moodle, Canvas etc. (launch, user/role mapping, course access,
      deep-linking course picker, grade passback, admin platform screens and landing pages in both fronts;
      Moodle 5.0 launch and grade passback verified in the nightly conformance workflow; inside an LMS iframe
      the front's session cookie can be blocked as third-party, so platforms should open ulams in a new window)
- [x] Key rotation, nonce/state validation, per-tenant registrations (`ulams:lti:rotate-keys` monthly,
      provisioning step `lti_keys`, single-use hints/state/nonce/jti in `lti_nonces`, registrations in the
      tenant database, isolation tests)
- [x] (new) Admin UI for LTI: tools and platforms screens, external-tool topic form with "pick content from
      tool" (Integrations → LTI)
- [x] (new) LTI: Client-Side OIDC (platform storage via `postMessage`) on the tool side, NRPS on the platform
      side, per-tool `frame-src` in the CSP (client-side OIDC: `lti_storage_target`, `POST /api/lti/tool/launch/verify`,
      browser test with a fake platform in the nightly conformance; NRPS: `GET /api/lti/platform/nrps/{course}`
      per tool switch; `frame-src`: the front reads `GET /api/lti/frame-origins`)
- [x] (new) Run `ulams:lti:rotate-keys --init` for existing tenants (new tenants get it at provisioning) (done by `ulams:upgrade`, step `lti_keys`, ADR 0081)

### 1.4 Shared
- [x] Upload hardening (zip-slip, MIME, size limits, virus-scan hook) (`packages/uploads`: SCORM, cmi5,
      course import, file manager; clamd hook tested with a fake clamd, compose profile `av` not run in CI)
- [x] Isolated origin / strict CSP for third-party JS (SCORM, Adapt, LiaScript and cmi5 play from the
      per-tenant content origin with a strict CSP, files served by `/api/content` from local or bucket disks
      (ADR 0046); the front/admin CSP is enforced in development and switches with `CSP_ENFORCE` (ADR 0044))
- [x] (new) Zip-slip: SCORM (`ScormService::unzipScormArchive`) and cmi5 (`Cmi5UploadService`) extract
      archives with `ZipArchive::extractTo` and no entry-path checks; replace with a safe extractor (M1.1)
- [x] (new) The SCORM player loads `scorm-again` from the jsDelivr CDN; vendor it (air-gapped installs)
- [x] (new) SCORM/cmi5 content of all tenants is served from the shared `storage.localhost` origin; move
      packages and players to a per-tenant content origin (M1.1) (SCORM and cmi5 done, `<slug>.content.localhost`,
      `api/docs/content-origin.md`; run `ulams:tenant:sync-env` so existing tenants get `CONTENT_ORIGIN`)
- [x] (new) Course import read files outside the extracted archive through paths in `content.json`
      (e.g. `../../../.env` as a category icon, published to the bucket); paths now resolved inside it
- [x] (new) SVG/HTML uploads served from the bucket: stored with `Content-Disposition: attachment` and an
      extension-based `Content-Type`; storage origin sends `script-src 'none'` for SVG (follow-up of 0.2)
- [x] (new) Students have no `scorm_track-update` permission, so the legacy `/api/scorm/track` rejects
      them and the front's legacy SCORM player never tracked (seeded for students, confirmed 2026-10-09; re-run
      `PermissionsSeeder` on existing tenants). SCORM completion now completes the SCORM topics using the SCO
- [ ] (new) Production: serve content origins from a separate registrable domain (not same-site with the
      app), and add registered LTI tool origins to the front/admin `frame-src` (documented in
      `api/docs/content-origin.md`; deployment pending). Note 2026-10-09: the owner chose the same-site
      `*.content.ulams.app` instead; the separate domain stays supported (ADR 0014, amended)
      (partial: deployment docs with DNS and TLS steps done (operators/content-origin) and the registered LTI tool origins are in the front's `frame-src` (ADR 0044); the real domain is owner decision #24)
- [x] (new) Same-site content subdomain hardening: `__Host-` cookies, exact-Origin checks on front and API,
      sandboxed player frames, COOP/CORP headers, both modes documented
- [x] (new) Enforce the front/admin CSP after a week of clean reports; add a report collector (collector
      `POST /api/csp-report`, admin list `GET /api/admin/csp-reports`, `report-uri`/`report-to` on every policy,
      the front builds its CSP per request, `CSP_ENFORCE` and `ULAMS_CSP_HEADER` switch enforcement; ADR 0044;
      production turns it on after a clean week, see `operators/security-headers`)
- [x] (new) H5P service multitenancy via its `TenantResolver` (per-tenant key, database, bucket) (env-file
      resolver; per-tenant `H5P_INTERNAL_TOKEN`; library administration limited to the platform; production
      mounts limited to an exported least-privilege config (`ulams:h5p:export-config`, `compose.h5p.prod.yml`);
      idle-tenant eviction (`TENANT_IDLE_EVICT_MS`); ADR 0015)
- [x] (new) H5P: refresh the player model when the 5-minute Passport token rotates; redact `_token`
      in all proxies' access logs (Caddy and the H5P service redact `_token`; embed pages swap refreshed
      tokens in order, unit-tested; the old React front now refreshes the token before it expires)
- [x] Policies, OpenAPI annotations, fixtures and tests (LiaScript, Adapt A+B, LTI round-trip) (permissions
      `lti_manage`, `liascript_manage`, `adapt_manage`, OpenAPI for every new endpoint, fixtures and tests
      against in-test fakes; `.github/workflows/nightly-conformance.yml` (opt-in) with the Adapt worker build,
      Moodle 5.0 in both LTI directions (passed locally) and an operator-driven saLTIre job; ADR 0019)
- [x] (new) `TopicFinished` fired before the learner's progress was saved, so listeners running at once
      (sync queue) sent the previous LTI score; now dispatched after saving (found by the Moodle run, ADR 0018)
- [ ] (new) Turn on the nightly conformance runs (`NIGHTLY_CONFORMANCE=true`) and run the saLTIre job once
      with an operator
- [x] (new) Adapt Path B admin screen (sources, versions, build status)
- [x] (new) Astro front: H5P plays without a token, so learner state is not restored (decide: a short-lived
      H5P token from the BFF, or state through the BFF) (decided: state through the BFF, ADR 0045; the `/h5p`
      proxy adds the session token server-side for the player's own calls, the frame still gets `token: null`
      and the model's URLs carry no `_token`)
- [x] (new) Production: set `H5P_SERVICE_CONFIG_DIR`, run `ulams:h5p:export-config` and start the H5P service
      with `compose.h5p.prod.yml`
- [x] (new) H5P xAPI progress endpoint rejects statement objects (`ProgressService::h5p()` typed `string`) (plan L1-06; the statement is stored as JSON, its verb as the event)
- [ ] (new) `yarn install` on Node 24 fails in admin's postinstall (`max setup`: umi's esmi feature loads
      `http-deceiver`, which needs the removed `http_parser` binding); CI and `.nvmrc` use Node 22

---

## Phase 2: AI Course Builder

Note (new): a first Course Builder plan was drafted on 2026-10-08 (LLM layer in `api/packages/ai`,
tenancy package, Course Blueprint, LiaScript and Adapt topic types, admin module). It predates this
roadmap; Phase 2 is re-planned from this spec after Phases 0–1.

Plan (new): `docs/plans/phase-2.md` (approved 2026-10-09; follows Phase 1). First milestone
M2.1 "chat course building": upload → interview → outline diff → approved generation with citations →
approved apply through domain services → element chat edits. Designs:
`front/docs/design/stitch/course-builder/`. Open items and M2.2–M2.5: `docs/plans/leftovers-0-2.md` sections 2 and 5.

- [x] (new) Course Builder author area in the reference web app (`front/web`, `/studio`); the admin only
      links to it (M2.1, branch `phase-2/course-builder`; ADR 0022)
- [x] (new) AG-UI event log and SSE stream from Laravel, carrying A2UI surfaces (ADR 0011; A2UI as
      `a2ui-surface` activity snapshots, ADR 0023; cache-key wake instead of pub/sub, ADR 0029)
- [x] (new) Builder components in `@ulams/ui` and the course landing document in the catalogue format
- [x] (new) Studio: edit the Course Brief from the brief panel (`Edit` on a row opens the interview's
      own control, saves through `PUT …/brief`; price, theme and site never mark stages stale)
- [ ] (new) Detect admin edits made after an apply before re-applying (ADR 0010 drift check)
- [x] (new) Vendor the A2UI v0.9 JSON Schemas in `@ulams/ui` for dev-mode validation (plan 13.2; L2-02;
      the studio validates `a2ui-surface` envelopes in dev, tests cover every surface kind)
- [ ] (new) Operations for the builder: a separate PHP-FPM pool and Caddy route for
      `…/sessions/{id}/events`, a daily `course-builder:prune-events`, a Horizon queue for builder jobs
- [ ] (new) Run the opt-in cross-tenant check `TenantIsolationTest::testCourseBuilderSessionsDoNotCrossTenants`
      (written; needs `TENANCY_INTEGRATION=1` and two probe tenants)
- [ ] (new) Regenerate the OpenAPI spec and SDK path types for the builder endpoints (the SDK uses
      hand-written types; the API carries the annotations)
- [x] (new) Normalise `yarn.lock` with a real `yarn install` (a fresh `yarn install` leaves it unchanged; `--frozen-lockfile` in CI is the check) (entries for `@ag-ui/core` 1.0.2 and
      `diff` 9.0.0 were added by hand while the disk was full)
- [x] (new) Delete the RichText/GIFT content row when a topic is deleted (topic repository leaves it;
      the applier deletes topics through the repository)

### 2.1 LLM layer
- [x] Provider abstraction, model per task via config (Sonnet default, Haiku for light steps)
      (`api/packages/ai`; Anthropic, fake and disabled drivers; other providers in 8.2)
- [x] Structured outputs validated by JSON Schema, retry then graceful failure
- [x] Prompt caching for sources (live eval: lesson and quiz calls after the first read ~4.8k cached
      tokens)
- [x] Per-call logging: model, tokens, cost, latency, tenant, course; running cost per course
      (`ai_calls`, `ai:usage`, cost streamed to the studio)
- [x] Hard limits (source size, tokens per course, concurrency) (plus per-session cost, daily sessions,
      tenant monthly spend, eval spend)
- [x] Versioned prompt files with README (`api/packages/course-builder/resources/prompts`)

### 2.2 Ingestion
- [x] PDF, Markdown, DOCX → **Source Document** with stable fragment IDs (first-party DOCX converter,
      ADR 0026)
- [x] Untrusted content handling + prompt-injection tests (feature tests and a live eval fixture)
- [x] Design (don't build) image/video ingestion (design note in `docs/plans/phase-2.md` 6.4)

### 2.3 Interview
- [x] Adaptive chips/buttons with defaults and "decide for me"
- [x] Audience, duration, tone, theme preset + accent, free/paid (via `CommerceProvider`;
      interim: existing `payments`), assessments, language (partial: audience, level, duration and
      lesson length, tone, assessments, language, theme preset + accent and a free/paid question: Course
      Brief v2; the product is created through `CommerceProvider`, ADR 0049)
- [x] Editable **Course Brief** (schema-validated brief v2 with decided-by per field, editable in the
      studio panel and through the API with stale marking)

### 2.4 Generation pipeline (queued, resumable, streamed)
- [x] **Learning objectives** proposed and **approved by the author** first (with inline edits)
- [x] Outline mapped to source fragments and objectives
- [ ] Lessons in parallel from the component registry (rich text, LiaScript, H5P) (partial: rich text
      in a concurrency window; LiaScript and H5P lessons are M2.3)
- [x] Assessments with explanations, each traceable to a fragment (per-lesson quizzes and a final test,
      GIFT rendered by our code, support check against the cited text)
- [x] Metadata (title, description, SEO, pricing) (a suggested price for a paid course, confirmed by
      the author; ADR 0049)
- [ ] Tenant provisioning: subdomain, theme, publish, commerce channel/product if paid (partial: theme
      and the product (inactive at apply, active at publish) done; publish-check, landing and the new
      site are L2-08 and L2-09)
- [x] **Course Blueprint**: versioned JSON, stable IDs, citations; entities created via domain
      services; persisted per stage; progress streamed (SSE/websockets) (ADR 0010, 0025)
- [x] (new) Long jobs on dedicated queue connections: `<driver>-builder` (retry_after 2400) for Course
      Builder, Living Course and Adapt builds, `<driver>-long-job` for video and course clone; workers
      and Horizon with matching timeouts; config test `QueueRetryAfterConfigTest` (ADR 0083 amendment)

### 2.5 Element-level chat editing
- [x] Select element → chat → structured patch → diff → apply (course, module, lesson, block, question)
- [ ] Blueprint versions: undo/redo/restore; global edits via queued pipeline (partial: undo, redo and
      restore with re-apply done; global edits are M2.3)

### 2.6 Author UX
- [x] Upload → interview → live progress → tree + preview → element chat (e2e on the fake driver)
- [ ] Sources panel; retry a single failed step; themed learner frontend (partial: retry of a single
      step and source passages behind every citation done; the learner front is the existing one with
      the tenant theme; a full sources panel in the workspace is missing)

### 2.7 Generative UI
Architecture
- [x] Verify current A2UI / AG-UI versions and choose renderer (CopilotKit vs own) (A2UI v0.9,
      `@ag-ui/core` 1.0.2, own renderer; ADR 0011, 0023)
- [ ] UI component catalogue: name, props JSON Schema, model description, accessible
      implementation, text fallback (partial: the 17 builder components and the approved learner
      layout set (Timeline, FlipCards, CodeBlock, PracticeActivity, Callout, Steps, ComparisonTable,
      H5PFrame, LiaScriptLesson; L2-20); playground at `/catalogue/` in the docs site, L2-19)
- [x] `render_ui` validated server-side; invalid/unknown → text fallback (structured output choice
      validated against the `@ulams/ui` manifest)
- [x] Progressive streaming with skeletons; interactions sent back as structured events

Builder components (MVP)
- [ ] Interview controls · theme picker with live preview · drag-and-drop outline editor (partial:
      interview controls and theme picker done; drag-and-drop editor M2.3)
- [ ] Lesson preview card · variant comparison · quiz question card (partial: lesson preview and quiz
      question cards done; variant comparison M2.3)
- [ ] Diff view · generation progress with retry and cost · publish summary with warnings (partial:
      diff view, progress and the apply summary with warnings done; publish summary M2.2/M2.4)

Learner layouts (feature flag)
- [ ] AI-composed declarative lesson layouts from approved components, stored in blueprint
      (partial: the approved components and their manifest are done (L2-20); the Layout topic type
      and generation are L2-21)

Pedagogical guardrails
- [ ] Mandatory scaffolding: intro → toolbox → graded challenges → tiered hints →
      explanatory feedback → worked solution after attempt (partial: the `PracticeActivity` component
      enforces the slots and hides the solution until an attempt (L2-20); generation is L2-21)
- [ ] Four pillars check: objective alignment, agency, scaffolding, formative feedback

Generate-then-refine loop
- [ ] Critics: pedagogy, grounding, mechanics, visual/UX, accessibility; retry budget then
      flag to author
- [ ] Playwright agent solvability check incl. adversarial actions
- [ ] Critique results and iterations shown in publish summary

Simulations (opt-in)
- [ ] `simulation` component: sandboxed iframe, isolated origin, strict CSP, no network,
      typed postMessage
- [ ] Must pass solvability loop; author approval required; off by default in self-hosted

Adaptive interface (feature flag)
- [ ] Per-learner density, chunking, navigation, visible hints from Learner Insights
- [ ] Remediation components: Feynman reflection, elaborative questions, worked examples
- [ ] Surveys with SUS, UEQ, NASA-TLX + behavioural metrics

Impact measurement
- [ ] Built-in A/B experiments per course; delayed retention (3–7 days) as primary metric
- [ ] Results visible to authors; opt-in per tenant, consent where required

Quality
- [x] Component playground with model-facing descriptions (in the docs site instead of Storybook, ADR 0054,
      default pending #57; `/catalogue/`, L2-19)
- [x] Schema, fallback, interaction round-trip and accessibility tests per component (builder
      catalogue: vitest + axe in jsdom; axe on every studio screen in the e2e)
- [ ] Evals: right component choice, no raw markup outside `simulation`, simulation pass rate
      (partial: `course-builder:eval` checks interview component choice, DiffView for chat edits and no
      raw markup; simulations are M2.5)

---

## Phase 3: Living Course (killer feature)

Plan: `docs/plans/phase-3.md` (approved 2026-10-09; ADRs 0030–0034 Accepted). Milestones
M3.1 revisions and fragment diff (re-upload) → M3.2 impact and staleness → M3.3 AI update proposals →
M3.4 progress rules → M3.5 audit and notifications → M3.6 Git, webhooks, polling → M3.7 URL connector →
M3.8 evals and E2E. Designs: `front/docs/design/stitch/living-course/`.

- [ ] Source connectors: re-upload → Git (path + branch) → Drive / Notion as plugins (partial: upload, Git
      (GitHub, GitLab, Gitea/Forgejo) and URL connectors done, plugin contract and example connector in
      `docs/living-course/connector-plugins.md`; Drive and Notion not built, see the `(new)` item)
- [x] Change detection (webhook, poll, manual) with fragment-level diff
- [x] Impact analysis via citations, incl. quiz answers that may now be wrong
- [x] Update proposals: patches with reasons, reviewed as one diff (accept all / per element / reject)
- [x] Progress rules: minor edit keeps completion; changed quiz answer → re-attempt; never
      silently change past scores
- [x] Staleness signals per course and element
- [x] Audit trail (who accepted what, when, which source revision)
- [x] Tests: source v1/v2 fixtures; progress survives accepted update (API: `ProgressSurvivesUpdateTest`, eval
      fixtures with recorded live answers; studio e2e on the fake driver)
- [x] (new) URL connector (web pages on one host, CSS selector, HTML → Markdown)
- [x] (new) Shared SSRF-safe HTTP client in `core` (extracted from `lti`; IPv6, CGNAT, redirects re-checked)
- [x] (new) GIFT: snapshot the max score per attempt and archive questions instead of deleting them
- [ ] (new) Suggest a new lesson for newly added, uncovered source sections (partial: uncovered sections are
      listed in the proposal as `uncovered` items; no generated lesson proposal yet)
- [ ] (new) Generic Git (`git` CLI) connector for hosts without a supported API
- [ ] (new) Google Drive and Notion connector plugins (designed in `docs/plans/phase-3.md` 6.6)

---

## Phase 4: Personalisation

Plan (new): `docs/plans/phase-4.md` (draft, waiting for approval; ADRs 0057–0062 Proposed). Milestones M4.1 signal
stream → M4.2 risk scoring → M4.3 privacy and transparency → M4.4 nudges and recovery → M4.5 remediations →
M4.6 author analytics → M4.7 AI tutor → M4.8 adaptive interface, experiments, evals. Designs:
`front/docs/design/stitch/personalisation/`. Owner questions #59–#63.

### 4.1 `learner-insights` package
- [ ] Append-only learner signal stream mapped to blueprint element IDs; queues; backfill
- [ ] Rule-based risk scoring with human-readable reasons; per-tenant thresholds
- [ ] `RiskScorer` interface for future ML
- [ ] Statuses and events: `LearnerStruggling`, `LearnerAtRisk`
- [ ] Personal remediations (learner-scoped, grounded, cached per struggle pattern)
- [ ] Nudges via `notifications`, rate-limited
- [ ] Recovery rate measurement
- [ ] Author analytics; high-struggle elements → update proposals
- [ ] Privacy: per-tenant toggle, retention, explanations, minimal data to LLM
- [ ] Rule unit tests, synthetic learner journeys, tenant isolation
- [ ] (new) Streaming tutor answers (LLM client streaming over the SSE event log)
- [ ] (new) Partition `learner_signals` by month when a tenant exceeds 50M rows
- [ ] (new) Remediations for courses not built with the Course Builder
- [ ] (new) `TopicProgressUpdated` event in `courses` for non-completion progress (ids only)

### 4.2 AI tutor
- [ ] Answers only from course sources with citations; says when out of scope
- [ ] No quiz answers during active attempts
- [ ] Rate/cost limits; anonymised analytics

### 4.3 Trust and transparency
- [ ] Reasons for every recommendation · AI on/off and provider per tenant
- [ ] AI content labelling · documented data flows, no training on customer content

---

## Phase 5: UX and reference frontend

- [ ] Audit Wellms frontends; evolve or build new reference app (justify; SSR/SEO) (partial: new Astro
      SSR app `front/web`, ADR 0008 Proposed; landing, course, lesson player, quiz, finish for the three
      demo tenants; plan and numbers in `docs/plans/phase-5-reference-frontend.md`; uncommitted)
- [ ] Themeable from builder presets; per-tenant theme (partial: `--ulams-*` theme files per demo
      preset in `front/ui/src/styles/themes`, chosen from `theme.theme`; accent from `theme.accent`
      applied server-side with AA contrast; builder presets not connected yet)
- [ ] PWA offline mode with tested sync/conflict rules
- [ ] Single frontend for LMS **and** Sylius commerce (catalogue, checkout, account)
- [ ] Web components (my courses, continue, catalogue, quiz, tutor, certificate badge) (partial:
      `<ulams-quiz>`, `<ulams-h5p>`, `<ulams-video>`, `<ulams-progress>` in `front/ui/src/elements`)
- [ ] TypeScript SDK from OpenAPI; widget docs with live examples (partial: `@ulams/sdk` in `front/sdk`,
      fetch-only; request paths typed from the generated spec, response types hand-written because the
      spec has no response schemas; no widget docs yet)
- [ ] Learner UX: "continue", "what's next", progress and time estimates, short lessons
- [ ] Semantic search with cited AI answers
- [ ] **WCAG 2.2 AA** + European Accessibility Act; axe in CI (partial: reference frontend has landmarks,
      skip link, focus-visible, reduced motion, 360 px layout and a theme contrast test; axe scan of
      every page type in the Playwright e2e passes, not wired into CI yet)
- [ ] Admin UX: templates, guided empty states, sample course, bulk operations, saved filters
- [ ] AI transparency everywhere (diffs, citations, reasons one click away)
- [ ] Metrics: time to first course, time to first enrolment, admin task times
- [ ] (new) Reference frontend for the demos: UI catalogue `@ulams/ui` (JSON-schema registry, renderer
      with fallbacks, landing pages as A2UI-shaped documents), BFF with httpOnly session, demo auto-login,
      SWR cache of public API data (partial: implemented and tested, uncommitted; needs the Caddy switch
      of `*.app.localhost` to :4321 and the API fixes listed in the plan)
- [ ] (new) Platform product landing on the platform host (`app.localhost`) with the live demos;
      account area; webinars/events/consultations pages (partial: implemented and tested, uncommitted;
      see `docs/plans/phase-5-reference-frontend.md`, batch 2)

---

## Phase 6: Market-essential modules

### 6.1 Certificates and compliance
- [ ] Extend existing Wellms certificates (don't duplicate)
- [ ] (new) Remove ReportBro completely and replace it with **pdfme** (recommended: MIT, actively
      maintained, WYSIWYG designer for variable-based PDF templates, JSON templates, QR/barcode schemas).
      Why: the ReportBro designer (`reportbro-designer`, AGPL-3.0) is bundled into admin, the server image
      runs `reportbro-lib` (AGPL-3.0), and the default `REPORTBRO_URL` sends certificate data to
      reportbro.com. Removal checklist:
  - [ ] Admin: replace `components/PdfEditor` and `components/TemplateFields` with the `@pdfme/ui`
        designer; drop `reportbro-designer` from `admin/package.json` (partial: designer with variables
        panel, API preview and save done, typecheck/build pass; not yet exercised in a browser; admin
        Jest is broken at baseline, so the new helper tests in `PdfEditor/template.test.ts` do not run)
  - [ ] API `templates-pdf`: replace `ReportBroService`/contract, the `reportbro/report/run` routes and
        `FabricPdfController` with a pdfme renderer client; keep the existing variables and
        `CourseFinished` flow; store templates as pdfme JSON (partial: done and tested —
        `PdfRendererContract`, `POST /api/admin/pdfs/preview`, fonts proxy, certificates rendered once
        and stored; uncommitted)
  - [ ] Renderer: small MIT Node worker `api/pdf` (pdfme generator; the API image has no Node), reached
        over HTTP like `api/h5p`; QR schema for certificate verification URLs (6.1) (partial: service,
        tests and Docker image done; the QR points to `{APP_URL}/certificates/verify/{id}`, the
        verification page does not exist yet)
  - [ ] Remove the `reportbro` service from `api/docker-compose.yml`, `REPORTBRO_URL` from config and
        `.env.example`, and its mentions in docs and `LICENSING.md` (partial: done; historical mentions
        remain in `docs/reports/phase-0-audit.md`, `docs/plans/phase-0.md` and ADR 0002)
  - [ ] Migrate existing templates (one-off converter or re-create); pdfme templates for the demo
        certificates; tests for template CRUD, rendering and the CourseFinished certificate (partial:
        converter migration + `templates-pdf:migrate-reportbro` done; themed JSON for coffee/oncall/nightsky
        in `templates-pdf/resources/pdfme`, not yet assigned by the demo seeding)
  - [ ] Until then: set `REPORTBRO_URL` to the local server so no data leaves the installation
        (obsolete once the pdfme change is merged: ReportBro and `REPORTBRO_URL` are gone)
- [ ] Verification URL/QR, expiry, recertification, reminders
- [ ] Mandatory training with due dates and manager escalation
- [ ] Compliance reports and audit export (linked to Phase 3 audit trail)

### 6.2 Automated enrolment and paths
- [ ] Rule engine (attribute → path, due in N days), on change and on schedule
- [ ] Learning paths with prerequisites; lifecycle notifications

### 6.3 Identity and HR
- [ ] SAML 2.0 and OIDC per tenant · SCIM 2.0 · HRIS import (CSV/API first)

### 6.4 Commerce (Sylius) and extended enterprise
- [ ] `CommerceProvider` interface (sync product, create checkout, handle order events) (partial: interface and the Wellms cart adapter shipped in `api/packages/commerce`, ADR 0049; the Sylius adapter is pending)
- [ ] Sylius adapter as default implementation
- [ ] Entitlements model in LMS (access, validity, source order or seat package)
- [ ] Catalogue sync: course/bundle/subscription/seat package → digital Sylius product
- [ ] Order → entitlement via signed, idempotent webhooks + reconciliation job; never grant
      access from frontend redirect
- [ ] Single login: LMS as identity source; Sylius customer linked on first checkout
- [ ] Tenant ↔ Sylius channel (catalogue, prices, currency, locale, tax zone), created at provisioning
- [ ] Unified admin: prices, coupons, orders, refunds in LMS admin; native Sylius admin for
      advanced settings
- [ ] B2B seat packages (Sylius product + LMS seat pool), invoices from Sylius
- [ ] Migration of orders, vouchers and access from Wellms packages with zero lost access
- [ ] EU VAT (OSS) for digital services: confirm Sylius handling, document options
- [ ] Per-tenant branding, catalogue, sales pages
- [ ] Partner/customer portals with scoped admins and reports

### 6.5 Analytics and ROI
- [ ] Dashboards (completion, results, struggle/recovery, certificates, overdue)
- [ ] BI export/API, scheduled reports, optional business KPI link

### 6.6 Skills and competencies
- [ ] Competency framework; AI-suggested tags confirmed by author
- [ ] Learner skill profiles feeding Learner Insights and path rules

---

## Phase 7: Developer experience and agents

### 7.1 Course-as-code
- [ ] Markdown + YAML/JSON format with JSON Schema
- [ ] CLI `ulams`: init, validate, preview, push, pull, diff, publish
- [ ] Two-way Git sync with diff-based conflicts
- [ ] GitHub Action + Docker image; preview deployment per PR
- [ ] Git merge triggers Living Course update proposal
- [ ] (new) CLI plan `docs/plans/cli.md`: agent-first `ulams` CLI and MCP server (draft, waiting for approval;
      ADRs 0072–0079)
- [x] (new) M1 CLI core: `front/cli` workspace, command registry, output contract and exit codes, profiles,
      `login` (token/password/demo), `whoami`, `ulams api`, `schema`, `describe`
- [x] (new) M2 noun commands generated from OpenAPI + overrides, topic uploads of every type, pagination,
      `--dry-run`, `--wait`, `apply -f`, coverage matrix enforced in CI
- [ ] (new) M4 course builder commands with AG-UI events as NDJSON; Living Course commands after the Phase 3 merge
- [ ] (new) M5 course-as-code: Blueprint v2, Markdown + directives format, sync base and conflict diffs
      (after Phase 3; citations for author blocks pending #78)
- [ ] (new) M6 CLI release: npm `ulams` (pending #77), bun-compiled binaries (signing pending #76), Docker image
- [ ] (new) Endpoints to import a blueprint as a builder version and export a course as a blueprint (for M5)

### 7.2 Code exercises
- [ ] WebContainers/Sandpack (JS/TS), Pyodide (Python), optional server sandbox
- [ ] Autograding with author tests → attempts and learner signals
- [ ] AI hints without revealing solutions

### 7.3 API, SDK, webhooks
- [ ] Complete published OpenAPI; TS SDK first, PHP second
- [ ] Stripe-style webhooks (signed, retries, replay, delivery log, test sends, versioned events)
- [ ] Scoped API keys with rate limits and usage stats
- [x] (new) S1 scoped personal access tokens (`area:read|write`, presets, fail-closed route map), agent audit
      log, `Idempotency-Key`, `X-Request-Id`, `GET /api/meta`; admin "API tokens" page (ADR 0074)
- [x] (new) S2 device login: own RFC 8628 flow + `/cli/authorize` page in the web app (ADR 0075; pending #74)
- [ ] (new) S3 platform tenant API with queued provisioning (ADR 0078; pending #79)
- [x] (new) S4 course builder run-status endpoint `GET /api/admin/course-builder/runs/{run}`
- [ ] (new) S5 OpenAPI response schemas for the top 60 operations the CLI uses, after L0-11; stable operationIds
- [ ] `npx create-ulams` / `docker compose up` with seed data
- [ ] Docs site with runnable examples; free cloud sandbox tenant (partial: Starlight site in `front/docs-site` on branch `docs/starlight-site`; runnable examples and the sandbox tenant pending)

### 7.4 Plugin system
- [ ] Extension points (content, UI catalogue, connectors, risk rules, webhooks, admin pages)
- [ ] Manifest, versioning, compatibility checks; marketplace-ready design (not built)

### 7.5 MCP server
- [ ] Admin/author tools (courses, builder, enrolment, reports, update proposals, certificates)
- [ ] Learner tools (my courses, next lesson, quiz, tutor) with progress recorded
- [ ] Rich UI: A2UI over MCP → MCP Apps for simulations → Markdown fallback
- [ ] Delegate commerce actions to Sylius MCP tool
- [ ] Agent safety: scoped tokens, dry-run, idempotency keys, human approval, rate limits,
      agent audit log
- [ ] Tool description evals with typical agent tasks
- [ ] (new) First version on Cloudflare Workers (TypeScript, Agents SDK, OAuth) against the current REST
      API: hand-written course/topic/quiz tools + tools generated from the OpenAPI spec
      (note: the CLI plan proposes the local `ulams mcp` first and this as the later hosted variant from the
      same registry; pending #75)
- [x] (new) M3 `ulams mcp` (stdio + Streamable HTTP) generated from the CLI registry: toolsets, annotations,
      confirmation for destructive tools, resources, MCP client tests, agent eval (ADR 0076)

### 7.6 Machine-readable content
- [ ] `llms.txt`, Markdown version of every page, public schemas, `AGENTS.md`
- [ ] Knowledge export (cited chunks for company RAG), auto re-export on change

---

## Phase 8: Self-hosting

- [ ] (new) R&D: Cloudflare deployment (TypeScript + Hono gateway Worker, Laravel as Cloudflare
      Container, R2, Hyperdrive, Queues, Durable Objects) with a one-command `wrangler deploy` for a
      dev environment; strangler migration of the API to Hono only if the spike succeeds

- [ ] One app image + PostgreSQL + optional Redis (DB fallback)
- [ ] Commerce as optional profile: + one Sylius image, shared PostgreSQL server (separate DB)
- [ ] `docker compose up` and Helm chart with sane defaults
- [ ] Setup wizard (admin, domain, mail, storage, AI provider or none, commerce link)
- [ ] Any AI provider (OpenAI-compatible, Anthropic, Ollama/vLLM); full function without AI
- [ ] Air-gapped: no telemetry by default, no external CDNs, offline licence, data residency
- [ ] Safe migrations, stable/LTS channels, backup/restore tested in CI
- [ ] Health checks, Prometheus metrics, structured logs, zero-downtime guidance
- [ ] SBOM, signed images, CVE scanning, hardening guide, ISO 27001 / GDPR audit docs

---

## Quality bar (every phase)

- [ ] Mocked LLM in tests + eval command on golden fixtures (schema, citation coverage, quiz
      answers supported, duration, cost)
- [ ] Tenant isolation tests for every endpoint
- [ ] WCAG 2.2 AA checks for learner-facing UI
- [ ] A/B experiment + delayed-retention metric for every feature claiming learning benefit
- [ ] E2E: upload → interview → live course → chat edit → source change → accepted update →
      progress intact
- [ ] Commerce E2E: buy → webhook → access granted; refund → access revoked; missed webhook
      repaired by reconciliation
- [ ] Existing tests and linters green; H5P/SCORM unchanged; README per package

## Out of scope (for now)

Image/video generation, AI avatars, custom themes beyond presets, real-time multi-author
collaboration, fine-tuning, billing for the builder itself.

## Key references

- Google Research, learning interactives (2026): research.google/blog/the-future-of-practice-enabling-teachers-to-create-learning-interactives-with-generative-ui/ · arXiv 2609.20738
- Learn Your Way: arXiv 2509.13348, 2509.18664
- A2UI and MCP Apps: developers.googleblog.com/en/a2ui-and-mcp-apps/
- Sylius: github.com/Sylius/Sylius
