# ULAMS roadmap: TODO

AI-native headless LMS on Wellms (Escola LMS). Differentiator: **courses that stay in sync with
their sources ("Living Course") and adapt to each learner**, with every element cited.

Full spec for Claude Code: `docs/ROADMAP-PROMPT.md`. Working rules: `CLAUDE.md`. Run sessions with
"Read docs/ROADMAP-PROMPT.md and start Phase N". Every phase: explore → plan → **approval** →
small commits → tests → summary.

---

## Decisions made

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

## Open decisions

- [ ] Final name (favourite **ULAMS**; alternatives Wellam, Monte, Spiral, Automata, UlamOS)
  - [x] GitHub organisation: `ulams` is taken; register `ulams-dev` (fallbacks: `ulams-hq`,
        `ulamslabs`, `ulams-ai`) (note: `ulams-dev/ulams` created and pushed 2026-10-08)
  - [ ] Check domains (ulams.ai, ulams.dev) and trademarks
  - [ ] Check legal aspects of using the Ulam name
  - [x] (new) Copyright of the original EscolaLMS/Wellms code and `scorm-player`: owned by the product owner; admin and scorm-player licensed MIT
- [ ] Move MCP server (7.5) right after Phase 2? Cheap to build, strong demo
- [ ] Move certificates (6.1) earlier if compliance is the priority segment
- [ ] Multitenancy for the POC: one deployment, tenant per subdomain with own theme?
- [ ] Prototype the Sylius order → entitlement flow early (highest-risk commerce piece)
- [x] Add the spec file to the repo as `docs/ROADMAP-PROMPT.md`

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

### 0.1 Explore and report (no code)
- [x] Map repo, packages, versions; course → lesson → topic model and topic types (see docs/reports/phase-0-audit.md)
- [x] Report on `headless-h5p`, `scorm`, `cmi5`, `lrs`, `tracker`, `reports`, `payments`,
      `cart`, `vouchers`, `translations`, `settings`, `templates`, `notifications` (see docs/reports/phase-0-audit.md)
- [x] Can `recommender` be safely disabled or removed? What depends on it? (removed from API, admin and front; nothing else depended on it; ADR 0006; webcam-capture leftover tracked in 0.1c)
- [ ] Multitenancy via `gecche/laravel-multidomain`: current setup, dynamic subdomains possible? (partial: dynamic subdomains work via the tenancy package (`ulams:tenant:create`); fixed shared Redis keys, unknown-host fallback and boot-time worker lists; remaining: tenant video queue, per-tenant storage credentials, production DNS/TLS)
- [x] Inventory of learner activity data (tracker, xAPI/cmi5, SCORM CMI, H5P, quizzes, progress,
      logins): storage, granularity, retention, gaps (see docs/reports/phase-0-audit.md)
- [x] How content updates preserve learner progress today (see docs/reports/phase-0-audit.md)
- [ ] Tests, CI, code style, queues (Horizon), storage, existing AI code (partial: explored; no AI code exists; baseline failures: core 6, auth 3)
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
- [ ] (new) Root README, AGENTS.md and per-package READMEs for the monorepo
- [ ] (new) Remaining legacy references (partial: `escolalms/php` replaced by a base built in-repo, ReportBro removed and replaced by pdfme): replace the `escolalms/php` and `escolalms/reportbro-server`
      images, decide on upstream provenance links, reword ADR prose, retarget Docker Hub publishing
      workflows, replace the `ulams.app` placeholder domain, recreate SQL views in pre-rename databases
- [x] (new) Fix `php artisan route:list` (Mattermost client connects in its constructor)
- [ ] (new) CI (partial: root `ci.yml` with path filters, PHP shards, licence guards and Dependabot committed; not yet run on GitHub — the branch is unpushed; publishing workflows intentionally dropped): move workflows to the root `.github/` with path filters; drop MySQL services; run Jest
      in admin/front; Dockerfiles build from the repo root
- [ ] (new) Remove the non-existent `packages/tracker/src` path from Swagger (done); consider Git LFS for
      large test fixtures; revisit exact pins (`faker-markdown-generator`, `tzsk/sms`)

