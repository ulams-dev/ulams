# Entitlements

What granted a user access to a course, with validity and a unique source (ADR 0130). Core of Phase 6.4 CM-1.

## What it does

- `entitlements` table: `subject_type`/`subject_id` (a course), `status` (`active`, `revoked`, `expired`),
  `valid_from`, `valid_until`, `source_type`, unique `source_key`, `source_ref`, revocation reason.
- `EntitlementService`, the only writer of purchased access: `grantCourse()` (idempotent by `source_key`),
  `revoke()` (idempotent), `project()` and `hasAccess()`. It reaches `course_user` only through
  `CourseAccessService`, so policies, H5P and SCORM keep reading the same projection.
- Revoking one entitlement removes `course_user` only when no other active entitlement remains; the latest end
  date wins and an open-ended entitlement beats a dated one. Progress is never deleted.
- Access that exists without an entitlement is adopted as a `migration` entitlement before the first grant.

## Not yet (planned)

Seat pools and assignments (CM-9), the expiry job, the access backfill with its parity check and the
`CourseAccessService` decorator (CM-2).

## Endpoints

None. The commerce package applies order events through this service.
