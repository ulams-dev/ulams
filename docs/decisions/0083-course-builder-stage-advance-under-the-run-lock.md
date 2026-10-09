# 0083. Course Builder: a stage change is one transaction under the run lock

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

A generation run once stalled with several database queue workers. Reproduced with 2-4 `queue:work`
processes on the `database` connection and the fake LLM driver (`ConcurrentWorkersTest`): the run stayed
`running` at stage `quizzes` with every step `done` and an empty queue, or a stage was opened and closed
twice (two `STEP_FINISHED` events).

Cause: `GenerationService::advance()` closed a stage under the run lock but wrote the next `stage` as
`<next>:opening` and created the next stage's steps afterwards, outside the lock, in `openStage()`.
Between the two, a worker finishing its own step saw a stage with no steps, read it as "all done" and
advanced again. `openStage()` then wrote `stage` back to a value an overlapping advance had already
moved past. With no step left in flight nobody called `advance()` again, so the run never finished.
Not involved: job uniqueness (none), release/backoff (`tries = 1`), the SSE wake-up (a read-only cache
key). `dispatchWindow()` also counted running steps and claimed pending ones without a lock, so workers
could overfill the concurrency window.

## Decision

- `advance()` closes the stage, writes the next `stage` and creates the next stage's steps in one
  transaction under `lockForUpdate` on the run. A stage without steps (no quizzes asked for) is closed in
  the same transaction. There is no `:opening` state.
- Events, progress and job dispatch follow the commit and belong only to the worker that made the
  transition. A late worker sees the next stage open with pending steps and does nothing.
- `dispatchWindow()` counts and claims under the same run lock and dispatches after the commit.
- `ConcurrentWorkersTest` forks 1-4 real `queue:work` processes on the database connection against one
  session and asserts the run finishes and each stage opens and closes once.

## Consequences

- Workers briefly queue on the run row; the work inside the lock is a few queries.
- The separate `retry_after` hazard (below) is resolved by the amendment.

## Amendment (2026-10-09): long jobs get their own queue connections

The `database` and `redis` connections have `retry_after = 90`, while a Course Builder or Living Course step
runs up to 1800 s: a long real LLM call was handed to a second worker while the first still ran it (a double
call, double cost). The same held for other long jobs that ran on the default connection.

- New connections in `api/config/queue.php`, each in a `database` and a `redis` variant, with their own queue
  name (a queue name is shared by all connections of one driver, so the worker that pops it decides
  `retry_after`; the name must be dedicated):
  - `<driver>-builder`, queue `builder`, `retry_after` 2400 (`BUILDER_QUEUE_RETRY_AFTER`): `RunJob`, `StepJob`
    (1800 s, 1 try), Living Course `AnalyseGroupJob`, `ProgressRulesJob` (1800 s), `CheckSourceJob` (900 s) and
    the Adapt `BuildAdaptSource` (now with `$timeout = 900`; the HTTP call to the builder stays at 300 s).
  - `<driver>-long-job`, queue `queue-long-job`, `retry_after` 19000: `ProcessVideo` and `CloneCourse`
    (18000 s; the clone ran on the default connection so far). `redis-long-job` existed already.
- The jobs pick the variant that matches `QUEUE_CONNECTION` (`database` or `redis`); any other default
  connection (`sync`) is used unchanged. `COURSE_BUILDER_QUEUE(_CONNECTION)`, `LIVING_COURSE_QUEUE(_CONNECTION)`,
  `ADAPT_QUEUE(_CONNECTION)`, `VIDEO_QUEUE(_CONNECTION)` and `LONG_JOB_QUEUE(_CONNECTION)` still override.
- `api/workers.sh queue` starts a third worker per tenant for the builder queue with `--timeout=1800`
  (the long-job worker keeps `--timeout=18000`); Horizon has `supervisor-builder` with `timeout` 1800.
  Rule: worker `--timeout` >= job `$timeout` and < `retry_after`.
- H5P and SCORM imports and PDF rendering are synchronous HTTP calls, not queued jobs, so `retry_after` does
  not apply to them.
- `tests/Integrations/QueueRetryAfterConfigTest.php` asserts, per job and per driver, that its connection
  exists, has a dedicated queue and a `retry_after` above `$timeout` plus a margin; that every job in
  `packages/*/src/Jobs` with a timeout above the default connection's `retry_after` is routed; and that the
  worker and Horizon timeouts match.
- Jobs already queued on the old connection (`default` queue) finish there; set the variables above, restart the
  workers (`queue:restart`) and run a worker for `builder`.
