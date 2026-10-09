# 0068. The scheduler loop claims each minute with a shared cache lock

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

ADR 0021 left the scheduler as a single replica: `scheduler.sh` runs `ulams:tenant:schedule-loop` per
domain, and no scheduled task uses `onOneServer()`, so a second scheduler container would run every
reminder and daily job twice. Single replica is a single point of failure for scheduled work.

## Considered options

1. `onOneServer()` on every scheduled event. The events are registered by about 25 vendored packages,
   each in its own provider; every one would have to change, and new ones would be unsafe by default.
2. A leader lock held by one loop for as long as it lives.
3. A lock per minute and domain, taken by the loop before it runs the tick.

## Decision

Option 3, in `ScheduleLoopCommand`: `Cache::store()->getStore()->lock('ulams:schedule-tick:<tenant|platform>:<YmdHi>', 90)->get()`.
The winner runs the tick; the lock is not released so a replica that is seconds late skips the minute.
The cache is Valkey with a per-tenant prefix, so tenants do not share locks (the name also carries the
slug). A store that does not support locks, or `TENANCY_SCHEDULER_LOCK=false`, runs every tick. `--once`
stays a manual tick without a lock. The HA documentation drops the "exactly one" rule.

## Consequences

- Good: scheduler containers can be replicated for failover; no change to the packages' schedules, and
  every present and future task is covered.
- Bad: when the winner crashes mid-tick, that minute's tasks are skipped (the next minute proceeds).
- Bad: a replica whose clock is a minute off can run a minute twice; hosts need NTP.
