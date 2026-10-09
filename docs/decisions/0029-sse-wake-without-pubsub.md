# 0029. The SSE endpoint wakes on a cache key, not Valkey pub/sub

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

ADR 0011 planned that the SSE endpoint wakes on a Valkey pub/sub ping with a 500 ms polling
fallback. A PHP subscriber blocks the worker inside `SUBSCRIBE` and cannot also enforce the 25 s
connection cap or notice a closed client without extra machinery.

## Decision

`EventLog::append()` writes the new event id to the cache key `course_builder:last_event:<session>`
(Valkey in the stack, per-tenant prefix). The stream loop reads that key every
`COURSE_BUILDER_SSE_POLL_MS` (500 ms) and queries the events table only when it changed. Without a
cache the loop polls the table. Connections still close after `COURSE_BUILDER_SSE_SECONDS` (25 s).
The separate FPM pool for the events route stays an operations step (Caddy route to a small pool).

## Consequences

- Good: no subscriber process, works with any cache store, the same code under Octane.
- Bad: up to 500 ms latency per event and one cache read per tick per open stream.
