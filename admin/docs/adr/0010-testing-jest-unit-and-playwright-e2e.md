# 0010. Jest for unit tests, Playwright (replacing Cypress) for end-to-end tests against a real API

- Status: Accepted (retroactive)
- Date: 2022-08-11

## Context and Problem Statement

The panel needed automated checks in CI. A month after the repository was created both unit and e2e suites were set up; the e2e approach was later replaced.

## Considered Options

- Cypress e2e against umi's mock server (`start-server-and-test start:mock … cypress-run`), March 2021.
- Cypress e2e against a dockerised `escolalms/api` (PR #528, August 2022).
- Playwright e2e against the dockerised API (chosen one week later, PR #555).

## Decision Outcome

- Unit/component tests: Jest with Babel transforms in `config/jest/*` (no ts-jest), Enzyme / Testing Library, run by `.github/workflows/unit.yml`.
- E2E: `@playwright/test` with `playwright.config.ts`; specs in `src/e2e/*.e2e.spec.ts` (login, logout, new course, user, voucher, consultation, …). `.github/workflows/e2e-playwright.js.yml` starts Postgres, Redis, MailHog and the `escolalms/api:latest` image as services, runs migrations/seeders and `passport:client` inside the API container, builds the admin against `http://localhost` and runs the specs. Cypress, its config and `eslint-plugin-cypress` were removed in the same PR that added Playwright.

Inferred: Playwright was preferred over Cypress for multi-browser support and speed; the PR does not say.

### Consequences

- Good: e2e tests exercise the real API contract, which matters because types are hand-written (0004).
- Bad: e2e depends on the latest published API image, so an API release can break admin CI with no admin change.
- Bad: the suite is small (about ten specs) and the 2024 migration PR notes "missing e2e tests".

## Evidence

- `c66b6f53`, `2bdc7f6f` … `d885a640` 2021-03-22 "cypress e2e …" — `admin/.github/workflows/e2e.js.yml`.
- `876e2e54` 2021-03-25 "jest ts unit test #5 (#7)" — `admin/config/jest/*`, `admin/.github/workflows/unit.yml`.
- `b243d5d6` 2022-08-04 "cypress tests (#528)" — e2e workflow runs `escolalms/api` image.
- `80b353c5` 2022-08-11 "playwright init (#555)" — `admin/playwright.config.ts`, `admin/tests/run-tests.js`, `admin/src/e2e/baseLayout.e2e.spec.ts`, `admin/package.json` (−cypress, +@playwright/test ^1.25.0), workflow renamed to `admin/.github/workflows/e2e-playwright.js.yml`.
- `045d972a` 2022-08-22 "login test (#562)", `b7bd0e23` 2022-08-24 "new course tests added (#567)".
