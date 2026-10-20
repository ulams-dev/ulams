# Compliance

Certificate validity, required training with due dates, recertification and manager escalation
(roadmap Phase 6.1; plan: `docs/plans/phase-6-compliance.md`). It builds on `courses`, `course-access`
and `templates-pdf` through their services and events and changes none of them.

## What it does

- **Course settings** (`compliance_course_settings`): `mandatory`, `validity_days`, `recertify_before_days`,
  `default_due_days`, `reminder_days`, `escalation_days`. `PolicyCertificateValidity` rebinds
  templates-pdf's `CertificateValidityContract`, so a new certificate gets the course's expiry date.
- **Assignments** (`compliance_assignments`): `AssignmentService::assign()` grants access through
  `CourseAccessService`, writes the due date to `course_user.deadline`, and is idempotent per user and
  course. A valid certificate satisfies the assignment at once; an expired one resets the learner's progress
  through `ProgressResetter` (default `EloquentProgressResetter`, replaceable).
- **Completion**: `CourseFinished` closes open assignments (only when the course is really finished: access
  removal fires the same event) and `PdfCreated` links the certificate and supersedes older ones.
- **Daily run** (`compliance:process`, 06:00): recertification, certificate reminders and expiry notices,
  due-soon and overdue notices, escalation to the manager (`compliance_manager_links`). Every notice goes
  through `NotificationLog::once()` (`compliance_notification_log`, unique per kind, subject, offset and
  recipient), so reruns send nothing twice.
- **Events** (templated through `templates-email`): `TrainingAssigned`, `TrainingDueSoon`, `TrainingOverdue`,
  `TrainingEscalated` (recipient: the manager), `CertificateExpiringSoon`, `CertificateExpired`.

## Report and audit export

`ComplianceReportService` serves three datasets, filterable by `course_id`, `user_id`, `status`, `from` and `to`:
`assignments` (who was required to do what, due, completed, overdue days, escalation level, certificate and its
status and expiry, course version), `certificates` (every certificate with holder, issue and expiry dates, status,
revocation reason and the course version it attests) and `audit` (the hash-chained Living Course audit entries,
only when that package is installed). The JSON report is paginated; the export streams everything as CSV (UTF-8
with BOM, cells starting with `=`, `+`, `-` or `@` are neutralised) or JSON lines, and each export is recorded in
`compliance_exports` (who, dataset, filters, row count). The course version of a certificate is the Living Course
version (`v<n>`) when there is one, otherwise the course's last update at issue time.

## API

| Method | Path | Who |
|---|---|---|
| GET, PUT | `/api/admin/compliance/courses/{course}/settings` | `compliance_manage` |
| GET, POST | `/api/admin/compliance/assignments` | `compliance_manage` |
| DELETE | `/api/admin/compliance/assignments/{id}` | `compliance_manage` |
| GET | `/api/admin/compliance/managers` | `compliance_manage` |
| PUT | `/api/admin/compliance/managers/{user}` | `compliance_manage` |
| GET | `/api/admin/compliance/report?dataset=` | `compliance_report` |
| GET | `/api/admin/compliance/report/export?dataset=&format=csv\|jsonl` | `compliance_report` |
| GET | `/api/compliance/my-assignments` | the learner |
| GET | `/api/compliance/team/assignments` | a manager, their direct reports only |

## Installing

- `php artisan migrate`
- `php artisan db:seed --class="Ulams\Compliance\Database\Seeders\CompliancePermissionSeeder"` (admin gets `compliance_manage` and `compliance_report`)
- config `ulams_compliance`: `reminder_days` (30, 7, 1), `escalation_days` (1, 7), `recertify_before_days` (30)

## Tests

`./vendor/bin/phpunit --testsuite compliance` (assignments, settings, completion, the daily run with a clock,
tenant isolation on every endpoint).
