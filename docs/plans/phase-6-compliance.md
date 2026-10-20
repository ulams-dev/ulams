# Phase 6: certificates and compliance

Status: approved by blanket instruction 2026-10-20, defaults used.

Scope is Phase 6.1 (certificates, mandatory training, compliance reports and audit export). The 6.2
rule engine and learning paths, the 6.5 dashboards and the 6.6 competency framework follow in later
milestones (section 9). SAML/OIDC/SCIM/HRIS (6.3) and commerce (6.4) are another lane.

## 1. Goal

A tenant can issue certificates that expire, can be verified by anyone with a link or QR code,
renew automatically before they lapse, and can be required of staff by a due date with escalation to
a manager. A compliance officer can export who completed what, when, in which course version, and
which certificate proves it.

## 2. What exists (extend, do not duplicate)

- `templates-pdf` already issues a `FabricPDF` row per `CourseFinished` event with a random
  `certificate_id` and a `@VarCertificateVerifyUrl` that the default template encodes as a QR code. The URL
  points to `{APP_URL}/certificates/verify/{id}`, which does not exist yet.
- `courses`: `course_user` carries `deadline` and `end_date`; `CheckForDeadlines` fires
  `CourseDeadlineSoon` hourly; `CourseFinished`/`CourseAssigned` events exist.
- `course-access` (`CourseAccessService`) is the domain service for granting and removing access.
- `templates-email` turns events into mail through registered templates; `notifications` stores in-app ones.
- Tenants have separate databases (ADR 0007), so every new table is tenant-scoped by construction.

## 3. Design

### 3.1 `templates-pdf` (extended)

Migration on `fabric_pdfs`: `expires_at` (nullable), `revoked_at`, `revoked_reason`, `course_version`
(nullable string, the course `updated_at` hash or version label at issue time), `superseded_by_id`.
`FabricPDF` gets `isValid()`, `status()` (`valid`, `expired`, `revoked`).

Public endpoint `GET /api/certificates/verify/{certificateId}` (throttled, no auth, no enumeration:
ids are random UUIDs): returns status, course title, holder name (configurable mask:
`full` default, `initials`, `none`), issued and expiry dates, issuer name. 404 for unknown ids, same
body shape for all statuses. Admin `POST /api/admin/pdfs/{id}/revoke`. OpenAPI annotations and
permission `fabricjs_pdf_revoke` through the existing permission seeder.

The reference frontend gets a `/certificates/verify/[id]` page (learner route, documented, WCAG 2.2 AA)
that calls the endpoint and renders the result.

### 3.2 New package `api/packages/compliance` (`Ulams\Compliance`)

A new package is justified because validity, requirements and escalation belong to no existing package
and `courses`/`templates-pdf` must stay free of compliance policy. It depends on `courses`,
`course-access`, `templates-pdf` and `templates` only through their events and services.

Tables (tenant database):

| Table | Purpose |
|---|---|
| `compliance_course_settings` | per course: `validity_days`, `recertify_before_days`, `reminder_days` (json, default 60/30/7), `mandatory`, `default_due_days`, `escalation_days` (json) |
| `compliance_assignments` | one required-training obligation: user, course, `due_at`, `source` (manual, rule, recertification), `status`, `completed_at`, `certificate_id`, `escalation_level` |
| `compliance_manager_links` | `user_id` unique to `manager_id`; filled by admin API now, by HRIS import later (6.3) |
| `compliance_notification_log` | idempotency: `(subject_type, subject_id, kind, offset_days)` unique plus `sent_at` |

Services (interfaces plus implementations, bound in the provider):

- `CertificateLifecycleService`: on `PdfCreated` for a course certificate, sets `expires_at` from the course
  settings; `revoke()`; `expiring($days)` query.
- `AssignmentService`: `assign(user, course, dueAt, source)` calls `CourseAccessService::addAccessForUsers`,
  sets the `course_user.deadline`, creates the assignment (idempotent per user, course, open assignment).
  A listener on `CourseFinished`... completion is detected from the existing progress completion event and
  closes the assignment with the certificate.
