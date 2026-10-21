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

## Learning paths and enrolment rules (Phase 6.2)

- **Paths** (`compliance_paths`, `compliance_path_steps`): an ordered set of courses; a step lists the courses of
  the same path that must be done first (`prerequisites`; unknown ids, self references and cycles are rejected).
  Enrolling a learner (`PathEnrollmentService::enroll`, once per learner and path) turns each step into a required
  training assignment as soon as its prerequisites are done, so only startable courses are assigned. Finishing a
  course unlocks the next steps; finishing the last one completes the path. A course counts as done with a valid
  certificate, or, when it issues none, once it is finished.
- **Rules** (`compliance_rules`): conditions on user attributes (all must hold) enrol a user in a path, due in N
  days. Attributes: `group` (group names), a user column or a custom user field (model-fields), e.g. `department`,
  `role`, `location`. Operators: `equals`, `not_equals`, `in`, `contains`, `starts_with`, `ends_with`, `exists`
  (case-insensitive; hidden attributes such as passwords are unreadable). Rules run whenever a user is saved and in
  the daily `compliance:process`; a user is enrolled in a path once, so a rule never re-enrols someone who finished
  it or was removed by hand. `POST /rules/{id}/evaluate` previews (default) or runs a rule now.
- **Events** `PathEnrolled` and `PathCompleted` (e-mail templates registered); due, reminder and escalation
  notices come from the step assignments.

## Report and audit export

`ComplianceReportService` serves three datasets, filterable by `course_id`, `user_id`, `status`, `from` and `to`:
`assignments` (who was required to do what, due, completed, overdue days, escalation level, certificate and its
status and expiry, course version), `certificates` (every certificate with holder, issue and expiry dates, status,
revocation reason and the course version it attests) and `audit` (the hash-chained Living Course audit entries,
only when that package is installed). The JSON report is paginated; the export streams everything as CSV (UTF-8
with BOM, cells starting with `=`, `+`, `-` or `@` are neutralised) or JSON lines, and each export is recorded in
`compliance_exports` (who, dataset, filters, row count). The course version of a certificate is the Living Course
version (`v<n>`) when there is one, otherwise the course's last update at issue time.

## Competencies and skill profiles (Phase 6.6)

- **Framework** (`compliance_competencies`): a tree of competencies (area, skill), optional unique `code`; cycles are
  rejected, deleting a node lifts its children.
- **Course tags** (`compliance_course_competencies`): `status` `suggested` or `confirmed`. AI proposes, the author
  approves: `CompetencySuggester` makes one `competency_tags` call (logged in `ai_calls`, prompt
  `resources/prompts/competency_tags`) with the framework and the course's lessons as untrusted data; every returned
  competency id and lesson id is checked, each suggestion points at the lessons that teach it, and it stays
  `suggested` until an author confirms it (`PUT .../competencies/{competency}`) or rejects it (`DELETE`). A
  suggestion counts for nothing. Authors (`compliance_competencies`) tag the courses they author; admins any course.
- **Skill profile** (`SkillProfileService`): the confirmed competencies of the courses a learner has done (valid
  certificate, or finished when the course issues none), with `earned_at` and `expires_at`. It is the `competency`
  attribute of the enrolment rules (`not_equals` means "lacks") and the data Learner Insights reads.

## Dashboard and scheduled reports (Phase 6.5)

`DashboardService` answers `GET /api/admin/compliance/dashboard` (`compliance_report`): open, overdue and completed
assignments, completion rate, average days to complete, certificate health (valid, expiring in 30 and 90 days,
expired, revoked) and the same per course, most overdue first. Learner Insights metrics (struggle and recovery) are
not part of it yet. `ReportSchedule` rows (`/api/admin/compliance/schedules`) e-mail a dataset as CSV or JSON lines
daily, weekly or monthly (06:00) from `compliance:process`; recipients are tenant users, never free-text addresses.
For BI tools the report endpoints and the export are the API (scoped tokens, area `certificates`).

## API

| Method | Path | Who |
|---|---|---|
| GET, PUT | `/api/admin/compliance/courses/{course}/settings` | `compliance_manage` |
| GET, POST | `/api/admin/compliance/assignments` | `compliance_manage` |
| DELETE | `/api/admin/compliance/assignments/{id}` | `compliance_manage` |
| GET | `/api/admin/compliance/managers` | `compliance_manage` |
| PUT | `/api/admin/compliance/managers/{user}` | `compliance_manage` |
| GET, POST | `/api/admin/compliance/paths` | `compliance_manage` |
| GET, PUT, DELETE | `/api/admin/compliance/paths/{id}` | `compliance_manage` |
| POST | `/api/admin/compliance/paths/{id}/enrollments` | `compliance_manage` |
| GET, POST, PUT, DELETE | `/api/admin/compliance/rules[/{id}]` | `compliance_manage` |
| POST | `/api/admin/compliance/rules/{id}/evaluate` | `compliance_manage` |
| GET | `/api/compliance/my-paths` | the learner |
| GET | `/api/admin/compliance/report?dataset=` | `compliance_report` |
| GET | `/api/admin/compliance/report/export?dataset=&format=csv\|jsonl` | `compliance_report` |
| GET, POST, PUT, DELETE | `/api/admin/compliance/competencies[/{id}]` | read: `compliance_manage` or `compliance_competencies`; write: `compliance_manage` |
| GET | `/api/admin/compliance/courses/{course}/competencies` | manage, or `compliance_competencies` on an authored course |
| POST | `/api/admin/compliance/courses/{course}/competencies/suggest` | same |
| PUT, DELETE | `/api/admin/compliance/courses/{course}/competencies/{competency}` | same |
| GET | `/api/admin/compliance/users/{user}/skills` | `compliance_report` |
| GET | `/api/compliance/my-skills` | the learner |
| GET | `/api/admin/compliance/dashboard` | `compliance_report` |
| GET, POST, PUT, DELETE | `/api/admin/compliance/schedules[/{id}]` | `compliance_report` |
| GET | `/api/compliance/my-assignments` | the learner |
| GET | `/api/compliance/team/assignments` | a manager, their direct reports only |

## Installing

- `php artisan migrate`
- `php artisan db:seed --class="Ulams\Compliance\Database\Seeders\CompliancePermissionSeeder"` (admin gets `compliance_manage` and `compliance_report`)
- config `ulams_compliance`: `reminder_days` (30, 7, 1), `escalation_days` (1, 7), `recertify_before_days` (30)

## Tests

`./vendor/bin/phpunit --testsuite compliance` (assignments, settings, completion, the daily run with a clock,
tenant isolation on every endpoint).
