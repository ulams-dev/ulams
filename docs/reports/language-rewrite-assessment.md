# Rewriting the ULAMS backend in TypeScript, Rust or Go: plan, effort and benefits

Date: 2026-10-20. Author: roadmap session (analysis only, no code changed). Status: **assessment for the owner's decision**; nothing here is scheduled.

## 1. Answer in five lines

- **Size to rewrite:** about 185 000 lines of PHP in 66 API packages, 89 000 lines of PHP tests (about 3 200 test methods), 305 migrations creating 155 tables, 566 OpenAPI operations, 147 controllers, 395 request classes, 176 API resources, 51 policies, 46 events, 26 queued jobs, 40 console commands. The admin (67 000 lines of TS/TSX), the learner frontend, the UI catalogue, the SDK, the CLI and the H5P and PDF services are **already TypeScript** and stay.
- **Modelled effort (one full-time engineer working with AI agents, estimate, not a measurement):** TypeScript **about 31 person-months** (range 19 to 50), Go **about 44** (27 to 70), Rust **about 64** (38 to 102). Calendar time for one person is roughly the same number in months; a team of four with a clear module split cuts it to about 40 to 60 percent of that, not 25 percent, because review, parity testing and the shared core do not parallelise.
- **Gains are real but narrow.** The backend is I/O bound (PostgreSQL, Redis, S3, ffmpeg, LLM calls), so a faster language gives little throughput that the product needs today. The real gains are: one language across the whole stack (TypeScript), a single static binary for self-hosting (Go, Rust), and lower memory per tenant (Go, Rust). Details in section 5.
- **Costs are large:** a freeze of the roadmap for 1 to 2 years, loss of the Wellms package lineage and the composer-package extension model, and the risk of regressing standards that took years to get right (SCORM, LTI 1.3, cmi5, xAPI, H5P tracking).
- **Recommendation: do not rewrite everything now.** If a language move is wanted, do it as a **strangler migration to TypeScript**: new modules (Phase 4 and later) in TypeScript behind the same Caddy gateway, legacy Laravel packages ported one by one only when they are touched anyway. Section 8 gives the decision rules and a 4-week spike that replaces the guesses in this report with a measured velocity.

## 2. What exists today (facts, counted on `main` at `396c1d912`)

| Item | Count | How counted |
|---|---|---|
| API packages (`api/packages/*`) | 66 | directories |
| PHP source lines in packages | 182 877 | `*.php` outside `tests/` |
| PHP test lines in packages | 89 164 | `*.php` under `tests/` |
| Test methods | about 3 211 | `function test*`, `@test`, `#[Test]` |
| Migrations / tables created | 305 / 155 | `Schema::create` |
| OpenAPI operations | 566 | the API browser spec |
| Controllers / request classes / resources / policies | 147 / 395 / 176 / 51 | class patterns |
| Events / queued jobs / console commands | 46 / 26 / 40 | class patterns |
| Eloquent model files | 207 | files under `*/Models` |
| Composer requirements | 48 (+8 dev) | `api/composer.json` |
| Already TypeScript | admin 67k, `front/web` 13k, `front/ui` 21k, `front/sdk` 31k, `front/cli` 14k, H5P service 4k, PDF service 1k | lines of `.ts/.tsx/.astro` |

Largest packages by source lines: `courses` 11.9k, `auth` 11.8k, `course-builder` 10.5k, `cart` 10.2k, `living-course` 8.4k, `questionnaire` 6.8k, `topic-type-gift` 6.7k, `consultations` 6.5k, `templates-email` 4.2k, `lti` 4.1k, `tasks` 4.1k, `payments` 4.0k, `tenancy` 3.8k, `webinar` 3.7k.

Framework features the code leans on, which every target language must replace:

