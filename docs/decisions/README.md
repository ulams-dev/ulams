# Architecture decision records

Project-wide decisions for ulams, in [MADR](https://adr.github.io/madr/) format. Decisions that only
concern one application live next to it (`api/docs/adr`, `admin/docs/adr`, `front/docs/adr`; those
are retroactive records mined from the pre-monorepo history).

Workflow (see `CLAUDE.md`): the agent proposes an ADR with status **Proposed**; the product owner
approves it, which changes the status to **Accepted**. Superseded records stay and link to their
replacement.

The index below is generated from each record's title and `Status:` line: run `node scripts/adr-index.mjs`
(or `yarn adr:index`) after adding or changing a record, never edit the rows by hand. CI fails when it is stale.

<!-- BEGIN GENERATED: adr-index (run `node scripts/adr-index.mjs`, do not edit by hand) -->

| # | Title | Status |
|---|---|---|
| [0001](0001-monorepo-with-vendored-packages.md) | Monorepo with vendored packages | Accepted |
| [0002](0002-rename-to-ulams.md) | Rename EscolaLMS / Wellms to ulams | Accepted |
| [0003](0003-h5p-as-isolated-gpl-service.md) | H5P as an isolated GPL service (Lumi) | Accepted |
| [0004](0004-css-custom-properties-theming.md) | Theming with CSS custom properties, no styled-components | Accepted |
| [0005](0005-turborepo-and-yarn-workspaces.md) | Turborepo and Yarn workspaces | Accepted |
| [0006](0006-remove-recommender.md) | Remove the recommender package | Accepted |
| [0007](0007-tenancy-package.md) | Tenancy: database per tenant, provisioned by the tenancy package | Accepted |
| [0008](0008-reference-frontend.md) | Reference frontend: Astro SSR, framework-free SDK, schema-described UI catalogue | Accepted |
| [0009](0009-llm-layer.md) | LLM layer: an `ai` package on the official Anthropic SDK | Accepted |
| [0010](0010-course-blueprint-and-builder.md) | Course Builder: a versioned Course Blueprint applied through domain services | Accepted |
| [0011](0011-ag-ui-over-sse-from-laravel.md) | Builder streaming: AG-UI events over SSE from Laravel, carrying A2UI surfaces | Accepted |
| [0012](0012-lti-package-and-libraries.md) | LTI 1.3: one `lti` package, first-party platform side, packbackbooks tool side | Accepted |
| [0013](0013-adapt-build-worker.md) | Adapt Path B: JSON sources in the API, builds in an isolated GPL-3.0 worker | Accepted |
| [0014](0014-content-origin.md) | Third-party packages run on a per-tenant content origin, files served through the API | Accepted |
| [0015](0015-h5p-service-tenancy.md) | H5P service per tenant: derived internal token, platform-only libraries, least-privilege mounts | Accepted |
| [0016](0016-liascript-without-scorm-package.md) | LiaScript: versioned Markdown documents played without a SCORM package | Accepted |
| [0017](0017-upload-guard.md) | One upload guard and safe extractor for every upload path | Accepted |
| [0018](0018-completion-events.md) | External content completes topics; completion events fire after progress is saved | Accepted |
| [0019](0019-nightly-conformance.md) | Conformance against real LMSs and builders in an opt-in nightly workflow | Accepted |
| [0020](0020-public-competitor-comparison.md) | Public comparison with other learning platforms: sourced data, neutral values | Accepted |
| [0021](0021-ha-reference-architecture.md) | High-availability reference architecture | Accepted |
| [0022](0022-course-builder-studio-in-web-app.md) | Course Builder studio in the reference web app, with its own author session | Accepted |
| [0023](0023-a2ui-surfaces-as-ag-ui-activity-snapshots.md) | A2UI surfaces travel as AG-UI activity snapshots (`a2ui-surface`) | Accepted |
| [0024](0024-fake-llm-driver-cassettes-and-synthetic-answers.md) | Fake LLM driver: normalised cassettes and synthetic answers | Accepted |
| [0025](0025-blueprint-to-lms-mapping.md) | How a Course Blueprint maps to LMS entities | Accepted |
| [0026](0026-first-party-docx-converter.md) | A first-party DOCX converter instead of PhpWord | Accepted |
| [0027](0027-course-builder-access.md) | Course Builder access: one permission, author acts, admins look | Accepted |
| [0028](0028-generation-stages-and-grounding.md) | Generation stages, grounding check and quiz support check | Accepted |
| [0029](0029-sse-wake-without-pubsub.md) | The SSE endpoint wakes on a cache key, not Valkey pub/sub | Accepted |
| [0030](0030-living-course-revisions-and-update-proposals.md) | Living Course: source revisions and update proposals on top of the Course Blueprint | Accepted |
| [0031](0031-deterministic-fragment-change-detection.md) | Fragment-level change detection is deterministic | Accepted |
| [0032](0032-source-connectors-as-plugins.md) | Source connectors as plugins; Git through host APIs; one SSRF-safe HTTP client | Accepted |
| [0033](0033-progress-preservation-rules.md) | Progress preservation rules for content updates | Accepted |
| [0034](0034-tamper-evident-audit-trail.md) | A tamper-evident audit trail for Living Course | Accepted |
| [0038](0038-brand-identity-orbital-folio.md) | Brand identity: Orbital Folio | Proposed |
| [0039](0039-author-preview-of-draft-courses.md) | Author preview of draft courses runs on its own routes with the author's token | Proposed |
| [0040](0040-postgresql-17.md) | PostgreSQL 17 with a tested dump-and-restore upgrade | Proposed |
| [0041](0041-seaweedfs-and-per-tenant-s3-identities.md) | SeaweedFS replaces MinIO; per-tenant S3 identities; server-side reads use the internal endpoint | Proposed |
| [0042](0042-no-websocket-server.md) | No WebSocket server: Soketi and Pusher removed, Reverb only when a feature needs push | Proposed |
| [0043](0043-enforced-morph-map.md) | An enforced morph map with stable aliases for polymorphic types | Proposed |
| [0044](0044-content-security-policy-enforcement.md) | Content Security Policy: report collector, enforcement, tool origins from the API | Proposed |
| [0045](0045-h5p-state-through-bff.md) | H5P learner state through the BFF; no API token in the browser | Proposed |
| [0046](0046-cmi5-content-origin-and-launch-token.md) | cmi5 on the content origin with a one-time launch token and an LRS-only session token | Proposed |
| [0047](0047-openapi-attributes.md) | OpenAPI as PHP attributes; doctrine/annotations removed; spec snapshot test | Proposed |
| [0048](0048-course-sites.md) | Course sites: publish into the current site by default; new sites through provisioning and session transfer | Proposed |
| [0049](0049-commerce-provider-before-sylius.md) | A CommerceProvider interface before Sylius, with a Wellms cart adapter | Proposed |
| [0050](0050-lesson-content-type-registry.md) | Lesson content-type registry: deterministic LiaScript rendering and allow-listed H5P libraries | Proposed |
| [0051](0051-critic-loop-and-solvability-runner.md) | Critic loop with a retry budget and an isolated Playwright solvability runner | Proposed |
| [0052](0052-layout-topic-type.md) | Learner layouts as a Layout topic type rendered from the catalogue | Proposed |
| [0053](0053-simulations-sandbox.md) | Simulations: single-file HTML on the content origin, sandboxed, typed postMessage, off by default | Proposed |
| [0054](0054-component-playground-in-docs-site.md) | Component playground in the docs site instead of Storybook | Proposed |
| [0055](0055-course-experiments.md) | Course experiments with delayed retention, surveys and a tenant-level consent model | Proposed |
| [0056](0056-nginx-unprivileged-images.md) | Admin and legacy front served by nginx-unprivileged with runtime JSON config | Accepted |
| [0057](0057-learner-insights-signal-stream.md) | learner-insights package: an append-only signal stream keyed by blueprint element IDs | Proposed |
| [0058](0058-rule-based-risk-scoring.md) | Rule-based risk scoring with reasons behind a RiskScorer interface | Proposed |
| [0059](0059-personal-remediations.md) | Personal remediations: learner-scoped, grounded, cached per struggle pattern | Proposed |
| [0060](0060-ai-tutor.md) | AI tutor: course-scoped full-text retrieval, citations and an attempt guard | Proposed |
| [0061](0061-personalisation-privacy.md) | Personalisation privacy: off by default, opt-out or consent, minimal data, explainable, erasable | Proposed |
| [0062](0062-adaptive-interface-profile.md) | Adaptive interface as a presentation profile over catalogue components | Proposed |
| [0063](0063-tenants-inherit-platform-ai-settings.md) | Tenants inherit the platform AI settings, with per-tenant overrides | Proposed |
| [0064](0064-studio-applied-state-is-authoritative.md) | The studio reports "applied" only from authoritative state | Proposed |
| [0065](0065-demo-login-supports-tutor.md) | Demo login supports the tutor role | Proposed |
| [0066](0066-never-regenerate-app-key.md) | init.sh never regenerates an existing APP_KEY | Proposed |
| [0067](0067-quiz-time-limit-setting-key.md) | The quiz time limit default is read from `ulams_gift_quiz.max_quiz_time` | Proposed |
| [0068](0068-scheduler-minute-lock.md) | The scheduler loop claims each minute with a shared cache lock | Proposed |
| [0069](0069-ci-covers-web-ui-sdk.md) | CI typechecks, lints and tests the web app, ui and sdk | Proposed |
| [0070](0070-admin-node-24-shim.md) | The admin runs umi/max on Node 24 through a small shim | Proposed |
| [0071](0071-api-security-hardening-leftovers.md) | API security hardening: auth on admin routes, allow-listed payment input, bounded group walks | Proposed |
| [0072](0072-agent-first-cli-command-registry.md) | The agent-first `ulams` CLI: one command registry generates the parser, help, `describe`, MCP tools and docs | Proposed |
| [0073](0073-cli-machine-contract.md) | CLI machine contract: JSON envelope, NDJSON, exit codes and error codes | Proposed |
| [0074](0074-scoped-personal-access-tokens.md) | Scoped personal access tokens with an agent audit log and `Idempotency-Key` | Proposed |
| [0075](0075-device-login.md) | Device login with our own RFC 8628 flow approved in the web app; Passport's device grant stays off | Proposed |
| [0076](0076-local-mcp-server-from-cli-registry.md) | `ulams mcp`: a local MCP server (spec 2026-07-28, SDK v2) generated from the CLI registry | Proposed |
| [0077](0077-cli-distribution.md) | CLI distribution: npm, bun-compiled binaries and a Docker image; no telemetry | Proposed |
| [0078](0078-platform-tenant-api.md) | A platform-only HTTP API for tenant management | Proposed |
| [0079](0079-course-as-code-format.md) | Course-as-code: Markdown with directives + YAML, Blueprint v2 and a committed sync base | Proposed |
| [0080](0080-interactive-preview-in-the-studio.md) | Interactive preview in the studio: learner pages from the blueprint, in a frame | Proposed |
| [0081](0081-upgrade-command-and-step-registry.md) | `ulams:upgrade`: an idempotent per-tenant upgrade command with a step registry | Proposed |
| [0082](0082-quiz-attempt-deadline-on-any-queue-driver.md) | Quiz attempt deadline holds on every queue driver | Proposed |
| [0083](0083-course-builder-stage-advance-under-the-run-lock.md) | Course Builder: a stage change is one transaction under the run lock | Proposed |
| [0084](0084-cli-builder-and-living-course-commands.md) | CLI and MCP commands for the course builder and Living Course | Proposed |
| [0085](0085-platform-tenant-api-implementation.md) | The platform tenant API: operations, permission and what stays off | Proposed |
| [0086](0086-interactive-topic-type.md) | Interactive topic type: author-uploaded JavaScript packages in an opaque sandbox on the content origin | Proposed |
| [0087](0087-interactive-bridge-protocol.md) | The `ulams-ix` bridge protocol and the `@ulams/interactive-bridge` library (MIT) | Proposed |
| [0088](0088-separately-licensed-content-packages.md) | Content packages under `demo-content/`, played only as sandboxed content | Proposed (amended 2026-10-09) |
| [0089](0089-six-demo-academies-and-content-sourcing.md) | Six demo academies: three free interactive courses, one theme preset each, sourced content, EN/PL as two courses | Proposed |
| [0090](0090-living-course-implementation-choices.md) | Living Course: choices made during implementation | Proposed |
| [0091](0091-shared-hosting-cron-workers-and-manual-tenant-database.md) | Shared hosting: cron-driven workers and an operator-created tenant database | Proposed |
| [0092](0092-vps-cloudflare-hosting-reference.md) | Production reference: one VPS behind Cloudflare, flat tenant hosts, a tunnel and R2 | Proposed |
| [0093](0093-public-showcase-endpoint-and-landing-hero-interactives.md) | A public showcase endpoint and hero interactives on the demo landings | Proposed |
| [0094](0094-interactive-demo-courses-from-module-files.md) | Interactive demo courses are written as Markdown module files and seeded through the domain services | Proposed |
| [0095](0095-ulam-course-facts-sources-and-images.md) | The Ulam course: every statement traced to a fact sheet, sources numbered, four licensed photographs | Proposed |

<!-- END GENERATED: adr-index -->
