# 0058. Rule-based risk scoring with reasons behind a RiskScorer interface

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/phase-4.md` (section 6)

## Context and problem statement

The spec requires transparent, rule-based scoring with human-readable reasons, thresholds that can
be configured per tenant and course, and an interface for a future ML scorer. Statuses are on track,
struggling and at risk, with the events `LearnerStruggling` and `LearnerAtRisk`.

## Considered options

1. A `RiskScorer` interface with a `RuleBasedScorer` of five rules: inactivity, failed attempts,
   slow element against the cohort median, abandoned element, and repetition (low weight). Reasons are
   message keys with parameters.
2. A weighted numeric score with thresholds.
3. An ML model now.

## Decision

Option 1:

- **Statuses.** Stored per learner, course and element, with reasons, scorer and version.
- **Transitions.** Kept append-only.
- **Events.** Fired on transitions only, carrying ids rather than `User` objects, so they never
  become notifications by accident. `LearnerRecovered` is added.
- **Evaluation.** A debounced job after signals, plus a nightly sweep for the time-based rules.
- **Cohort rules.** Need at least 10 learners.

## Consequences

- Good: every status can be explained in one sentence and tested with synthetic journeys.
- Bad: rules miss subtler patterns; the interface leaves room for a statistical scorer later.
