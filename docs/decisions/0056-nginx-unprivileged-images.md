# 0056. Admin and legacy front served by nginx-unprivileged with runtime JSON config

- Status: Accepted
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L0-16)

## Context and problem statement

The admin and legacy front images serve static builds from PHP + Apache, only to inject runtime
settings. The product owner approved switching to nginx-unprivileged on 2026-10-09.

## Considered options

1. `nginx-unprivileged` with an entrypoint that writes `runtime-config.json` from an allow-list of
   env variables.
2. Keep PHP + Apache.
3. Bake settings at build time.

## Decision

Option 1:

- **Server.** Port 8080, non-root, SPA fallback, long cache for hashed assets.
- **Runtime config.** The apps fetch `runtime-config.json` before boot.
- **Headers.** Security headers and the CSP come from the reverse proxy (ADR 0044).

## Implementation notes

- Settings keep their names: the entrypoint writes every `REACT_APP_*` (admin) or `VITE_APP_*` (front)
  variable, so existing deployments only change the port (80 to 8080). The plan's `ULAMS_*` allow-list
  was not introduced, to avoid renaming settings that the apps and the docs already use.
- The page loads `runtime-config.json` with a synchronous request in an inline script, before any
  bundle, because Sentry and the tenant resolution read the values when their modules load.
- Source maps are removed from the images; `MULTI_DOMAINS` and the PHP front controller are gone.

## Consequences

- Good: smaller images, no PHP in static images, non-root.
- Bad: the apps' boot code changes, so runtime config needs a fetch before render.