### 0.1c Security and audit follow-ups (new)
- [x] (new) Replace the GPL PHP libraries `trax2/framework` (lrs) and `laraveldaily/laravel-invoices` with first-party code
- [x] (new) Payment callbacks must verify the payment with the provider (Stripe signature/status, P24
      verification); RevenueCat off by default and server-verified
- [x] (new) Remove the consultation webcam capture and its unauthenticated upload endpoints (recommender leftover)
- [x] (new) Authenticate the Jitsi recording webhook and restrict the downloaded URL (SSRF)
- [x] (new) Verify JWT signatures in the LRS guard
- [x] (new) Fix the ungrouped `orWhere` in `CourseAccessService::getUserCourseIds` and similar queries
- [ ] (new) Remove the tracker Logs screen in admin and other tracker leftovers
- [x] (new) Fix the tenant video processing queue (jobs dispatched to a queue no tenant worker consumes)
- [ ] (new) `Relation::enforceMorphMap` for topic types so class renames never orphan data
- [x] (new) ADR for the tenancy package (docs/decisions/0007)
- [ ] (new) Upgrade PostgreSQL 12 (EOL) to 16/17 with a tested dump/restore path
- [ ] (new) Drop Soketi until realtime is needed (broadcast driver is `log`); Laravel Reverb after 0.2

- [ ] (new) cmi5 for learners: give students the cmi5 launch permission and serve AU files from
      object storage (they sit on the local disk that Caddy does not serve) — found by the demo seeders
- [ ] (new) Containers cannot reach `storage.localhost` (it resolves to the container itself); use the
      internal MinIO endpoint for server-side fetches (e.g. Image topic creation)
- [x] (new) Platform bucket publicly readable by default (`MINIO_DEFAULT_BUCKETS=ulams:download`)
- [x] (new) Demo course seeders for the three experiences (`make demo-seed`, `demo-seed-tenants`)

- [ ] (new) Security follow-ups (medium): require `auth:api` on admin tag routes; rate-limit/authorise
      `POST api/images/img`; review `POST api/cmi5/fetch`; client-set `has_trial`, client currency override
      and `payProduct` skipping `purchasable`; vouchers admin search OR grouping; `getChildGroups` depth;
      keep `_ignition` off in production
- [ ] (new) Stripe: handle the 3-D Secure redirect in the front and document the webhook setup
      (`PAYMENTS_STRIPE_WEBHOOK_SECRET`, `/api/payments-gateways/webhook/stripe`); RevenueCat receipt verifier
- [ ] (new) Jitsi: confirm the JaaS webhook signature format against the JaaS docs; configure
      `JITSI_RECORDING_HOSTS`
- [ ] (new) Drop the unused `analyze_enabled` columns (consultations, webinars) and clean up stored meeting
      frames in tenant buckets
- [ ] (new) Remove the Stripe test key committed in `api/docker/envs/*.example`
- [ ] (new) Responsible disclosure: the payment-callback, LRS-token, webcam-upload and course-access issues
      exist in the upstream EscolaLMS packages; notify upstream users

- [ ] (new) mjml: the `mjml` compose service is not on the `ulams` network and `MJML_API_URL` is not set
      (templates fall back silently); wire it or drop the service
- [ ] (new) Publish images to GHCR (`ghcr.io/ulams-dev/*`, decided 2026-10-08); publish the base image as `ulams/php:8.3` with source offers for its GPL programs (see
      `api/docker/php/NOTICE`)

- [ ] (new) Delete `front/src/style/` (two unused styled-components helpers; excluded from tsconfig,
      eslint and the guard until removed)
- [x] (new) Cart on tenants crashes without a Stripe publishable key (`stripe.tsx` calls
      `stripeKey.includes` on null); show a configuration message instead
- [ ] (new) Yarn install on Node 23 needs `--ignore-engines` (vitest engines); CI pins Node 22

### 0.2 Framework upgrade
- [x] Upgrade plan from Laravel 9 (EOL) to supported Laravel/PHP: order, breaking changes,
      forks/patches needed, risks (docs/plans/phase-0.md: 9 → 10 → 11 → 12 → 13 on PHP 8.4)
