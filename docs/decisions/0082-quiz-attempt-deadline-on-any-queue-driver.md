# 0082. Quiz attempt deadline holds on every queue driver

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

Starting a GIFT quiz attempt dispatched `MarkAttemptAsEnded` with `->delay($attempt->end_at)`. The `sync`
queue driver ignores the delay and runs the job at once, so the job set `end_at` to now and the attempt
ended the moment it was created (found in the Phase 3 e2e runs with `QUEUE_CONNECTION=sync`).

## Decision

- The deadline is the attempt's `end_at`, checked server-side on read and submit (`QuizAttempt::isEnded()`,
  the attempt policy). That does not depend on any queue.
- The delayed job is dispatched only when the default connection can delay
  (`MarkAttemptAsEnded::queueCanDelay()`: not `sync`, not `null`).
- The deadline job carries `atDeadline = true` and never ends an attempt whose `end_at` is still in the
  future, so a misconfigured driver cannot end it early. Submitting and the explicit "end attempt"
  endpoint still end it immediately.
- No scheduled sweep: there is no "finalised" marker column, and adding one is a data-model change that
  this fix does not need.

## Consequences

- With `sync` (or `null`) no event is fired when a deadline passes without a submit; scoring, status and
  access are still correct because they derive from `end_at`.
- Tests cover `sync`, `null`, `database` and `redis`.
