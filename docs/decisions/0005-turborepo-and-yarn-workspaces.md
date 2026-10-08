# 0005. Turborepo and Yarn workspaces

- Status: Accepted (2026-10-08)
- Date: 2026-10-08

## Context and problem statement

The monorepo holds two React applications, a Node service and a Laravel API. Developers need one
install, one lockfile and fast, cached builds; admin reads source from `front/src/lib`, so build
caching must know about that cross-folder input.

## Decision

- Yarn 1 workspaces (`admin`, `front`, `api`, `api/h5p`) with one root `yarn.lock`, Node 22.
- Turborepo tasks `dev`, `build`, `lint`, `typecheck`, `test`; admin's inputs include
  `$TURBO_ROOT$/front/src/lib/**`.
- `api/package.json` holds scripts only (Docker, artisan, phpunit) so the API takes part in the task
  graph without JavaScript dependencies.
- Husky + lint-staged at the root; vendored `src/lib` folders are skipped by formatters.

## Consequences

- Good: `yarn dev` starts everything; builds are cached and incremental.
- Bad: Yarn 1 is in maintenance mode; moving to pnpm or Yarn 4 is a possible later step.
- Bad: per-application Dockerfiles must build from the repository root.
