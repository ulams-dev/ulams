# 0181. Improvement proposals from learner insights are Living Course proposals of their own kind

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-4-experiments-and-analytics.md` (section 5)

## Context and problem statement

The spec turns high-struggle elements into "a Phase 3 style update proposal to improve the shared course,
reviewed as a diff". Living Course proposals are built around two source revisions: `source_id`,
`from_revision_id` and `to_revision_id` are required, analysis reads the fragment changes between them, and
applying promotes the target revision. An improvement driven by learner difficulty has no source change.

## Considered options

1. One proposals table with a `kind` column (`source`, `insights`), nullable revision columns, an `evidence`
   JSON column, a dedicated `InsightsProposalService` and `ImproveRequest`, and a branch in apply that skips the
   revision steps. Same review screen, audit trail and progress rules.
2. Fake a revision pair (the synced revision on both sides) and reuse `ProposalService` unchanged.
3. Use the Course Builder element chat edit (`PatchService`) with a generated author request.

## Decision

Option 1. The evidence is aggregated only (rates, group size, top reasons, wrong-option shares, tutor
out-of-scope counts) and is shown to the author as "Why this was proposed". The model receives the element,
its cited fragments, the lesson objectives and the aggregated evidence; every patched block must cite the
provided fragments and pass the grounding check. Proposals require the minimum group size and count against
the session's Living Course budget.

## Consequences

- Good: progress rules (ADR 0033), learner notices, audit trail and remediation retirement work unchanged;
  authors review both kinds in one place.
- Good: the audit trail does not claim a source change that did not happen.
- Bad: `ApplyService` and `AnalysisService` gain a branch, and the revision columns lose their NOT NULL
  guarantee; existing code that assumes a revision must check `kind`.
- Option 2 was rejected because it records a false source update; option 3 because it bypasses the progress
  rules and the audit trail.
