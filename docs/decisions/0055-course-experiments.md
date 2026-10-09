# 0055. Course experiments with delayed retention, surveys and a tenant-level consent model

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L2-23)

## Context and problem statement

The quality bar requires an A/B experiment with a delayed retention metric for every feature that
claims a learning benefit. The spec asks for experiments that are opt-in per tenant, with consent
where required. Phase 4 needs the same machinery for its remediation holdout.

## Considered options

1. An `experiments` package with deterministic assignment, delayed retention quizzes, SUS/UEQ-S and
   NASA-TLX surveys, and results by arm with bootstrap confidence intervals. Tenants opt in; learners
   get a notice and an opt-out; consent mode is optional.
2. An external experimentation service.
3. Always require explicit consent.

## Decision

Option 1:

- **Assignment.** `hash(user, salt)`.
- **Arms.** Arms switch between author-approved sibling topics.
- **Retention quiz.** Sent N days (3–7) after completion, built from the lesson's questions.
- **Results.** Shown only for groups of at least 20 per arm. No per-learner data is shown.
- **Opt-out.** Opted-out learners get the control arm and are excluded from results.
- **Consent mode.** In consent mode, nothing is assigned before consent.

## Consequences

- Good: we can measure learning impact ourselves and reuse it in Phase 4.
- Bad: small cohorts give wide intervals, and authors see "not enough data" often at first.
- Default pending #58.
