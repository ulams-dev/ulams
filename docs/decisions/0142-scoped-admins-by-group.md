# 0142. Scoped admins for partner and customer portals, scoped by group

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-remaining.md` (4.2); spec `docs/ROADMAP-PROMPT.md` 6.4 (portals) and 6.5

## Context and problem statement

Extended enterprise needs partner and customer admins who see reports for, and assign training to, only their own
learners. Permissions today are tenant-wide (`compliance_report`, `compliance_manage`); managers see only direct
reports. Groups already exist (`groups` with `parent_id`) and drive enrolment rules; the commerce plan links seat
packages to a group.

## Considered options

1. `compliance_admin_scopes` (user, group, covering descendant groups) and one `ScopeResolver` used by every
   reporting query, with permissions `compliance_scope_read` and `compliance_scope_assign`.
2. A sub-tenant per partner.
3. Row-level security in the database.

## Decision

Option 1. The resolver returns the user ids visible to the caller: everyone for tenant-wide permissions, members of
the scoped groups for scoped admins. It applies to the dashboard, every report dataset, exports, schedules, the KPI
impact view and the Learner Insights overview (where k-anonymity still applies inside the scope). Scoped admins work
in `/account/organisation` in the learner app, not in the admin app.

## Consequences

- Good: reuses groups, seat pools and rules; one place to test for leaks.
- Bad: every new reporting query must go through the resolver; a test per dataset enforces it.
- Bad: scoping covers reporting and assignment only, not course authoring or settings.

## Alternatives considered

- Option 2 multiplies databases and breaks tenant-wide reporting.
- Option 3 is not portable across the supported databases and is hard to test per request.
