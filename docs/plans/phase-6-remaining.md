# Phase 6: remaining work (pdfme close-out, analytics UI and BI, policy lifecycle, path notifications)

Status: Proposed. The owner approved the batch of plans in advance; the defaults in section 9 apply unless
the owner overrides them.

Identity (6.3) and commerce (6.4, except the scoped reports of the partner portals) have their own plans. This
plan builds on `docs/plans/phase-6-compliance.md`, which is implemented (#273, #276, #280, #284, #286, #298).

## 1. Goal

- ReportBro is gone and the pdfme designer is proven in a browser; the TODO matches the code.
- An admin sees compliance and learning health on one screen (completion, results, struggle and recovery,
  certificates, overdue), schedules reports there, connects a BI tool with a scoped token and, optionally,
  sets training next to the business KPIs a tenant imports.
- A partner or customer admin sees and manages only their own learners.
- A policy owner uploads a policy, gets departmental course variants with every element cited, sees who is
  trained, and approves policy changes. An approved change updates the variants, revokes superseded
  certificates, reassigns an exam (minor change) or the course (major change) and issues a new certificate
  on completion. The whole history is auditable per tenant.
- Learning paths send the complete set of lifecycle notifications.

## 2. TODO items covered

Quoted from `docs/ROADMAP-TODO.md`:

1. 6.1 "(new) Remove ReportBro completely and replace it with **pdfme**" and its five sub-items.
2. 6.5 "Dashboards (completion, results, struggle/recovery, certificates, overdue)" and "BI export/API,
   scheduled reports, optional business KPI link"; 6.4 "Partner/customer portals with scoped admins and
   reports" (the reporting and scoping half; seat pools and sales stay in the commerce plan).
3. 6.1 "(new) Policy lifecycle delivery: link the uploaded policy version and cited fragments to all
   departmental course variants; use departments, duration, seniority and experience from the course brief;
   apply an uploaded style guide and provision distinct branded department domains (Coming)."
4. 6.1 "(new) Policy owner dashboard: show assigned people by department, completed and pending training,
   course version and issued PDF certificate; support the branded tenant learning application (Coming)."
5. 6.1 "(new) On an approved policy change, update affected course variants, invalidate superseded
   certificates, notify learners and policy owners with a source diff, assign a new exam for a minor change
   or a new course for a major change, and issue a replacement PDF certificate after completion (Coming)."
6. 6.2 "Learning paths with prerequisites; lifecycle notifications" (the notifications half).

## 3. Current state (explored on `main` at `4bde7dd1f`)

### 3.1 ReportBro and pdfme

Most of the removal is merged (`b9a1f137c`, #273, #335); the TODO notes at lines 728–754 are stale.

- No ReportBro service, route, env var, compose service or npm/composer package remains.
- Renderer: `api/pdf` (Express 5, MIT, `@pdfme/generator`, `@pdfme/common`, `@pdfme/schemas` pinned at
  6.2.4, worker threads, `X-Internal-Token`, OFL fonts, vitest `test/render.test.ts`), compose service
  `pdf`, published image, built and tested in CI.
- API: `templates-pdf` talks to it through `PdfRendererContract` / `PdfServiceRenderer`;
  `POST /api/admin/pdfs/preview`, fonts proxy, certificates rendered once and stored, themed templates in
  `resources/pdfme/certificate-*.json` for all demo academies (seeded by `DemoExperience::certificateContent`).
- Admin: `components/PdfEditor` on `@pdfme/ui` 6.2.4, reached from `/configuration/templates/pdf/:id`;
  admin Jest runs again (#335).
- Migration: `ReportBroConverter`, `ReportBroMigrator`, `templates-pdf:migrate-reportbro`, an automatic
  migration, a legacy banner in the editor, `LegacyTemplateException` (refuses to render ReportBro JSON).
- Verification: `GET /api/certificates/verify/{id}` and `front/web/src/pages/certificates/verify/[id].astro`.
- Open: the designer has never been exercised in a browser; there is no admin e2e test for it.

### 3.2 Analytics and reports

- `compliance`: `DashboardService` (`GET /api/admin/compliance/dashboard`: assignments, certificate health,
  per course), `ComplianceReportService` (datasets `assignments`, `certificates`, `audit`; paginated JSON,
  CSV and JSON lines export, logged in `compliance_exports`), `ReportSchedule` and `ScheduledReportService`
  (daily, weekly, monthly e-mail to tenant users), manager team view `GET /api/compliance/team/assignments`.
- `learner-insights`: `Analytics::summary` and `::elements` per course (struggle rate, remediation rate,
  recovery rate, on-track/struggling/at-risk counts), with groups under `min_group_size` withheld on the server.
  There is no cross-course aggregate.
- Scoped personal access tokens (ADR 0074, `auth/src/Support/TokenScopes.php`) with `reports` and
  `certificates` areas; outgoing webhooks (ADR 0103) emit `certificate.issued` but no training events.
- Admin (`admin/`, Umi Max + antd 5 + `recharts` 3): `/analytics/reports` shows four legacy pie charts from
  the `reports` package. There are no compliance, schedule, certificate-revoke, path or rule screens.
  `maatwebsite/excel` is already an API dependency. Admin has Playwright but no axe checks;
  `front/web` uses `@axe-core/playwright` 4.13.0.
- Groups (`auth`: `groups` with `parent_id`) and model-fields custom user fields (e.g. `department`) feed the
  enrolment rules. There is no scoped (partner/customer) admin concept.

### 3.3 Policy lifecycle

Nothing exists in code. The building blocks:

- Living Course (`api/packages/living-course`): revisions of a `course_builder_sources` row, frozen fragments,
  `living_course_fragment_changes` (the source diff, with magnitude), proposals with items classified
  `minor|major|answer_changed|removed`, `DecisionService`, `ApplyService` (records `version_from/to` in the
  hash-chained audit), `ProposalApplied` event, learner notices. UI in the Studio (`front/web/src/pages/studio/s/[id]/{sources,updates,audit}.astro`).
- Course Builder: brief schema `resources/schemas/course-brief/v2.json` (no departments, seniority or
  experience); PDF ingestion into cited fragments; one session per course; no course fork; branding is a theme
  preset plus accent; `SessionArchive` export/import is the nearest thing to a copy.
- Certificates: `fabric_pdfs` with expiry, revocation (`CertificateVerificationService::revoke`, event
  `CertificateRevoked` that nothing listens to), `course_version`, `superseded_by_id`.
- Compliance: `AssignmentService::assign` (a valid certificate auto-completes a new assignment unless the
  source is `recertification`), `CompletionService`, GIFT quizzes as exams; compliance certifies whole courses.
- Tenants: separate database per tenant, `front_host` per tenant, no host aliases.
- Landing: ADR 0098 (Proposed) tells the story as an illustrative workflow; `PolicyStory.astro`.

### 3.4 Path notifications

E-mail templates and in-app notifications (the `notifications` package stores every `Ulams*` event that
carries a user) exist for `PathEnrolled`, `TrainingAssigned` (each unlocked step), `TrainingDueSoon`,
`TrainingOverdue`, `TrainingEscalated`, `PathCompleted`. Missing: a completion notice per required course
(`TrainingCompleted`), the path context in the step mails, webhook events for training, and a test that
proves the whole matrix.

## 4. Design

### 4.1 pdfme close-out (area 1)

No new code paths; finish and prove.

- Admin e2e `admin/src/e2e/pdfDesigner.e2e.spec.ts`. The existing admin Playwright specs need a live backend
  and do not run in CI, so this milestone adds an `admin-a11y-playwright` CI job (built admin served statically,
  API answered with `page.route` fixtures, like `web-a11y-playwright` does for `front/web`). The spec:
  open a template, add a text field and a variable from the panel, preview (calls `/api/admin/pdfs/preview`),
  save, reopen; a ReportBro template shows the legacy banner and cannot be saved unconverted.
- Keyboard and screen reader pass over the designer chrome we own (variables panel, toolbar, banner); the
  pdfme canvas itself is a pointer tool, so the variables panel offers a keyboard alternative (insert variable
  at the selected field).
- Keep the converter, command and migration until the release after the next tagged release, then remove them
  through an upgrade step (ADR 0081) that fails loudly if a ReportBro template still exists.
- Update the TODO notes, tick the sub-items that are implemented, tested and merged; leave historical mentions
  in `docs/reports/phase-0-audit.md`, `docs/plans/phase-0.md` and ADR 0002 as history.

### 4.2 Dashboards, BI, KPIs and scoped reports (area 2)

**API**

- `learner-insights`: `Analytics::overview(int $days, ?array $courseIds)` and
  `GET /api/admin/learner-insights/overview?days=&course_id[]=`: per course `enrolled`, `active`,
  `struggleRate`, `recoveryRate`, `atRisk`, plus totals; courses under `min_group_size` come back as
  `{hidden: true}`. Same permission as `summary`.
- `compliance`: a dataset registry `Ulams\Compliance\Reports\DatasetRegistry` with a `ReportDataset` contract
  (`key`, `columns` with types, `query(filters)`, `row`). The three existing datasets move behind it unchanged;
  `audit` registers only when Living Course is installed (today a `class_exists` check), `course_health`
  registers from `learner-insights` (aggregates only, k-anonymity kept), `policy_training` from the policy
  package (4.3). `GET /api/admin/compliance/report/datasets` returns keys, column names and types, so BI tools
  and the admin build forms from it.
- Export formats: CSV and JSON lines (existing) plus XLSX through the existing `maatwebsite/excel` (no new
  dependency). The same three formats for scheduled reports.
- BI access: scoped tokens with `reports:read` on the report, datasets and export endpoints (they are already in
  the scope map; a coverage test proves it). Documented recipes, no connector code: Power BI and Excel (web
  connector, CSV/JSON), Metabase and Superset (JSON lines into a table via a small cron script), Looker Studio
  (CSV URL). Incremental pulls with `updated_since` on `assignments` and `certificates`.
- Webhooks: add `training.assigned`, `training.completed`, `training.overdue`, `path.completed`,
  `certificate.revoked` to `webhooks/src/Support/EventCatalog.php` (with sample payloads and versioning per ADR 0103).
- Business KPI link (optional): tables `compliance_kpis` (`key`, `name`, `unit`, `direction` up|down) and
  `compliance_kpi_values` (`kpi_id`, `group_id` nullable, `period_start`, `period_end`, `value`, `source`
  csv|api|manual), unique per KPI, group and period. `POST /api/admin/compliance/kpis/{id}/values` (JSON or CSV
  upload) and `GET /api/admin/compliance/kpis/{id}/impact?group_id=&from=&to=`, which returns the KPI series
  next to completion rate and overdue count for the same group and periods. The UI and docs say "shown side by
  side, not a causal claim"; no AI involved.
- Scoped admins (partner/customer portals, ADR 0142): table `compliance_admin_scopes` (`user_id`, `group_id`),
  a group covering its descendants (`groups.parent_id`). A `ScopeResolver` returns the user ids visible to the
  caller (all for `compliance_report`; members of the scoped groups for `compliance_scope_read`).
  Applied to the dashboard, every dataset, exports, schedules (recipients and rows), the KPI impact view and
  `learner-insights/overview` (k-anonymity applies inside the scope too). `compliance_scope_assign` lets a
  scoped admin assign required courses and paths only to members of their groups. The commerce plan links a seat
  package to a group; this plan needs only the group id.

**Admin UI** (`admin/`, antd + recharts, new section entries in `admin/config/routes.ts` under Analytics)

- `/analytics/compliance` `pages/Compliance/Dashboard`: KPI tiles (open, overdue, completion rate, average days,
  certificates valid/expiring/expired/revoked), per-course table sortable by overdue, struggle and recovery column
  from the overview endpoint (with "too few learners to show" for hidden groups), period switch 7/30/90, course and
  group filters, drill-down links to the report rows.
- `/analytics/compliance/reports` `pages/Compliance/Reports`: dataset picker from `/report/datasets`, filters,
  table, export buttons (CSV, JSON lines, XLSX), export history from `compliance_exports`.
- `/analytics/compliance/schedules`: CRUD on the existing schedule API.
- `/analytics/compliance/kpis`: KPI definitions, CSV upload with a preview, impact chart (two series, the KPI on
  its own axis, a data table under the chart).
- `/analytics/certificates`: certificate list with status and a revoke dialog with a required reason (the API
  exists; no admin UI today).
- `/users/scopes`: assign scoped admins to groups.
- `/courses/required-training`: paths, rules (with the evaluate preview) and course compliance settings. This was
  deferred by the compliance plan; it is the admin screen the dashboard drill-downs need. Marked `(new)` in the TODO.
- Charts: every chart has a text table equivalent, colours from the admin theme tokens with 3:1 contrast for
  marks, no meaning by colour alone, keyboard-focusable data points not required because the table carries the data.

**Partner portal UI** (`front/web`, learner app, branded per tenant): `/account/organisation` for scoped admins,
reusing the `account/team.astro` patterns: their learners, assignments, certificates, the scoped dashboard tiles
and exports. Partner admins never need the admin app.

### 4.3 Policy lifecycle (area 3, ADRs 0140 and 0141)

**New package `api/packages/policy-lifecycle` (`Ulams\PolicyLifecycle`).** Justified because it orchestrates
four packages (course-builder, living-course, compliance, templates-pdf) and none of them should know about
policies. It depends on their services and events only.

Tables (tenant database):

| Table | Purpose |
|---|---|
| `policies` | `title`, `owner_user_id`, `source_id` (`course_builder_sources`), `current_version_id`, `minor_due_days`, `major_due_days`, `status` active/archived |
| `policy_versions` | `policy_id`, `number`, `revision_id` (`living_course_revisions`), `status` draft/in_review/approved/rejected/superseded, `change_class` initial/minor/major, `summary` (cited), `submitted_by`, `approved_by`, `approved_at`, `effective_at` |
| `policy_variants` | `policy_id`, `department_label`, `group_id` (audience), `course_id`, `builder_session_id`, `exam_course_id` (nullable), `brief` (json: duration, seniority, experience), `brand_profile_id`, `host_alias_id` |
| `policy_brand_profiles` | `source_id` (the style guide), `tokens` (json: preset, accent, fonts, logo file), `citations` (json), `status` suggested/approved |
| `policy_rollouts` | `policy_version_id`, `variant_id`, `proposal_id` (`living_course_proposals`), `status` pending/ready/applied/rejected/failed, `course_version_to` |
| `policy_certificates` | `fabric_pdf_id`, `policy_version_id`, `variant_id`, `user_id` (links a certificate to the policy version without touching `templates-pdf`) |
| `policy_reassignments` | `rollout_id`, `user_id`, `kind` exam/course, `assignment_id`, `revoked_certificate_id`, `notified_at` |

Every state change is also written to the Living Course audit chain (`AuditLog::record`, new actions
`policy.version.submitted|approved|rejected`, `policy.rollout.applied`, `policy.certificate.revoked`,
`policy.reassigned`), so the existing audit export carries it.

**Flow**

1. *Upload.* The owner creates a policy with a PDF (`POST /api/admin/policies`). It becomes a Course Builder
   source and goes through the existing PDF ingestion into fragments. Version 1 is `draft`.
2. *Brief.* Course brief schema `v3.json` (course-builder) adds optional `departments` (list of label plus group
   id), `seniority` (junior/mid/senior/mixed), `priorExperience` (none/some/expert) and keeps `totalMinutes` for
   duration; the interview gains the question keys `departments`, `seniority`, `experience`. v2 briefs stay valid
   (the new fields are optional; the brief migrator fills defaults).
3. *Style guide.* An optional style-guide upload is a second source (untrusted input). One LLM call
   (`policy_brand_tokens`, logged in `ai_calls`, model from config) proposes theme tokens; each token cites the
   style-guide fragment it comes from; an author approves or edits them. Without AI the author picks tokens by hand.
4. *Variants.* For each department the package creates a Course Builder session from the shared brief with the
   department overrides and the same policy source, so each variant's elements cite the same policy fragments.
   Generation, critics and citation checks are the existing pipeline. An exam-only course per variant (one GIFT
   final test, questions cited to the policy) is generated too, for minor changes. Variants enrol their group
   through an enrolment rule (`group equals <id>`) on a path holding the variant course, so reminders, escalation
   and reports apply unchanged.
5. *Approval of version 1.* The owner approves the variants (the Studio review), the courses publish, the version
   becomes `approved`, and completion certificates are linked in `policy_certificates` on `PdfCreated`.
6. *Change.* A new PDF is uploaded as a new revision of the same source (existing `RevisionService`). The package
   opens `policy_versions` N+1 `in_review` with the Living Course source diff and asks for a proposal per variant
   (existing `ProposalService`, one rollout row each). The change class is suggested from the proposals (major if
   any item is `major`, `answer_changed` or `removed` on an assessed element, otherwise minor); the owner can raise
   it, never lower it below what the proposals say without a recorded reason.
7. *Approve.* The owner reviews the source diff and each variant's proposal (existing accept/reject per item),
   then approves the version: `ApplyService` applies every rollout under one job; if one fails, the version stays
   `in_review` and nothing below runs.
8. *Roll out.* For every learner with a live certificate linked to the previous version of an affected variant:
   revoke it through `CertificateVerificationService::revoke` with reason `policy_superseded:v<N>`; assign the
   exam course (minor) or the variant course (major) through `AssignmentService::assign` with the new source
   `policy_change` and due date `minor_due_days` / `major_due_days`; record a `policy_reassignments` row.
   Learners who never completed keep their open assignment and simply see the updated course.
9. *Notify.* Events `PolicyChangeApproved` (to the owner: counts per department), `PolicyRetrainingAssigned`
   (to the learner: what changed, the cited source diff summary, the due date and the link), and a listener on
   `CertificateRevoked` that sends `CertificateRevokedNotice`. All are registered with `templates-email`, appear
   in-app, and are webhook events. The diff summary is built from `living_course_fragment_changes`, not generated,
   so it needs no citation check beyond linking the fragments.
10. *Replacement certificate.* Completion issues a certificate as today; the listener links it to the new version
    in `policy_certificates` and `CompletionService` closes the assignment.

**Owner dashboard.** `GET /api/admin/policies/{policy}/dashboard`: per department (variant) assigned, completed,
pending, overdue; per person the course version, policy version of their certificate, certificate status and a link
to the PDF; filters by department and status; CSV export through the `policy_training` dataset. Permissions:
`policy_manage` (all policies) and `policy_owner` (policies where `owner_user_id` is the caller).

**Department academies (ADR 0141).** Distinct branded department domains inside one tenant:
`tenant_host_aliases` in the platform database (`tenant_id`, `host`, `label`, `theme` json, `default_group_id`),
resolved by `front/web/src/lib/tenant.ts` and the API tenancy middleware like `front_host`. An alias applies its
theme and shows the department's catalogue first; access is still decided by enrolment. Provisioning follows the
existing `TenantLifecycle`; DNS and TLS stay with the operator (documented, `.example.test` in demos and tests).

**Studio UI** (`front/web/src/pages/studio/policies/`, reusing the sources, updates and audit islands):

- `index.astro`: policies list with owner, current version, pending review badge.
- `new.astro`: upload, brief chat with the department, seniority and experience questions, style-guide upload and
  token review.
- `[id]/index.astro`: owner dashboard (tiles per department, people table, export).
- `[id]/versions/[n].astro`: source diff (existing word diff component), variant proposals side by side, change
  class with reason, approve and reject; a pre-flight summary of what approval will do (certificates to revoke,
  people to reassign, per department).
- `[id]/variants.astro`: variants, their courses, exam course, group, host alias, brand.

Learner side: the existing `account/training.astro` shows `policy_change` assignments with the "what changed"
summary; `certificates/verify/[id]` shows "revoked: superseded by policy version N" for revoked certificates.

**Demo.** A seventh demo experience, "policy" (`api/database/seeds/Demo/PolicyExperience.php`), with a short
original policy PDF, two departments, a v2 with one minor and one major change prepared as a pending version, so
the landing story (ADR 0098) has a working counterpart. Content is written for the demo (no third-party text).

### 4.4 Path lifecycle notifications (area 4)

- New event `TrainingCompleted` (learner; and the manager when the course is mandatory and the manager link
  exists) fired by `CompletionService` when an assignment closes; template variables include the certificate link.
- `PathVariables` added to the step events when the assignment belongs to a path (path name, step n of m, next
  step), so `TrainingAssigned` for an unlocked step reads "Step 2 of 4 of <path> is now open".
- Webhook catalogue entries (4.2).
- `PathLifecycleNotificationTest`: one learner through a three-step path with a clock: enrolled, step assigned,
  due soon, overdue, escalated, step completed, next step unlocked, path completed; asserts one mail and one
  in-app notification per event and nothing on a rerun.
- Learner notification list in `front/web` gets labels and links for every compliance notification type.

## 5. Dependencies

| Dependency | Where | Why | Licence, maintenance, size, self-hosting |
|---|---|---|---|
| `@pdfme/ui`, `@pdfme/common`, `@pdfme/schemas` 6.2.4 (existing) | `admin` | certificate designer | MIT, active releases, already bundled; no network calls at runtime (fonts come from our proxy) |
| `@pdfme/generator`, `@pdfme/common`, `@pdfme/schemas` 6.2.4 (existing) | `api/pdf` | rendering | MIT; stays pinned; upgrades go through Dependabot with the render tests |
| `@axe-core/playwright` 4.13.0 (new to admin, same version as `front/web`) | `admin` devDependency | WCAG checks on the new admin screens | MPL-2.0 (dev only, not shipped), Deque, active; no runtime or self-hosting impact |
| `maatwebsite/excel` (existing) | `compliance` | XLSX export | MIT, already in `api/composer.json` |

No other new dependency: charts use the existing `recharts`; the policy package uses existing services.

## 6. Migrations

- `compliance`: `compliance_kpis`, `compliance_kpi_values`, `compliance_admin_scopes`; `AssignmentSource` gains
  `policy_change` (string column, no schema change).
- `policy-lifecycle`: the seven tables in 4.3.
- `tenancy` (platform database): `tenant_host_aliases`.
- `course-builder`: brief schema `v3.json` plus a brief upgrader (no table change).
- Permissions seeders: `compliance_scope_read`, `compliance_scope_assign`, `policy_manage`, `policy_owner`.
- All reversible; no data rewrite of existing tables.

## 7. Tests

- Tenant isolation for every new endpoint (KPIs, scopes, datasets list, overview, policies, versions, variants,
  dashboard, host aliases), same technique as `compliance/tests/Api/TenantIsolationTest.php`; host aliases tested
  so a host never resolves to another tenant.
- Scope tests: a scoped admin sees only members of their groups and descendants in every dataset, export, schedule,
  dashboard and overview, and cannot assign outside them.
- k-anonymity: overview and `course_health` never return a group under `min_group_size`, also inside a scope.
- Token scope coverage test for every new route; XLSX, CSV and JSON lines produce the same rows.
- Policy flow (PHPUnit, mocked LLM through the fake responders): upload, variants cite only policy fragments
  (citation coverage check), brand tokens cite the style guide and stay `suggested` until approved, version approval
  applies every rollout or none, revoke plus reassign per change class, no reassignment for learners without a
  certificate, idempotent rerun of the rollout job, replacement certificate linked to the new version, audit chain
  verifies, notifications once each. Style guide and policy text with injected instructions do not change tool
  calls or output schema.
- An eval fixture (policy PDF, two departments) for the real-model eval command: citation coverage, quiz answers
  supported, duration within the brief, cost.
- Admin: Jest for helpers, Playwright with axe (WCAG 2.2 AA) for the dashboard, reports, schedules, KPIs,
  certificates, scopes and required-training pages, plus the pdfme designer flow.
- `front/web`: Playwright with axe for `/account/organisation`, the Studio policy pages and the learner training
  and verification changes; mock API fixtures in `front/web/tests/mock-api`.
- Existing suites stay green; H5P and SCORM untouched.

## 8. Risks

- Revoking many certificates at once: the rollout runs chunked in a queued job, shows a pre-flight count, and is
  idempotent per learner and version.
- Policy variants multiply generation cost: the brief shows an estimate per variant before generation, and the
  budget guard of the builder applies.
- Change classification can be wrong: the owner sees the suggestion and the reason, can raise the class, and the
  audit records who decided.
- Scope leaks are the main security risk of portals: one `ScopeResolver` used by every query, and a test per
  dataset.
- KPI correlation can be misread as impact: side-by-side view only, wording reviewed, no automatic conclusion.
- Host aliases touch tenant resolution: aliases are unique across the platform and validated like `front_host`.
- The pdfme canvas is not fully keyboard accessible upstream: the designer is an admin authoring tool; the keyboard
  path is the variables panel, and the limitation is documented.

## 9. Open questions (recommended default in bold; the default is used unless the owner overrides it)

1. Where the policy UI lives. **Studio in `front/web`**, because it reuses the Living Course review UI; the admin
   app gets only a link.
2. Departmental variants: separate tenants or one tenant? **One tenant, one course per department, branded host
   aliases** (ADR 0141); separate tenants would split one policy across databases.
3. Exam for a minor change. **An exam-only course per variant**, generated with the variant, so assignment,
   certificates and reports work unchanged; topic-level reassignment is not built.
4. When superseded certificates stop being valid. **Revoked at rollout** (the spec says "invalidate"), with the
   reason and the new due date in the notice; a grace period is a later setting.
5. Who approves a policy change. **The policy owner**; admins with `policy_manage` can act for an absent owner and
   the audit records it.
6. Change class rule. **Suggested from the proposals, owner may raise it, lowering needs a recorded reason.**
7. Due days after a change. **14 days for a minor change, 30 for a major change**, per policy.
8. Partner portal surface. **`/account/organisation` in the learner app**, not the admin app.
9. BI connectors. **No connector code; documented recipes over the scoped-token export API, with XLSX added.**
10. KPI link. **Manual and CSV/API import, side-by-side chart, no correlation statistics.**
11. ReportBro migration code. **Keep it until the release after the next tagged release, then remove it with an
    upgrade step.**
12. Demo policy academy. **Add a seventh demo experience with original content.**

Owner-only inputs (nothing blocks on them; defaults are used meanwhile):

- Approve ADRs 0140, 0141 and 0142 (status Proposed).
- The demo policy text and the two department names (default: a fictional "Remote work and data handling" policy,
  departments Engineering and Sales).
- Whether real department domains will ever be used beyond `.example.test` (affects only operator docs).

## 10. Milestones

| # | Slice | Demo |
|---|---|---|
| P6R-1 | pdfme close-out: admin Playwright CI job with mocked API and axe, designer e2e, TODO notes updated, sub-items ticked | design, preview and save a certificate in the admin; the test runs in CI |
| P6R-2 | Path lifecycle notifications: `TrainingCompleted`, path context, webhook entries, matrix test, learner labels | a learner walks a 3-step path and gets every notice once |
| P6R-3 | Dashboard API additions: `learner-insights/overview`, dataset registry, datasets list, XLSX, `updated_since` | `curl` with a `reports:read` token pulls `course_health` as XLSX |
| P6R-4 | Admin compliance dashboard, reports and schedules screens, certificate revoke screen | the admin dashboard with struggle and recovery next to overdue |
| P6R-5 | Admin required-training screens (paths, rules, settings) `(new)` | create a path and a rule, preview who it enrols |
| P6R-6 | Business KPI link (API, CSV import, impact chart) and BI recipes in docs | import a KPI CSV and see it next to completion |
| P6R-7 | Scoped admins and the partner portal `/account/organisation` | a partner admin sees only their company's learners |
| P6R-8 | Policy package core: policies, versions, upload, brief v3, variants generated per department, owner dashboard API | upload a policy, generate two cited departmental variants |
| P6R-9 | Policy change: version review, rollouts, revoke and reassign, notifications, replacement certificate link | upload v2, approve, see revoked certificates and new exam assignments |
| P6R-10 | Studio policy UI and learner-side changes | the full owner flow in the browser |
| P6R-11 | Style guide brand tokens and department host aliases | two department academies on `.example.test` hosts with their own look |
| P6R-12 | Policy demo academy and landing link | the landing story has a working demo |

Recommended first: **P6R-1**. It is small, closes an item the TODO still shows as half done, and adds the admin
axe setup that P6R-4, P6R-5 and P6R-6 need. P6R-2 can run in parallel.
