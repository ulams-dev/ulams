# 0011. Error monitoring with Sentry and user feedback with Ybug

- Status: Accepted (retroactive)
- Date: 2021-11-19

## Context and Problem Statement

Admin users run the panel against many different installations. The team needed to see front-end errors from production and to collect bug reports with screenshots from non-technical users.

## Considered Options

Not recorded.

## Decision Outcome

- Ybug feedback widget (`src/services/ybug.ts`), added August 2021, enabled when `REACT_APP_YBUG` is set.
- Sentry (`@sentry/react`, `src/services/sentry.ts`), added November 2021, enabled when `REACT_APP_SENTRYDSN` is set. Since July 2023 the release builds upload source maps (`sentry:sourcemaps` script with `sentry-cli`, `devtool: 'source-map'`) and `docker_build.yml` creates a Sentry release `admin@<tag>` (org `escolasoft`, project `wellms-admin`).
- Both keys are runtime-injectable (see 0006/0007), so each self-hosted instance can use its own projects or none.

### Consequences

- Good: production errors arrive with de-minified stack traces tied to a release tag.
- Good: monitoring is optional and off by default for self-hosters.
- Bad: release builds need `SENTRY_AUTH_TOKEN` in CI; Inferred: unless removed after upload, `.map` files ship with the bundle.

## Evidence

- `3d870bde` 2021-08-20 "ybug (#67)" — `admin/src/services/ybug.js`, `admin/src/app.tsx`.
- `233a7f0e` 2021-11-19 "sentry (#218)" — `admin/src/sentry.ts`, `admin/package.json`, `admin/.github/workflows/pages.yml`, `admin/.env.staging`.
- `b9e6c465` 2023-01-09 "sentry && ybug" — `admin/src/services/sentry.ts`, `admin/src/services/ybug.ts`.
- `60f26804` 2023-07-25 "sentry source maps WELLMS-371 (#940)" — `admin/.github/workflows/docker_build.yml`, `admin/.github/workflows/pages.yml`, `admin/config/config.ts`, `admin/package.json`.
