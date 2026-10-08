# 0009. Runtime configuration injected into the static build (`window.VITE_APP_*`)

- Status: Accepted (retroactive)
- Date: 2022-05-24 (first version); 2024-09-30 (current PHP-based version)

## Context and Problem Statement

A CRA/Vite build bakes `process.env`/`import.meta.env` values into the bundle. The demo is
shipped as one Docker image (`escolalms/demo`) that must point at different API URLs,
Sentry DSNs, routers and Firebase projects per deployment - and, since 2024, per domain in a
multi-tenant setup.

## Considered Options

Not recorded.

## Decision Outcome

Configuration is read first from `window.*` globals and only then from build-time env
(`config/index.ts`: `window.VITE_APP_API_URL || import.meta.env.VITE_APP_PUBLIC_API_URL`).

1. 2022-05: the multi-stage `Dockerfile` copies `index.html` to `tpl.html`; `entrypoint.sh`
   `sed`-replaces `API_URL = null`-style placeholders with container env vars on start.
2. 2024-09: the runtime image became `php:apache`; `config/php/index.php` serves
   `index.html`, inserts `window.VITE_APP_*="..."` (and legacy `REACT_APP_*`) for every
   matching env var at the `<!-- inject env variables -->` token, supports per-domain
   overrides via `MULTI_DOMAINS` + `<DOMAIN>_VITE_APP_*` vars, and adds the Sentry release
   from `version.html`.

### Consequences

- Good: one image for all environments and tenants; no rebuild to change the API URL.
- Bad: config is visible in page source (fine for public keys only).
- Bad: `entrypoint.sh` still runs the 2022 `sed` replacements although `index.html` no longer
  contains those placeholders. Inferred: they are now no-ops.

## Evidence

- `fef5aaed` 2022-05-24 "Update Dockerfile (#172)" - `front/Dockerfile`, `front/entrypoint.sh` (`tpl.html`)
- `95408afd` 2022-05-25 "docker build (#175)" - `front/entrypoint.sh`, `front/public/index.html`
- `6e58ee4b` 2024-09-30 "Feature/multidomain (#500)" - `front/config/php/index.php`, `front/Dockerfile`, `front/entrypoint.sh`
- `c6319d87` 2024-11-06 "Feature/multidomain (#505)"; `32003f17` 2024-11-27 "Feature/mod rewrite (#515)"
