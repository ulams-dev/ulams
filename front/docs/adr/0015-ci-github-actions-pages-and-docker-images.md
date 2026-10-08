# 0015. CI on GitHub Actions: static analysis, GitHub Pages demo, Docker Hub images

- Status: Accepted (retroactive)
- Date: 2021-09-20 (Actions after restart); 2022-04-21 (Docker images)

## Context and Problem Statement

The front needs a public demo, PR checks and a deployable artefact for customer
installations (docker-compose stacks with the API and admin).

## Considered Options

- Travis CI was tried on day one (`d4ef64a9`) and replaced by GitHub Actions + Codecov the
  same day (`80fbb3e9`).

## Decision Outcome

GitHub Actions workflows in `front/.github/workflows/`:

- `node.js.yml` "static analysis": ESLint (`--max-warnings 25`) and `tsc --noEmit` on push
  and PRs. Unit tests (Jest, added 2021-10-21) are not run in CI.
- `pages.yml`: on `main`, builds with `VITE_APP_ROUTING_TYPE=HashRouter` against the stage
  API and deploys `dist/` to the `gh-pages` branch; notifies Mattermost.
- `docker_build.yml` (on release) and `docker_build_develop.yml` (on `main`, since
  2024-02-28): multi-arch (QEMU/Buildx) images `escolalms/demo:<tag>` / `:dev` pushed to
  Docker Hub, plus Sentry release ([0012](0012-sentry-error-monitoring.md)).

### Consequences

- Good: every merge to `main` produces a live demo and a `dev` image.
- Bad: no automated tests gate merges; the `e2e update` commits only bump versions.
- Inferred: these per-repo workflows must be re-scoped (paths filters) in the monorepo; a
  0100+ ADR should cover that.

## Evidence

- `221062c3` 2021-03-09 "tests ci"; `d4ef64a9` "travis"; `80fbb3e9` "codecov action" - removes `front/.travis.yml`
- `e5de8ed6` 2021-09-20 "github files (#23)" - `front/.github/workflows/node.js.yml`, `pages.yml`
- `910e4ae6` 2021-10-21 "jest"; `901a82d0` "Working tests"
- `299745e2` 2022-04-21 "Create docker_build.yml"
- `7b361bdf` 2024-02-28 "Build dev image (#437)" - `front/.github/workflows/docker_build_develop.yml`