| Laravel / PHP piece | Used for | Notes |
|---|---|---|
| Eloquent ORM (207 models, polymorphic relations, soft deletes, query cache trait) | all persistence | the biggest hidden cost: relations, scopes, casts, events |
| Laravel Passport (OAuth2, personal access tokens, scoped tokens) | auth | needs a certified OAuth2/OIDC server or a port |
| `gecche/laravel-multidomain` + `tenancy` package | one process, one database, bucket, keys and Redis prefix per tenant | tenant resolution per request is core to every endpoint |
| Horizon and queues (26 jobs), scheduler (40 commands) | video conversion, imports, AI pipeline, notifications | long jobs (up to 5 h) |
| `spatie/laravel-permission` | roles and permissions | 51 policies on top |
| `l5-swagger` annotations | OpenAPI source | 223 files carry `@OA\` |
| FormRequest validation (395) and API resources (176) | request and response shapes | mechanical but large |
| `pbmedia/laravel-ffmpeg`, `intervention/image`, `smalot/pdfparser`, `dompdf`, `maatwebsite/excel`, `mjml-php` | media, PDF, Excel, mail | ecosystem availability differs per language |
| `packbackbooks/lti-1p3-tool`, `league/omnipay`, `google/apiclient`, `twilio`, `firebase` | LTI 1.3, payments, Google, SMS, push | LTI 1.3 and omnipay-style payment drivers have no equal in Go or Rust |
| Anthropic SDK, `opis/json-schema` | AI pipeline, blueprint validation | available in all three |

## 3. Method of the estimate

This is a **model**, because no module has been ported yet. Inputs:

1. Source size in lines (183k in packages plus about 2k in `api/app`), counted above. Tests are ported with each module and are included in the throughput.
2. **Throughput** in lines of legacy source ported per person-month, including tests, review, fixing parity bugs and docs, for one engineer working with AI coding agents (assumption, to be replaced by the spike in section 8): **TypeScript 7 000, Go 5 000, Rust 3 500**. The ratios reflect typical code expansion (TypeScript is shorter than PHP, Go longer, Rust longest with lifetimes and error types) and ecosystem gaps (more code written from scratch in Go and Rust).
3. **Fixed overhead** of 20 percent of the base for what is not module porting: the shared core (tenancy, auth, OpenAPI generation, queue runner, config), a parity harness that replays the PHP test scenarios and compares API responses, data and file migration tooling, the cutover and a rollback path, ops (images, compose, Helm), SDK, CLI and admin adjustments, and documentation.
4. Range: **0.6x to 1.6x** of the likely value, because the throughput is the biggest unknown and errors compound.

| | Base (lines / throughput) | Overhead (+20%) | **Likely** | Low | High |
|---|---|---|---|---|---|
| TypeScript | 185 000 / 7 000 = 26 | +5 | **31 person-months** | 19 | 50 |
| Go | 185 000 / 5 000 = 37 | +7 | **44 person-months** | 27 | 70 |
| Rust | 185 000 / 3 500 = 53 | +11 | **64 person-months** | 38 | 102 |

Calendar view for the single maintainer the project has today (with agents doing the first draft and the human reviewing): TypeScript about 2.5 years, Go about 3.5, Rust about 5. With a funded team of four engineers: about 14 to 20 months (TypeScript), 20 to 28 (Go), 28 to 40 (Rust). These figures exclude the roadmap itself: any feature work during the rewrite either happens twice (PHP and the new code) or waits.

## 4. Plan, common to all three languages (strangler, not big bang)

The API is headless and every client talks HTTP, so the migration can be done **path by path behind the existing Caddy gateway**, with both implementations sharing the PostgreSQL schema (one tenant database each) and the S3 buckets. A big-bang rewrite is rejected: it has no rollback and cannot ship value for years.

| Phase | Content | Share of base effort | Exit criterion |
|---|---|---|---|
| 0. Spike (4 weeks) | Port two contrasting packages (for example `tags`, small CRUD, and `questionnaire`, mid-size with reports) including their tests, auth middleware stub and OpenAPI output | about 2% | measured throughput replaces section 3; go/no-go |
| 1. Foundation | Tenant resolution, database access layer (migrations stay the source of truth), auth (OAuth2 tokens compatible with the existing `oauth_*` tables and scoped tokens), permissions, config, queue runner and scheduler, OpenAPI generation from code, structured logs, health, container image | about 15% | the new service answers `/api/profile` and a tenant-isolation test suite is green; Caddy routes a path to it |
| 2. Content core | `courses`, `topic-types`, `uploads`, `files`, `images`, `video`, `pdf`, categories, tags, lessons, progress, certificates | about 25% | the learner app and the admin can run a course end to end on the new service |
| 3. Standards | `scorm`, `cmi5`, `lrs`, `lti` (platform and tool), `h5p` index, LiaScript, Adapt, GIFT quizzes, questionnaires, tasks | about 20% | the conformance suites (ADL, saLTIre, cmi5 test) pass on the new service |
| 4. Commerce and live learning | `cart`, `payments` (Stripe, Przelewy24), `vouchers`, `invoices`, `consultations`, `webinar`, `jitsi`, `stationary-events`, notifications and templates (email, SMS, PDF) | about 20% | Wellms-compatible orders and entitlements verified, no lost access |
| 5. AI and Living Course | `ai`, `course-builder`, `living-course`, connectors, learner insights | about 12% | evals and cassette replays match; costs logged |
| 6. Cutover and removal of PHP | per-tenant switch, data check, rollback window, remove PHP images | about 6% | one release cycle with no PHP traffic |

Per-language specifics:

- **TypeScript:** Hono or Fastify for HTTP, Kysely or Drizzle for SQL (the existing SQL migrations stay), BullMQ on Redis for jobs, `node-oidc-provider` or a hand-written token layer over the existing `oauth_*` tables, Zod schemas shared with the SDK and the admin, OpenAPI generated from the Zod schemas, `pdfme` (already chosen for PDF), `ffmpeg` through child processes. Shares types and validation with admin, web, UI, SDK and CLI. Existing Node services (H5P, PDF) can be folded into the same process family.
- **Go:** chi or Echo, `sqlc` for typed queries, River or Asynq for jobs, Ory Fosite or Hydra for OAuth2, `excelize` for Excel, `chromedp` or pdfme for PDF, `ffmpeg` by exec. LTI 1.3 and SCORM runtime parts must be written (no maintained library of equal scope), plus payment drivers.
- **Rust:** Axum, `sqlx` (compile-time checked queries) or SeaORM, a job crate (Apalis) or a Postgres-backed queue, `oxide-auth` for OAuth2, `rust_xlsxwriter`, PDF through a headless Chromium or pdfme service. LTI 1.3, SCORM, cmi5, xAPI and the payment drivers must be written; the AI pipeline needs HTTP and JSON-schema validation crates (available).

## 5. Benefits, stated honestly

| Benefit | TypeScript | Go | Rust | Evidence in this repo |
|---|---|---|---|---|
| One language across API, admin, web, UI, SDK, CLI | **Yes** | no | no | 150k lines of TS already; shared Zod schemas would remove the generated-type step (`@ulams/sdk` is generated from OpenAPI today) |
| Contributors can work on any layer | **Yes** | partly | partly | one maintainer today; fewer languages lower the bar |
| Single static binary, trivial self-hosting, small images | no (Node runtime) | **Yes** | **Yes** | the Phase 8 stack launcher ships many containers today (API, workers, H5P, PDF, web, Caddy) |
| Lower memory per tenant | partly | **Yes** | **Yes** | `php.ini` allows 2 GB per request and up to 10 Horizon processes per supervisor; most of the footprint is PHP-FPM workers |
| CPU throughput | modest | high | highest | **not the bottleneck today**: the slow paths are ffmpeg, LLM calls, S3 and PostgreSQL; no load test of ULAMS exists (the requirements page says so), so a speed-up cannot be quoted |
| Compile-time type safety | good (strict TS) | good | **best** | PHP 8.4 with static analysis already catches a lot; the rewrite would remove PHP's dynamic edges (stringly-typed polymorphic columns, array payloads) |
| Memory safety, fewer classes of vulnerabilities | n/a | partly | **Yes** | the Phase 0 audit found logic and authorization bugs, not memory bugs: a language change does not remove those |
| Runs on Cloudflare Workers / edge | **Yes** | via WASM | via WASM | R&D item "Cloudflare deployment" in Phase 8 |
| Runs on the owner's MyDevil shared hosting | **Yes** (Node 22) | uncertain (binaries are allowed by the host policy only if approved) | uncertain | the shared-hosting variant today needs PHP 8.4 plus Node |
| Hiring and community | large | large | smaller | LMS/PHP community (Moodle, Wellms) is lost in all cases |
| Cleaner architecture than a forked 2017-era package set | possible | possible | possible | the same result is available by refactoring the PHP packages (deptrac is already in place) |

What does **not** improve: correctness of standards support, security posture, feature velocity (it drops during the rewrite), and time to the roadmap's differentiators (Living Course, Learner Insights, commerce).

## 6. Costs and risks

| Risk | Impact | Mitigation |
|---|---|---|
| Feature freeze or double work for 1 to 2 years | the roadmap (phases 3 to 8) stalls | strangler migration; new modules in the target language only |
| Regressions in standards (SCORM tracking, LTI 1.3 grade passback, cmi5, xAPI) | customers' packages break silently | keep the conformance suites as the gate; port them first in Phase 3 |
| Eloquent semantics (polymorphic relations, soft deletes, casts, events) are re-implemented by hand | data bugs | the morph-map work in the Phase 0 leftovers lane makes class names explicit first; golden-file API comparisons |
| Loss of the extension model (composer packages, Wellms plugins) and upstream lineage | differentiator "package-based extensibility" and the Wellms migration path (see `operators/migrate-from-wellms`) | define the new extension contract before Phase 2; keep a PHP compatibility service for migrators |
| Single maintainer, bus factor | rewrite never finishes | spike and gate every phase; stop rule: if the measured throughput is below half of the model, stop |
| Per-tenant data and file migration | downtime, lost access | schema stays identical; switch per tenant with a read-only window; rollback = route back to PHP |
| AI pipeline cassettes and evals must be re-recorded | cost, flaky evals | port with fakes first; re-record once |
| Wasted effort if the product pivots | sunk cost | do not start before the first paying customer or a funded team |

## 7. Comparison

| Criterion | TypeScript | Go | Rust |
|---|---|---|---|
| Effort (likely) | **31 pm** | 44 pm | 64 pm |
| Ecosystem fit for LMS (OAuth, PDF, Excel, LTI, payments) | **best** | medium | weakest |
| Reuse of existing code and types | **high** (150k lines) | none | none |
| Self-hosting footprint | medium | **very good** | **very good** |
| Performance headroom | adequate | high | highest |
| Risk of an unfinished rewrite | **lowest** | medium | highest |
| Fit with the one-maintainer reality | **best** | good | poor |
| Overall | **first choice if a move is wanted** | choose only for a gateway or edge component | choose only for isolated hot paths (for example a markdown/diff engine for Living Course, a video pipeline) |

## 8. Recommendation and decision rules

1. **Keep Laravel for the existing 66 packages through Phase 8.** The cost (31 to 64 person-months) is larger than all remaining roadmap phases together, and the product gains no customer-visible feature from it.
2. **If one language is the goal, choose TypeScript** and migrate by strangler: every new module (learner insights, outgoing webhooks, agents, commerce adapters) is built as a TypeScript service behind the gateway, using the shared Zod and SDK types. The existing H5P and PDF services already prove the pattern.
3. **Use Go or Rust only where they pay off locally:** a Go gateway or sidecar for rate limiting and webhooks delivery, or a Rust/Wasm module for a CPU-heavy part (text diff and chunking for Living Course, media analysis). These are weeks, not years.
4. **Run the 4-week spike** (phase 0 above) before any commitment: port `tags` and `questionnaire` to TypeScript with their tests, run the existing PHP tests as a black-box parity check against both, and record real lines per person-week. Re-estimate with that number. Stop rule: if the measured throughput is below 3 500 lines per person-month in TypeScript, drop the idea.
5. **Trigger to revisit:** a funded team of at least three engineers, or a hosting constraint the PHP stack cannot meet (for example a hard requirement for Workers or a single-binary distribution).

## 9. Assumptions and what would change the numbers

- Throughput figures (7 000, 5 000, 3 500 lines per person-month) are assumptions for AI-assisted porting with human review; they are the largest uncertainty. A 2x error in either direction moves the TypeScript estimate to 16 or 62 person-months.
- Line counts measure PHP only; configuration, templates and SQL are not counted, and generated OpenAPI is not counted.
- The fixed overhead (20 percent) assumes the PostgreSQL schema and S3 layout stay unchanged. A schema redesign adds 3 to 6 person-months.
- Excluded: re-implementing the admin or the learner frontend (already TypeScript), new roadmap features, security re-audit (budget about 1 to 2 person-months), and the owner's own time for decisions.
- No benchmark of ULAMS under load exists; performance statements are qualitative and must be measured before being used in decisions.