- `RecertificationService`: when a certificate is within `recertify_before_days`, opens a recertification
  assignment due on the expiry date (progress reset is described in section 8, question 2).
- `EscalationService`: for overdue assignments, notifies the learner and, by `escalation_days` offset, the
  linked manager; each notification is logged once.
- `ComplianceReportService`: query builder for completion rows (user, course, course version, assigned,
  due, completed, status, certificate id, certificate status, expiry); CSV and JSON streaming export.

Events (registered with `templates-email` so mail is templated and editable per tenant):
`CertificateExpiringSoon`, `CertificateExpired`, `TrainingAssigned`, `TrainingDueSoon`, `TrainingOverdue`,
`TrainingEscalated` (the manager receives it, with the learner and course).

Scheduler: one daily job `ProcessComplianceDeadlines` (reminders, expiry marks, recertification,
escalation), using the existing scheduler lock (ADR 0063 family). Every action is logged in
`compliance_notification_log` so reruns send nothing twice.

Endpoints (all with OpenAPI annotations, policies and tests): admin CRUD for course settings
(`/api/admin/compliance/courses/{course}`), assignments (`/api/admin/compliance/assignments`), manager links,
`GET /api/compliance/my-assignments` for learners, `GET /api/admin/compliance/report` (JSON, paginated,
filters) and `GET /api/admin/compliance/report/export` (CSV). Permissions `compliance_manage`,
`compliance_report` seeded by `CompliancePermissionSeeder`; a manager may read their reports' rows
(`compliance_team_read`).

### 3.3 Audit trail

The Phase 3 audit trail is the agent audit log (`AgentAuditLog`) and course version history. The compliance
export reads existing data (certificates, course version, assignments, progress) and adds its own append-only
`compliance_notification_log`; it does not rewrite history. Revocation records who and why.

## 4. Dependencies

None new. CSV export uses the framework's streamed response; PDF/QR rendering stays in `api/pdf`.

## 5. Migrations

`templates-pdf`: one migration (section 3.1). `compliance`: one per table. All reversible.

## 6. Tests

PHPUnit in each package: expiry computed on issue, verify endpoint for valid, expired, revoked and unknown ids
(and no other fields leaked), throttle, revoke permission, assignment idempotency, deadline written to
`course_user`, reminder, expiry and escalation timing with `Carbon::setTestNow`, notification dedup on rerun,
report columns and CSV, manager read scope, tenant isolation (a token of another tenant is rejected on every
admin endpoint, same technique as `adapt`). The LLM is not involved.

## 7. Risks

- Reminders for thousands of users: the job chunks and dispatches events, not mail.
- Course version at issue time is only as precise as the course data allows; the field stores the best
  available label and the report states it.
- Verification page shows a person's name: configurable mask, public by design of a certificate.

## 8. Open questions (defaults used)

1. Holder name on the public verification page: default `full`, config `initials` or `none`.
2. Recertification progress: default is to reset the learner's course progress through the existing
   progress service when the recertification assignment opens, and keep the old certificate valid until expiry.
3. Manager source until HRIS import exists: default the manual `compliance_manager_links` table.
4. Revoked certificates stay verifiable as "revoked" (never 404) so a printed copy shows its true state.

Owner-action issue: one combined issue for these defaults.

## 9. Milestones and PRs

1. This plan (docs only).
2. M1 certificates: `templates-pdf` expiry, revoke, verification endpoint, frontend verification page, docs.
3. M2 `compliance` package: course settings, assignments, reminders, recertification, escalation.
4. M3 compliance report and audit export.
5. Later, not in this pass if size demands: rule engine and learning paths (6.2), dashboards and BI export
   (6.5), competency framework (6.6), policy owner dashboard (6.1.1 parts that do not need the Living Course UI).
