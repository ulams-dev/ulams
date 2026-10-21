# 0105. Stream tutor answers over the SSE event log

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-5-learner-ui.md` 3.6 (milestone M-UI6, open question O4)
- TODO: Phase 4.1 "(new) Streaming tutor answers (LLM client streaming over the SSE event log)"

## Context and problem statement

The course tutor (ADR 0060) answers in one HTTP request: `POST /api/tutor/courses/{c}/messages` retrieves
course chunks, calls `LlmClient::generate()` and returns the validated answer with citations. The learner sees
nothing until the whole structured output has been generated and validated, which takes several seconds on
the light model. ADR 0060 deferred streaming.

The Anthropic driver already consumes a stream internally but only returns the final message. The Course
Builder streams AG-UI events through an append-only table and an SSE endpoint with `Last-Event-ID` resume, a
25 s connection cap and a cache-key wake (ADR 0011, ADR 0029). ADR 0011 rejected streaming from the request
that runs the LLM call because a reload loses it, and ADR 0042 says new server push should first reuse that
event-log pattern.

Constraints that streaming must not weaken:

- Every answer cites course chunks; the semantic validator rejects unknown citation ids and markup.
- During an active quiz attempt, the answer-leak check (`Leak::detects`) needs the whole answer before
  anything reaches the learner.
- One `ai_calls` row per model call, with model, tokens and cost; the monthly tenant cap reads them.
- One `learner_signals` entry per answer (ADR 0057), and learner data stays out of the prompt.
- Tenant isolation, and a learner can only read their own conversations.

## Considered options

1. **Queued answer that appends AG-UI events to a `tutor_events` table, read over SSE** (the builder
   pattern).
2. WebSockets through Laravel Reverb and Echo.
3. Client polling of a run-status endpoint.
4. Stream directly from the `POST` request.
5. No streaming; keep the M-UI3 request.

## Decision

Option 1.

- **LLM client.** `LlmClient::stream(LlmRequest $request, callable $onDelta): LlmResult` is added next to
  `generate()`. Validation, the repair attempt, budget checks and logging stay in the same code path as
  `generate()`, so each model call still writes exactly one `ai_calls` row with model, tokens and cost. The
  Anthropic driver forwards text deltas; a small incremental extractor (no new dependency) pulls the
  `answer` string out of the partial JSON (the schema puts `answer` first). The fake driver replays its
  canned answer in chunks, so unit and feature tests never call a real model. Deltas are forwarded only
  for the first attempt; when a repair attempt runs, the draft stays in place until the final event
  replaces it.
- **Run.** `POST messages` with `stream: true` runs the same checks as today in the request (tutor
  enabled, `attend` gate, daily and monthly limits), creates or loads the conversation, stores the
  learner message and returns 202 `{conversationId, runId}`. It then queues `AnswerJob`. The job reuses
  `TutorService` for retrieval, the attempt guard, the leak check, the stored reply and the signal, so the
  two paths cannot drift apart.
- **Event log.** The job appends AG-UI events to `tutor_events`: `RUN_STARTED`, `TEXT_MESSAGE_START`,
  `TEXT_MESSAGE_CONTENT` (deltas), `TEXT_MESSAGE_END`, then `CUSTOM` `tutor.final` with the validated
  answer, citations, refusal, `inScope` and `remaining`, then `RUN_FINISHED`; or `RUN_ERROR`. Each append
  writes the new id to a cache key (ADR 0029). Deltas are coalesced into chunks of about 100 ms or one
  sentence so a run writes tens of rows, not one per token.
- **Migration.** One tenant-database table `tutor_events`: `id` bigint (the SSE id), `run_id` ULID,
  `conversation_id`, `type`, `payload` JSON, `created_at`; index `(run_id, id)`. Down drops the table.
- **The final answer is authoritative.** Streamed text is a draft. The client shows it with
  `aria-busy="true"` and does not announce it; `tutor.final` always replaces it with the validated answer
  and its citations. If validation fails, the draft is replaced by the standard "I couldn't answer that"
  message. Citations and the stored `Message` come only from the final result.
- **Nothing streams during an active quiz attempt.** When `AttemptGuard` reports an attempt, the job calls
  `generate()` instead of `stream()` and emits only `tutor.final` after the leak check. No partial text of
  a possibly leaking answer is ever written to the event log.
- **Reading.** `GET /api/tutor/runs/{run}/events` tails the log with `Last-Event-ID` resume, closes after
  25 s and the client reconnects; it sends a comment ping every 10 s and stops on `connection_aborted()`.
  The tailing loop is extracted from the builder's `EventStreamController` into a shared class in `core`
  instead of being copied. The BFF passes `text/event-stream` through with `X-Accel-Buffering: no`.
- **Backpressure and timeouts.** The job writes to the table, never to a socket, so a slow reader cannot
  stall the model call. A run has a job timeout (`tutor.stream.timeout_seconds`, default 60) and emits
  `RUN_ERROR` when it expires or the model call fails. One running answer per conversation; a second
  `POST` while a run is open gets 409.
- **Retention.** Run events are pruned one day after the run ends, and always with their conversation
  (90 days, ADR 0060), by the existing `tutor:prune` command. The stored `Message` is the record; the event
  log is transport.
- **Tenant isolation.** Events live in the tenant database. The events route resolves the run through the
  conversation owned by the authenticated learner and returns 404 for another learner's or another
  tenant's run. Tests cover both, as for every new endpoint.
- **Fallback.** The non-streaming `POST` stays and is the default. The client falls back to it without
  JavaScript, when the SSE connection fails before the first event, or when the run endpoint answers
  with an error. Operators can switch streaming off with `tutor.stream.enabled`.
- **Open question O4.** The M-UI7 eval measures time to first text with and without streaming. If streaming
  gains less than 1 second before the first text appears, we drop it: the setting goes off by default and
  this ADR is superseded, while the non-streaming path stays unchanged.

## Consequences

- Good: the answer appears as it is written, a page reload mid-answer resumes, and no new service, daemon or
  dependency is added.
- Good: citations, the validator, the leak check, cost logging and signals behave exactly as in the
  non-streaming path, because they still see the whole answer.
- Good: the builder and the tutor share one SSE loop.
- Bad: the first text waits for a queue worker and the cache wake (up to 500 ms), which eats into the gain
  for short answers. O4 decides whether that is worth it.
- Bad: one more table, one more queued job type and one more SSE connection per open answer on the FPM pool.
- Bad: a draft can show text that validation later rejects; it is marked busy, never announced and always
  replaced.

## Alternatives considered

- **WebSockets (Reverb).** Adds a daemon that ADR 0042 removed, needs its own auth and tenancy path, and
  gives nothing a one-way answer stream needs. Rejected.
- **Polling a run-status endpoint.** Simpler client, but each poll is a full authenticated request, the text
  arrives in coarse steps and latency is no better than SSE with the same 500 ms wake. Rejected; the client
  fallback is the plain request, not polling.
- **Streaming from the `POST` request.** Lowest latency, but a reload loses the answer, it holds an FPM
  worker for the whole model call, and the leak check would have to buffer anyway. Rejected for the same
  reason as in ADR 0011.
- **No streaming.** Keeps the code smallest and remains the outcome if O4 shows less than 1 second of gain.
