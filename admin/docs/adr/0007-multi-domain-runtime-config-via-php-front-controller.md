# 0007. Per-domain runtime configuration through a PHP front controller

- Status: Accepted (retroactive)
- Date: 2024-02-14

## Context and Problem Statement

The API supports multi-domain (multi-tenant) deployments, where each domain has its own API URL, Sentry
DSN and so on. With the approach in 0006 one container could serve only one configuration, because the
values are written into `index.html` once at start.

## Considered Options

Not recorded.

## Decision Outcome

The runtime image switched from `httpd` to `php:apache`, and `config/php/index.php` became the entry
point: it reads `index.html`, looks up the request's `HTTP_HOST` and injects that domain's variables
before returning the page. First version (PR #1002): a mounted `env_config.php` with a `$domains` array.
September 2024 (PR #1091): configuration comes from env vars instead — `MULTI_DOMAINS=a.com,b.com` plus
variables prefixed with the upper-cased domain (dots/dashes → `_`, e.g. `A_COM_REACT_APP_API_URL`).
Today every `REACT_APP_*` / `VITE_APP_*` value (per-domain or global) is written as `window.<KEY>="…"` at
the `<!-- inject env variables -->` marker that `config/config.ts` emits, and `REACT_APP_SENTRY_RELEASE`
is derived from the image's `/version` file (January 2025, #1110/#1111).

### Consequences

- Good: one container serves many tenants; config stays in env vars, in line with the API's stateless config.
- Bad: a PHP runtime is now in the admin image only to template one HTML file.
- Bad: two injection mechanisms coexist (`sed` in `entrypoint.sh` and `index.php`).

## Evidence

- `a73e752e` 2024-02-14 "Migratin to new version of Ant Design Pro… (#1002)" (PR body: "adding support for
  multidomain") — `admin/config/php/index.php`, `admin/Dockerfile` (`FROM php:apache`),
  `admin/config/php/env_config.php`.
- `61e623a9` 2024-09-27 "Update index.php (#1091)" — `MULTI_DOMAINS` env parsing.
- `9465a5b0` / `07e11a67` 2025-01-02 "Feature/variables update (#1110/#1111)" — `admin/config/php/index.php`,
  `admin/config/config.ts`, `admin/src/services/sentry.ts`.
