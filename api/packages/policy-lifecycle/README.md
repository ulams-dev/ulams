# policy-lifecycle: a policy document as cited training per department

Policy lifecycle (Phase 6.1 of `docs/ROADMAP-PROMPT.md`, ADR 0140, plan `docs/plans/phase-6-remaining.md` 4.3). A policy owner
uploads a policy, names the departments that must be trained on it, and gets one Course Builder course per department,
each lesson and quiz question citing the policy text. The owner reviews and publishes the courses in the Studio, approves
the first version, and sees who is assigned, who finished and which policy version their certificate certifies.

AI proposes, the owner approves: the package only prepares each department's Course Builder session up to the outline
review; approving outlines, generating, applying and publishing are the builder's own reviewed steps, and approving the
policy version is an explicit owner action. No AI decides who must take a course or whether anyone completed it.

- Tables: `policies`, `policy_versions` (the uploaded files, privately stored), `policy_variants` (department, audience
  group, Course Builder session and source, LMS course, enrolment path and rule), `policy_certificates` (an issued
  certificate tied to the policy version in force; `templates-pdf` is not changed).
- Permissions: `policy_manage` (all policies) and `policy_owner` (policies the user owns). The admin role gets both.
- API: `/api/admin/policies` (list, create, show, versions, variants, generate, approve, dashboard, CSV export).
- Departments run one after the other (a builder author may have two AI runs at a time); the cost estimate per department
  is shown on the policy before anything is generated.
- Approval enrols each department's group through a compliance rule (`group equals <name>`) on a one-course path, so
  reminders, escalation, certificates and the compliance reports apply unchanged.
- Every state change is written to the Living Course audit chain (`policy.*` actions).
- Policy changes (new versions, rollouts, revoking and reassigning) are the next milestone (P6R-9).

Install: registered in `api/config/app.php`; run the migrations and `PolicyPermissionSeeder` (part of `PermissionsSeeder`).
Tests: `php artisan test --testsuite policy-lifecycle`.
