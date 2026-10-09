# 0027. Course Builder access: one permission, author acts, admins look

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

Builder sessions hold uploaded sources (possibly confidential) and spend AI budget. Every endpoint
needs a policy and tenant isolation.

## Decision

- New permission `course_builder_use`, seeded for the `admin` and `tutor` roles
  (`CourseBuilderPermissionSeeder`, called from `PermissionsSeeder`; existing tenants get it when the
  seeder is re-run).
- A session is visible to its author and to tenant admins; only the author can act on it (upload,
  answer, approve, apply, publish, edit). Admins see sessions read-only.
- Every endpoint resolves its session first (directly or via a source, fragment, version, run or
  step) and answers 404 for unknown ids and 403 for another author's session. Data lives in the
  tenant database, so ids from another tenant are unknown ids.
- With `AI_DRIVER=disabled` (or no key) every builder endpoint answers 503 with a clear message;
  the rest of the LMS is unaffected.
- Limits (defaults, env-configurable): 20 MB and 300 pages per source, 400k source tokens per
  session, 3M tokens and USD 5 per session, 2 concurrent AI runs per author, 10 new sessions per
  author per day, USD 50 AI spend per tenant per month, USD 20 eval spend per month.

## Consequences

- Good: tutors build their own courses; admins can audit without taking over.
- Bad: an admin cannot finish a tutor's abandoned session; they can start their own from the same
  source.
