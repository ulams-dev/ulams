# 0061. Personalisation privacy: off by default, opt-out or consent, minimal data, explainable, erasable

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/phase-4.md` (section 9)

## Context and problem statement

Learner Insights profiles learners and sends course content to an LLM provider for remediations.
GDPR applies:

- a legal basis;
- transparency;
- Art. 22 (automated decisions);
- retention;
- access, export and erasure;
- data minimisation.

Workplace monitoring rules may also apply (works councils).

## Considered options

1. Module off per tenant. When on, legitimate interest with a per-learner opt-out, or a consent mode.
   Statuses never affect grades, access or HR. Reasons are always shown. Learners can export and
   erase. Retention is configurable. Individual statuses are visible to admins only by default.
2. Consent-only for every learner.
3. On by default.

## Decision

Option 1:

- **Learner controls.** Kept in `user_settings` (`insights.enabled`, `.help`, `.nudges`,
  `.consent`).
- **Rights.** Export and erase endpoints. `AccountDeleted` erases everything.
- **Retention defaults.** Signals 180 days, histories 365 days, tutor 90 days. Aggregates are shown
  only for groups of 10 or more.
- **LLM data.** A documented data-flow table per task. A DPIA template for tenants.
- **Visibility.** Instructors see aggregates unless the tenant allows individual views.

## Consequences

- Good: defensible defaults, and tenants can tighten them (consent) or widen them (instructor
  views) deliberately.
- Bad: off by default means fewer tenants try the feature without an admin decision.
- Defaults pending #60, #61 and #62.
