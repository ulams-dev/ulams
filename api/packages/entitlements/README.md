# Entitlements

What granted a user access to a course, with validity and a unique source (ADR 0130). Phase 6.4 CM-1 and CM-2.

## What it does

- `entitlements` table: `subject_type`/`subject_id` (a course), `status` (`active`, `revoked`, `expired`),
  `valid_from`, `valid_until`, `source_type`, unique `source_key`, `source_ref`, revocation reason.
- Source types: `order_item`, `admin`, `compliance`, `lti`, `wellms_order`, `migration` (written today);
  `seat`, `group_legacy`, `voucher_legacy` (reserved, nothing writes them yet).
- `EntitlementService`, the only writer of purchased access: `grantCourse()` (idempotent by `source_key`),
  `grantManual()`, `revoke()`, `revokeAllForUser()`, `expireDue()`, `project()` and `hasAccess()`. It reaches
  `course_user` only through the plain `CourseAccessService`, so policies, H5P and SCORM keep reading the same projection.
- `EntitlementCourseAccess`, a decorator on `CourseAccessServiceContract`: every access written by an admin, an LTI
  launch or a compliance assignment (`AccessSourceContext::as('lti', ...)`, from `course-access`) also gets an
  entitlement. An explicit removal revokes every entitlement of the user for the course (`admin_removed`).
- Revoking one entitlement removes `course_user` only when no other active entitlement remains; the latest end
  date wins and an open-ended entitlement beats a dated one. Progress is never deleted.
- `ulams:entitlements:expire` (hourly): marks lapsed entitlements `expired` and keeps the course row, so progress survives.
- `ulams:entitlements:parity` (nightly once a backfill has passed): compares `course_user` with the entitlements in force (same date rule as
  `Course::hasUser()`), stores a report in `entitlement_parity_reports`, fails on any difference. `--latest` prints the
  last report. The access backfill lives in the `commerce` package (it reads the Wellms cart tables).
- Access that exists without an entitlement is adopted as a `migration` entitlement before another source changes it.

## Settings

`ENTITLEMENTS_EXPIRE` (default `true`) and `ENTITLEMENTS_PARITY` (default `true`) switch the scheduled commands.

## Not yet (planned)

Seat pools and assignments (CM-9).

## Endpoints

None. The commerce package applies order events through this service; the existing admin access endpoints of
`course-access` now also record and revoke entitlements.
