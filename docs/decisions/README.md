# Architecture decision records

Project-wide decisions for ulams, in [MADR](https://adr.github.io/madr/) format. Decisions that only
concern one application live next to it (`api/docs/adr`, `admin/docs/adr`, `front/docs/adr`; those
are retroactive records mined from the pre-monorepo history).

Workflow (see `CLAUDE.md`): the agent proposes an ADR with status **Proposed**; the product owner
approves it, which changes the status to **Accepted**. Superseded records stay and link to their
replacement.

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
| [0013](0013-adapt-build-worker.md) | Adapt Path B: JSON sources in the API, builds in an isolated GPL-3.0 worker | Proposed |
| [0014](0014-content-origin.md) | Third-party packages on a per-tenant content origin, files served through the API | Proposed |
| [0015](0015-h5p-service-tenancy.md) | H5P service per tenant: derived internal token, platform-only libraries, least-privilege mounts | Proposed |
| [0016](0016-liascript-without-scorm-package.md) | LiaScript: versioned Markdown documents played without a SCORM package | Proposed |
| [0017](0017-upload-guard.md) | One upload guard and safe extractor for every upload path | Proposed |
| [0018](0018-completion-events.md) | External content completes topics; completion events fire after progress is saved | Proposed |
| [0019](0019-nightly-conformance.md) | Conformance against real LMSs and builders in an opt-in nightly workflow | Proposed |
| [0022](0022-course-builder-studio-in-web-app.md) | Course Builder studio in the reference web app, with its own author session | Proposed |
| [0023](0023-a2ui-surfaces-as-ag-ui-activity-snapshots.md) | A2UI surfaces travel as AG-UI activity snapshots (`a2ui-surface`) | Proposed |
| [0024](0024-fake-llm-driver-cassettes-and-synthetic-answers.md) | Fake LLM driver: normalised cassettes and synthetic answers | Proposed |
| [0025](0025-blueprint-to-lms-mapping.md) | How a Course Blueprint maps to LMS entities | Proposed |
| [0026](0026-first-party-docx-converter.md) | A first-party DOCX converter instead of PhpWord | Proposed |
| [0027](0027-course-builder-access.md) | Course Builder access: one permission, author acts, admins look | Proposed |
| [0028](0028-generation-stages-and-grounding.md) | Generation stages, grounding check and quiz support check | Proposed |
| [0029](0029-sse-wake-without-pubsub.md) | The SSE endpoint wakes on a cache key, not Valkey pub/sub | Proposed |
