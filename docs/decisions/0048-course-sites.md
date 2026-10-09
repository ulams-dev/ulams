# 0048. Course sites: publish into the current site by default; new sites through provisioning and session transfer

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L2-09)

## Context and problem statement

The spec says the author gets "a complete course application on its own tenant subdomain". A
tenant is a database, a bucket, keys and an env file (ADR 0007), provisioned by platform-only
commands. Builder sessions live in the tenant where they were created (ADR 0010).

## Considered options

1. Publish into the current tenant by default; platform admins can choose "new site", which
   provisions a tenant and moves the session there.
2. Always create a new tenant per course.
3. Only the current tenant.

## Decision

Option 1:

- **Provisioning.** `POST /api/platform/tenants` (platform admins) queues `TenantProvisioner`.
- **Session transfer.** `course-builder:session:export` and `course-builder:session:import` move the
  brief, versions, sources and fragments. Fragment IDs re-derive identically.
- **Author account.** The author is invited into the new tenant and finishes apply, theme and
  publish there.
- **Theme.** The interview's theme question changes the site theme, so in "current site" mode it is
  asked only of users who can change site settings.

## Consequences

- Good: the common case stays cheap, and the full "own site" flow exists for sales landings and demos.
- Bad: two publish paths, and the session transfer must be kept in sync with schema changes (round
  trip test).
- Default pending #53.

## Implementation notes (L2-09)

- **Platform API**: the platform tenant API of ADR 0078 (`platform_admin`, `TENANCY_PLATFORM_API`) is used as is; this record adds nothing to it.
- **Moving a session** is `course-builder:session:export` and `:import` (a tar with the brief, the current and applied
  versions, the sources with raw and normalised files, and every fragment). Fragment ids and blueprint element ids are
  kept; sessions, sources and versions get new ids; the source ids named inside the documents are rewritten.
- **From a tenant**, `MoveToNewSiteJob` runs `ulams:tenant:create` under the platform host (`TENANCY_PLATFORM_HOST`),
  exports, then runs the import in the new tenant through `TenantCommandRunnerContract`, which creates the author as an
  admin and sends a password-reset invitation. It needs `TENANCY_NEW_SITES=true` and `platform_admin`; progress is in
  the session state for the studio.
- The session in the original site is kept; the transfer never copies LMS entities.
