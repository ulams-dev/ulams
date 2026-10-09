<!-- BEGIN:turborepo-agent-rules -->

# This is NOT the Turborepo you know

Turborepo configuration, task behavior, and CLI commands can vary between installed versions and may differ from your training data. Resolve the `turbo` package from this file's directory or relevant workspace; in monorepos, it may not be visible from the repository root. For example, run `node -p "require.resolve('turbo/package.json')"` from a workspace that depends on `turbo`.

Read `docs/README.md` inside that installed package first, then read the relevant pages from its `docs/` directory before changing Turborepo configuration or commands. Heed deprecation notices. These bundled docs match the installed package version and are available without network access.

This block is written and re-added by `turbo` before repository-scoped commands when an AI agent is detected. In the Turborepo source repository, its template is defined in `crates/turborepo-cli/src/cli/agent_guidance.rs`. Removing the managed block while updates are enabled means a later qualifying invocation will add it again. Set `"agentGuidance": false` in the root `turbo.json` or `turbo.jsonc` to opt out; this does not remove an existing block. Keep the block committed with your work to avoid an uncommitted change on the next agent invocation.
<!-- END:turborepo-agent-rules -->

# Working on ulams

Read [`CLAUDE.md`](CLAUDE.md) first: it holds the workflow (explore → plan → approval → small
commits → verify → update `docs/ROADMAP-TODO.md`) and the non-negotiables. This file adds the
practical map.

## Layout

- `api/`: Laravel API. Domain modules are vendored packages in `api/packages/<name>` (namespace
  `Ulams\`), autoloaded from `api/composer.json`; service providers are registered explicitly in
  `api/config/app.php`. New modules are new packages there, following an existing small package
  (e.g. `bookmarks_notes`: provider, routes, controllers with Swagger interfaces, requests with
  policies, resources, repositories/services behind contracts, permission enum and seeder, tests).
- `api/h5p/` (GPL) and `api/pdf/` (MIT): Node services reached over HTTP only. Never import code
  from `api/h5p` anywhere else, and never add GPL/AGPL code to the API, admin or front
  (`LICENSING.md`).
- `admin/`, `front/`: React SPAs. Shared libraries are in `front/src/lib` (`@ulams/*`).
- `docs/`: spec, tracker, plans, ADRs, reports, design briefs.

## Rules of thumb

- Styling: CSS Modules and `var(--ulams-*)` only (`front/src/lib/components/theme/README.md`).
  styled-components and `@lumieducation/*` imports are blocked by lint.
- LMS entities are created and changed through package repositories/services, never by writing
  tables directly; every new endpoint needs policies, Swagger annotations and a tenant isolation test.
- Mock the LLM in tests; no model names outside config.
- Commits: Conventional Commits, one concern per commit, tests in the same commit, no AI attribution.

## Running things

- Stack: `corepack yarn dev:api` (Docker), apps: `corepack yarn dev`.
- PHP commands run inside the API container:
  `docker compose -f api/docker-compose.yml exec api bash -c "php artisan …"`.
- PHPUnit: `./vendor/bin/phpunit --testsuite <package>` in the container, with
  `DB_HOST=postgres DB_DATABASE=test DB_USERNAME=default DB_PASSWORD=secret`.
- Tenants: `php artisan ulams:tenant:create|list|delete|sync-env`; artisan for one tenant:
  `php artisan <command> --domain=<slug>.localhost`.
- JS: `corepack yarn turbo run typecheck build lint test --filter=<workspace>`
  (workspaces: `admin`, `front`, `api`, `api-h5p`, `api-pdf`, `@ulams/docs`).
- Docs: `corepack yarn dev:docs` (port 4322); `corepack yarn workspace @ulams/docs coverage` lists modules, admin routes, learner pages and topic types without a page.
- Visual regression: `front/tests/visual/README.md`.
