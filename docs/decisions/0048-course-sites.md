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
