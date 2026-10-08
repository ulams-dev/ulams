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
| [0012](0012-lti-package-and-libraries.md) | LTI 1.3: one `lti` package, first-party platform side, packbackbooks tool side | Proposed |
