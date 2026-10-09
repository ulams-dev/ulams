# 0078. A platform-only HTTP API for tenant management

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (6.6); owner question #79

## Context and problem statement

Tenants are managed only with `ulams:tenant:*` artisan commands, which need shell access to the API
container. Platform admins and agents should manage tenants with `ulams tenants …`.

## Decision

Routes under `/api/platform/*` on platform hosts only, disabled unless `TENANCY_PLATFORM_API=true`, 404 on
tenant hosts. Tenant creation and deletion run as a queued job (the same `TenantProvisioner` steps) with an
operation-status endpoint; delete requires the slug as confirmation. Access needs the `platform_admin`
permission and a `platform:*` scoped token valid at most 30 days. The artisan commands keep working
(default, pending #79).

## Consequences

- Good: remote, scriptable tenant management with `--wait`, audit and scopes.
- Bad: provisioning becomes reachable over HTTP; mitigated by the off-by-default flag, platform-only
  routing, short-lived tokens and the audit log.
