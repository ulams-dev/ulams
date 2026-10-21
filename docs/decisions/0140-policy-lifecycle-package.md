# 0140. Policy lifecycle as a package orchestrating Living Course, Course Builder and compliance

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-remaining.md` (4.3); spec `docs/ROADMAP-PROMPT.md` 6.1.1

## Context and problem statement

Spec 6.1.1 asks for a policy PDF turned into cited departmental course variants, an owner dashboard, and, on an
approved policy change, updated variants, revoked superseded certificates, notices with the source diff and a new
exam (minor change) or course (major change). Living Course already detects source changes and proposes reviewed
course updates; compliance assigns required courses and certificates prove completion. Nothing ties them to a
policy and its versions.

## Considered options

1. A new package `policy-lifecycle` that owns policies, versions, variants, rollouts and certificate links, and
   calls the existing services and events.
2. Policy fields added to `living-course` and `compliance`.
3. Policies as a mode of a Course Builder session.

## Decision

Option 1. A policy is one Course Builder source with Living Course revisions; a policy version points to a revision
and is approved by the policy owner. Each department gets its own course (a Course Builder session from the shared
brief with department overrides) and an exam-only course, both citing the same policy fragments. Approving a new
version applies one Living Course proposal per variant, all or none; the rollout then revokes certificates linked
to the previous version (`CertificateVerificationService::revoke`, reason `policy_superseded:v<N>`) and assigns the
exam or the course through `AssignmentService` with the new source `policy_change`. Certificates are linked to
policy versions in the package's own `policy_certificates` table, so `templates-pdf` stays unchanged. Every step is
written to the Living Course audit chain. The change class is suggested from the proposals; the owner may raise
it, and lowering it needs a recorded reason.

## Consequences

- Good: no policy knowledge in the four packages it uses; existing review, citation, reminder, escalation and
  report machinery applies to policy training unchanged.
- Good: a minor change reuses whole-course certification through the exam-only course.
- Bad: one extra generated course per variant; generation cost shown before it runs.
- Bad: superseded certificates become invalid at rollout; a grace period would be a later setting.

## Alternatives considered

- Option 2 couples compliance to Living Course and spreads one workflow over two packages.
- Option 3 cannot hold several departmental courses and their shared version history.
- Topic-level exam reassignment: compliance certifies whole courses; it would need a second completion model.