- [ ] Implement after approval with test suite green at every step
      (partial: steps 1–3/4 done — Laravel 12.69.3 on PHP 8.3 (Carbon 3, Passport 12, Testbench 10, PHPUnit 11,
      `devianl2/laravel-scorm` vendored as `api/packages/laravel-scorm`), no new test failures; see
      docs/plans/phase-0.md B.11–B.13)

---

## Phase 1: Content formats and integrations

### 1.1 LiaScript
- [ ] Versioned Markdown + assets as course source
- [ ] CRUD API (create from Markdown, upload `.md`/zip, update, delete, fetch source)
- [ ] Rendering decision: self-hosted LiaScript vs export to SCORM/xAPI; no dependency on
      liascript.github.io

### 1.2 Adapt Learning
- [ ] Path A: import built SCORM zip (`adapt-contrib-spoor`)
- [ ] Path B (feature flag): JSON source, schema-validated, isolated build worker

### 1.3 LTI 1.3 (high priority)
- [ ] LTI Platform: launch external tools, AGS grade passback, deep linking
- [ ] LTI Tool: expose our courses to Moodle, Canvas etc.
- [ ] Key rotation, nonce/state validation, per-tenant registrations

### 1.4 Shared
- [ ] Upload hardening (zip-slip, MIME, size limits, virus-scan hook)
- [ ] Isolated origin / strict CSP for third-party JS
- [ ] (new) H5P service multitenancy via its `TenantResolver` (per-tenant key, database, bucket) (partial: env-file resolver live for the demo tenants; remaining: a distinct `H5P_INTERNAL_TOKEN`
      per tenant written at provisioning, library administration limited to the platform because
      libraries are shared, production mounts limited to env files and key directories, idle-tenant
      eviction)
- [ ] (new) H5P: refresh the player model when the 5-minute Passport token rotates; redact `_token`
      in all proxies' access logs
- [ ] Policies, OpenAPI annotations, fixtures and tests (LiaScript, Adapt A+B, LTI round-trip)

---

## Phase 2: AI Course Builder

Note (new): a first Course Builder plan was drafted on 2026-10-08 (LLM layer in `api/packages/ai`,
tenancy package, Course Blueprint, LiaScript and Adapt topic types, admin module). It predates this
roadmap; Phase 2 is re-planned from this spec after Phases 0–1.

### 2.1 LLM layer
- [ ] Provider abstraction, model per task via config (Sonnet default, Haiku for light steps)
- [ ] Structured outputs validated by JSON Schema, retry then graceful failure
- [ ] Prompt caching for sources
- [ ] Per-call logging: model, tokens, cost, latency, tenant, course; running cost per course
- [ ] Hard limits (source size, tokens per course, concurrency)
- [ ] Versioned prompt files with README

