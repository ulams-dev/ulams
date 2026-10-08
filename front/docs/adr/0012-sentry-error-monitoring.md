# 0012. Sentry for error monitoring, with releases and source maps from CI

- Status: Accepted (retroactive)
- Date: 2021-11-18

## Context and Problem Statement

The demo runs on many customer/stage deployments; front-end errors are otherwise invisible.

## Considered Options

Not recorded. (`@sentry/react` was already listed in the pre-restart package.json.)

## Decision Outcome

`@sentry/react` is initialised in `src/sentry.ts` when a DSN is configured and the host is
not `localhost`. DSN, release and environment come from `window.VITE_APP_SENTRY*` (runtime,
[0009](0009-runtime-environment-injection-into-static-build.md)) or build env. Since the Vite
migration, `@sentry/vite-plugin` uploads source maps when `SENTRY_AUTH_TOKEN` is set, and
the Docker workflows create a Sentry release `front@<tag>` (org `escolasoft`, project
`wellms-front`); the PHP injector derives the release from `version.html`. Browser tracing
and session replay integrations are enabled. SDK upgraded to v8 in 2024-11.

### Consequences

- Good: errors are attributable to a released version with readable stack traces.
- Bad: session replay and tracing send user data to a third party; needs to be covered by
  the deployment's privacy policy.

## Evidence

- `5c38a2b2` 2021-11-18 "sentry (#117)" - `front/src/sentry.ts`
- `56109b24` 2021-11-19 "sentry stage"
- `055ea181` 2024-11-18 "updating sentry package (#509)"; `d572468a` 2025-01-03 "sentry update (#537)"
- current: `front/vite.config.mjs` (`sentryVitePlugin`), `front/.github/workflows/docker_build*.yml` (`getsentry/action-release`)
