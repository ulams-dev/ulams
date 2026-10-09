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
- A separate hazard stays documented, not changed: the `database` connection has `retry_after = 90` while a
  step may run up to 1800 s, so a long LLM call can be handed to a second worker. Set `retry_after` above
  the job timeout (admin docs).
