# 0085. The platform tenant API: operations, permission and what stays off

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (6.6); ADR 0078 (the API itself); owner question #79

## Context and problem statement

ADR 0078 decided on a platform-only HTTP API, off by default, to manage tenants with `ulams tenants …`.
Building it raised small decisions about who may call it, how a long creation is observed, what
the API may reveal and how it relates to the artisan commands.

## Decision

- **Gate.** `TENANCY_PLATFORM_API` (default false) and "this process serves the platform"
  (`TENANT_SLUG` empty) are checked by a middleware that runs before authentication and answers the
  same 404 as an unknown path, so a default installation and every tenant host reveal nothing.
- **Who.** A new permission `platform_admin` (given to the `admin` role by `AuthPermissionSeeder`; the
  platform API is the only thing it unlocks, and it does nothing on a tenant host where the routes
  do not exist). Scoped tokens additionally need `platform:read` (GET) or `platform:write`, the area that
  already existed in the scope map, and live at most 30 days on a platform host (ADR 0074).
- **Operations.** Creation and deletion are queued jobs (`ProvisionTenantJob`, `DeleteTenantJob`) that run
  the same `TenantProvisioner` steps as the commands and record each one in a platform-database table
  `tenant_operations` (`queued → running → succeeded|failed`, one entry per step with times and
  error). `GET /api/platform/operations/{id}` is what `--wait` polls. One operation per tenant at a
  time (409 with the running operation). Asking again for a tenant that failed resumes it at the first
  unfinished step, as the command does; an active tenant is a 409.
- **One implementation.** `TenantLifecycle` holds "resolve or resume a tenant row", "change inheritable
  settings" and "delete everything it owns"; the three artisan commands and the API call it, so
  they cannot drift. The commands keep their output.
- **What is shown.** Never a secret: no database password, `APP_KEY`, Passport keys; of the setting
  overrides only the key names. Overrides are limited to the inheritable allow-list
  (`ANTHROPIC_API_KEY`, `AI_*`), and the value of a key is write-only.
- **Delete.** The request body must carry the slug as `confirm` (HTTP 422 otherwise); the CLI asks for
  `--yes` and sends the slug itself.
- **Not in scope.** No UI, no tenant update endpoint beyond settings, no listing of operations beyond the
  latest 50, no automatic retry of a failed job.

## Consequences

- Good: remote, scriptable tenant management with the same safety as the commands, observable and resumable.
- Good: an installation that does not set the flag has no new reachable surface.
- Bad: a platform administrator with a token can create databases and buckets over HTTP; mitigated by the
  flag, the scope, the 30-day limit, the throttle (60 per minute per user) and the agent audit log.
- Bad: a queue worker for the platform must run for operations to progress (it does in the supplied stack).
