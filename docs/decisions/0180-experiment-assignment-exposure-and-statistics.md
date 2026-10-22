# 0180. Course experiments: salted assignment, exposure logging and fixed-horizon statistics with guard rails

- Status: Accepted
- Date: 2026-10-21
- Plan: `docs/plans/phase-4-experiments-and-analytics.md` (section 6)
- Refines: ADR 0055 (course experiments, consent model confirmed in #58)

## Context and problem statement

ADR 0055 chose an `experiments` package with `hash(user, salt)` assignment, delayed retention as the primary
metric and results only for groups of at least 20 per arm. It left open how arms are derived across tenants
and experiments, when a learner counts as part of an experiment, and how results are protected against
peeking and small samples. The remediation holdout already in `learner-insights` uses
`crc32('holdout:' . userId) % 100` with no salt: the same learners are the control group in every course,
and nothing records whether they ever met the feature.

## Considered options

1. Salted SHA-256 bucket per tenant and experiment, persisted at eligibility; exposure logged where the varied
   surface is used; fixed-horizon analysis with a planned sample, no interim numbers, a seeded bootstrap
   interval and automatic checks (sample ratio mismatch, attrition, a harm-only guardrail).
2. The same assignment with always-visible results and sequential (always-valid) intervals.
3. An external experimentation service (GrowthBook, Unleash, PostHog).

## Decision

Option 1:

- **Assignment.** `bucket = first 32 bits of sha256(tenantKey ‖ experiment.salt ‖ userId) mod 10000`; arms by
  cumulative weight. `tenantKey` is derived from the tenant's `APP_KEY`. The decision is stored at the
  eligibility moment and never recomputed; weights are frozen while running.
- **Exposure.** A learner counts from the moment the varied surface is used (or would have been, for the
  control arm). Analysis is intention-to-treat over exposed learners.
- **Planned sample.** 100 per arm by default (10-point effect on a 0–1 retention score, power 0.8, α 0.05).
  Authors may raise it, not lower it.
- **No peeking.** Before the planned sample or the maximum duration, results show progress only. Nothing is
  shown for an arm below 20 learners.
- **Estimate.** Difference of means with a 95 % bootstrap percentile interval (2 000 resamples, seeded by the
  experiment id); Bonferroni for three arms; secondary metrics labelled exploratory.
- **Checks.** χ² sample ratio mismatch (p < 0.001 withholds the verdict); attrition banner when check response
  rates differ by more than 10 points; automatic stop when treatment completion is worse than control with the
  whole interval below −10 points (after 50 per arm).
- **Scope.** Experiments never vary assessed content or affect grades, access, certificates or deadlines, and
  make no LLM calls.

## Consequences

- Good: arms are independent across experiments and tenants; results are reproducible and hard to misread.
- Good: the holdout becomes an ordinary experiment with exposure records.
- Bad: small courses will often end with "not enough learners"; authors wait for the horizon without interim
  numbers.
- Bad: sequential designs that allow early stopping for benefit are not supported.
