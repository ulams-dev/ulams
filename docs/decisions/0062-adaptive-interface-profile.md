# 0062. Adaptive interface as a presentation profile over catalogue components

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/phase-4.md` (section 11.4)

## Context and problem statement

Phase 2.7 asks for interface-level personalisation (density, chunk size, navigation, visible
hints) driven by Learner Insights. It must stay within catalogue components and theme constraints
and be measured. This was moved to Phase 4 (pending #59).

## Considered options

1. A rule-derived presentation profile per learner and course, applied only through catalogue
   props. The learner can override it. Behind a flag, measured by an A/B experiment.
2. Model-generated layouts per learner.

## Decision

Option 1:

- **Profile fields.** `density`, `chunking`, `navigation` and `hints`, derived from statuses by rules
  and stored in `learner_presentation_profiles`.
- **Application.** `Prose` is split into `Steps`, plus initial hint counts and the outline mode. No
  model call and no model-written UI.
- **Flag.** `learner_insights.adaptive_interface`, default off.
- **Measurement.** An experiment with delayed retention, SUS/UEQ-S and NASA-TLX.

## Consequences

- Good: predictable, accessible, free to run and easy to explain.
- Bad: less expressive than generated layouts; generated remediations cover the content side.
