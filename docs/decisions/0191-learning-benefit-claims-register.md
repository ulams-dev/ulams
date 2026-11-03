# 0191. Register of learning-benefit claims, checked in CI

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/tech-debt-and-quality-bar.md` (TD-8); related ADR 0055 (course experiments)

## Context and problem statement

The quality bar says every feature that claims a learning benefit ships with an A/B experiment
definition and a delayed-retention metric. Several features make that claim (generative UI activities,
remediations, the adaptive interface, simulations), but the claims live only in prose: plans, the landing
and the docs. The experiments package of ADR 0055 is not built yet, so nothing links a claim to the
experiment that would support it, and nothing stops public copy from stating a benefit nobody measured.

## Considered options

1. Wait for the experiments package and define experiments there.
2. A register file now (`docs/experiments/claims.json`) with one entry per claim and its experiment
   definition, a config flag on the feature, and a CI script that checks both.
3. Drop benefit claims from public copy and do nothing else.

## Decision

Option 2.

- Each entry: `feature`, `claim`, `flag`, `experiment` (arms, primary metric `retention_score` measured
  3–7 days after completion, secondary metrics, minimum group size) and `status` (`defined`, `running`,
  `reported`). The fields match ADR 0055 so the experiments package can import them.
- A feature that claims a benefit sets `learning_claim: true` in its package config.
  `scripts/check-claims.mjs` (no dependency) fails when a flagged feature has no entry or an entry has no
  primary metric.
- Public copy (landing and docs data) may claim a learning benefit only for a feature whose status is
  `reported`; the same script checks the data files that carry feature status.

## Consequences

- The quality-bar item becomes checkable before the experiments package exists.
- Authors of new learning features write the experiment definition with the feature.
- Some current landing copy may have to be softened until results exist.
