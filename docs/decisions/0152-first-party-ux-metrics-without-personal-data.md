# 0152. First-party UX metrics stored in the tenant, without personal data

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-5-remaining.md` 4.8 (milestone P5R-12; open questions Q12, Q13)
- TODO: Phase 5 "Metrics: time to first course, time to first enrolment, admin task times"

## Context and problem statement

Time to first course is the key admin UX metric of the spec, with time to first enrolment and admin task completion
times. ULAMS promises no telemetry by default and easy self-hosting (principle 8). Umami (ADR 0101) covers only the
owned public sites; customer deployments carry no tracker.

## Considered options

1. A third-party product analytics tool in the admin.
2. Sending anonymous metrics to the ulams project.
3. Recording milestones and anonymous task durations in the tenant's own database, shown to its admins and
   aggregated for the operator through the platform API.

## Decision

Option 3.

- A `ux-metrics` package records milestones once from domain events into `ux_milestones`: first course created,
  first course published and first learner enrolment, measured from the provisioning time stored in
  `global.createdAt`.
- Task durations go into `ux_task_timings`: create course, publish, create user, enrol users, assign training,
  review an update proposal, export a report. The admin client sends them with `sendBeacon`. Rows carry the task,
  the duration, the outcome and the day; there is no user id, no IP and no entity id. Hidden-tab time is excluded,
  and rows are kept 180 days.
- Admins see medians on the dashboard. The operator sees milestones across tenants through
  `GET /api/platform/ux-metrics` and `ulams tenants metrics`.
- The data never leaves the installation. `ux_metrics.enabled` defaults to on and can be switched off.

## Consequences

- Good: the key metric exists for every installation without a tracker, cookie or third party.
- Good: the fields are documented and contain nothing personal.
- Bad: no per-admin breakdown, by design.
- Bad: the ulams project cannot see adoption numbers unless an operator shares them.
