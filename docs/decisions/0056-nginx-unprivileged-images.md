# 0056. Admin and legacy front served by nginx-unprivileged with runtime JSON config

- Status: Proposed
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

## Consequences

- Good: smaller images, no PHP in static images, non-root.
- Bad: the apps' boot code changes, so runtime config needs a fetch before render.