### 2.2 Ingestion
- [ ] PDF, Markdown, DOCX → **Source Document** with stable fragment IDs
- [ ] Untrusted content handling + prompt-injection tests
- [ ] Design (don't build) image/video ingestion

### 2.3 Interview
- [ ] Adaptive chips/buttons with defaults and "decide for me"
- [ ] Audience, duration, tone, theme preset + accent, free/paid (via `CommerceProvider`;
      interim: existing `payments`), assessments, language
- [ ] Editable **Course Brief**

### 2.4 Generation pipeline (queued, resumable, streamed)
- [ ] **Learning objectives** proposed and **approved by the author** first
- [ ] Outline mapped to source fragments and objectives
- [ ] Lessons in parallel from the component registry (rich text, LiaScript, H5P)
- [ ] Assessments with explanations, each traceable to a fragment
- [ ] Metadata (title, description, SEO, pricing)
- [ ] Tenant provisioning: subdomain, theme, publish, commerce channel/product if paid
- [ ] **Course Blueprint**: versioned JSON, stable IDs, citations; entities created via domain
      services; persisted per stage; progress streamed (SSE/websockets)

### 2.5 Element-level chat editing
- [ ] Select element → chat → structured patch → diff → apply
- [ ] Blueprint versions: undo/redo/restore; global edits via queued pipeline

### 2.6 Author UX
- [ ] Upload → interview → live progress → tree + preview → element chat
- [ ] Sources panel; retry a single failed step; themed learner frontend

### 2.7 Generative UI
Architecture
- [ ] Verify current A2UI / AG-UI versions and choose renderer (CopilotKit vs own)
- [ ] UI component catalogue: name, props JSON Schema, model description, accessible
      implementation, text fallback
- [ ] `render_ui` validated server-side; invalid/unknown → text fallback
- [ ] Progressive streaming with skeletons; interactions sent back as structured events

Builder components (MVP)
- [ ] Interview controls · theme picker with live preview · drag-and-drop outline editor
- [ ] Lesson preview card · variant comparison · quiz question card
- [ ] Diff view · generation progress with retry and cost · publish summary with warnings

Learner layouts (feature flag)
- [ ] AI-composed declarative lesson layouts from approved components, stored in blueprint

Pedagogical guardrails
- [ ] Mandatory scaffolding: intro → toolbox → graded challenges → tiered hints →
      explanatory feedback → worked solution after attempt
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
- [ ] Component playground (Storybook) with model-facing descriptions
- [ ] Schema, fallback, interaction round-trip and accessibility tests per component
- [ ] Evals: right component choice, no raw markup outside `simulation`, simulation pass rate

---

## Phase 3: Living Course (killer feature)

- [ ] Source connectors: re-upload → Git (path + branch) → Drive / Notion as plugins
- [ ] Change detection (webhook, poll, manual) with fragment-level diff
- [ ] Impact analysis via citations, incl. quiz answers that may now be wrong
- [ ] Update proposals: patches with reasons, reviewed as one diff (accept all / per element / reject)
- [ ] Progress rules: minor edit keeps completion; changed quiz answer → re-attempt; never
      silently change past scores
- [ ] Staleness signals per course and element
- [ ] Audit trail (who accepted what, when, which source revision)
- [ ] Tests: source v1/v2 fixtures; progress survives accepted update

---

## Phase 4: Personalisation

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

### 4.2 AI tutor
- [ ] Answers only from course sources with citations; says when out of scope
- [ ] No quiz answers during active attempts
- [ ] Rate/cost limits; anonymised analytics

### 4.3 Trust and transparency
- [ ] Reasons for every recommendation · AI on/off and provider per tenant
- [ ] AI content labelling · documented data flows, no training on customer content

---

## Phase 5: UX and reference frontend

- [ ] Audit Wellms frontends; evolve or build new reference app (justify; SSR/SEO)
- [ ] Themeable from builder presets; per-tenant theme
- [ ] PWA offline mode with tested sync/conflict rules
- [ ] Single frontend for LMS **and** Sylius commerce (catalogue, checkout, account)
- [ ] Web components (my courses, continue, catalogue, quiz, tutor, certificate badge)
- [ ] TypeScript SDK from OpenAPI; widget docs with live examples
- [ ] Learner UX: "continue", "what's next", progress and time estimates, short lessons
- [ ] Semantic search with cited AI answers
- [ ] **WCAG 2.2 AA** + European Accessibility Act; axe in CI
- [ ] Admin UX: templates, guided empty states, sample course, bulk operations, saved filters
- [ ] AI transparency everywhere (diffs, citations, reasons one click away)
- [ ] Metrics: time to first course, time to first enrolment, admin task times

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
- [ ] `CommerceProvider` interface (sync product, create checkout, handle order events)
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

### 7.2 Code exercises
- [ ] WebContainers/Sandpack (JS/TS), Pyodide (Python), optional server sandbox
- [ ] Autograding with author tests → attempts and learner signals
- [ ] AI hints without revealing solutions

### 7.3 API, SDK, webhooks
- [ ] Complete published OpenAPI; TS SDK first, PHP second
- [ ] Stripe-style webhooks (signed, retries, replay, delivery log, test sends, versioned events)
- [ ] Scoped API keys with rate limits and usage stats
- [ ] `npx create-ulams` / `docker compose up` with seed data
- [ ] Docs site with runnable examples; free cloud sandbox tenant

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
