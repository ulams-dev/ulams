# 0042. No WebSocket server: Soketi and Pusher removed, Reverb only when a feature needs push

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L0-04)

## Context and problem statement

Soketi runs in compose and Caddy routes `ws.localhost` to it. Nothing uses it:

- The broadcast driver is `log` everywhere.
- No class implements `ShouldBroadcast`.
- No frontend loads Echo or pusher-js.
- The Course Builder streams over SSE (ADR 0011, ADR 0029).

The roadmap planned to "drop Soketi until realtime is needed; Laravel Reverb after 0.2".

## Considered options

1. Remove Soketi and `pusher/pusher-php-server`, and add nothing.
2. Replace Soketi with Laravel Reverb now.
3. Keep Soketi.

## Decision

Option 1:

- The broadcast connection defaults to `null`.
- The broadcast provider and the inert channel files are removed.
- `ws.localhost` and `metrics.localhost` are no longer routed.

A future feature that needs server push should first consider SSE through the existing event-log
pattern; Reverb is adopted only when bidirectional low-latency messaging is required, with its own ADR.

## Consequences

- Good: one container and one Composer dependency fewer, and no AGPL component (Soketi) in the stack.
- Bad: if WebSockets are needed later, the broadcasting setup has to be added back.
- Default pending #43.
