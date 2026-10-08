# 0006. Remove the recommender package

- Status: Proposed (removal requested by the product owner on 2026-10-08)
- Date: 2026-10-08

## Context and problem statement

`escolalms/recommender` is an HTTP client to external Python ML services (course-completion and
topic-type predictions, emotion/attention analysis of meeting webcam frames). The roadmap forbids
using, extending or depending on it; personalisation is built as the new, transparent
`learner-insights` module (Phase 4).

## Decision

Remove `api/packages/recommender` from the API composition together with everything that only
existed to feed it: its routes, settings, migrations' tables, configuration, tests and the admin and
front screens that displayed its predictions and meeting analytics.

## Consequences

- Good: no dependency on external ML services; no webcam-frame processing of learners (privacy).
- Bad: the "AI recording analysis" (emotion/attention) for consultations and webinars disappears
  until Learner Insights provides transparent, rule-based signals.
