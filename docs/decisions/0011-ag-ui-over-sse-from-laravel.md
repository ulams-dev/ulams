# 0011. Builder streaming: AG-UI events over SSE from Laravel, carrying A2UI surfaces

- Status: Accepted (2026-10-09)
- Date: 2026-10-08

## Context and problem statement

The Course Builder chat must stream progress and render interactive UI chosen by the model
(spec 2.7): A2UI v0.9 (Apache-2.0) for *what* to render, AG-UI (MIT) for *how* agent and UI talk.
Generation runs for minutes in queue workers, must survive page reloads and worker restarts, and must
work for self-hosted installs with minimal moving parts. The API runs on PHP-FPM behind Caddy; Soketi
(AGPL) is present but due to be replaced, and the broadcast driver is `log`. The reference frontend is
an Astro SSR app with a plain TypeScript SDK and our own UI catalogue (ADR 0008).

## Considered options

1. **SSE from Laravel**: jobs append AG-UI events to a table; an SSE endpoint tails it with resume by
   event ID.
2. A small Node/TypeScript agent service that owns the LLM loop and streams AG-UI natively.
3. WebSockets through Laravel Reverb (or Soketi) and Echo.
4. Stream directly from the HTTP request that runs the LLM call.

## Decision

Option 1.

- Every interaction is an AG-UI run. Jobs append events (`RUN_*`, `STEP_*`, `TEXT_MESSAGE_*`,
  `STATE_SNAPSHOT`/`STATE_DELTA`, `ACTIVITY_*`) to `course_builder_events`; the event row ID is the SSE
  `id`.
- A2UI v0.9 messages (`createSurface`, `updateComponents`, `updateDataModel`, `deleteSurface`) travel
  inside AG-UI events — as activity events if that is the AG-UI convention for A2UI at implementation
  time, otherwise as `CUSTOM` events named `a2ui` — behind one adapter on each side.
- `GET …/sessions/{id}/events` streams with `Last-Event-ID` resume and a state snapshot on connect.
  Connections close after 25 s and the client reconnects, so FPM workers are not held indefinitely; the
  route is served by a separate small FPM pool through Caddy; the endpoint wakes on a Valkey pub/sub
  ping with a 500 ms polling fallback.
- User actions in surfaces start a new run: `POST …/runs` with an AG-UI `RunAgentInput`-shaped body;
  the action is validated against the surface the server issued.
- The model never emits raw markup; it may pick a catalogue component with props, validated against
  the catalogue manifest exported by `@ulams/ui`; unknown or invalid specs fall back to text.
- The frontend uses `@ag-ui/core` for types and our own small fetch-based SSE reader and A2UI renderer
  in `@ulams/sdk` / `@ulams/ui`; no CopilotKit, `@ag-ui/client` or `@a2ui/lit`.

## Consequences

- Good: one runtime owns LLM calls, auth, tenancy, limits and cost logging; domain services stay in
  Laravel. No extra service or daemon for self-hosters.
- Good: runs are resumable and replayable (debugging, evals, reloading the page).
- Good: standard protocols, so other AG-UI clients and A2UI renderers (and A2UI over MCP in Phase 7.5)
  can reuse the same events.
- Bad: SSE on PHP-FPM is not free; it needs the reconnect cap and a separate pool. Octane or FrankenPHP
  later removes the cost without changing the protocol.
- Bad: token-by-token streaming of model output is coarser (events are written per message chunk by the
  job); acceptable because course content is shown per element, not as a live typewriter.
- Rejected option 2 would split auth, tenancy and cost logging across two runtimes; option 3 adds a
  daemon and is not AG-UI's native transport; option 4 is not resumable.
