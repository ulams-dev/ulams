# 0084. CLI and MCP commands for the course builder and Living Course

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (7.4, 7.5, 8); ADRs 0072, 0073, 0076

## Context and problem statement

The builder is driven through AG-UI runs: actions are posted to `sessions/{id}/runs` and the
interview, outline and apply cards arrive as A2UI surfaces on the SSE stream only. The session
snapshot does not contain the interview questions. An agent needs the whole pipeline (source,
interview, outline, generation, apply, publish, element chat, undo) without a browser, and an MCP
client needs to survive a generation that takes minutes inside a tool call.

## Decision

- One hand-written command per builder and Living Course endpoint (`builder …`, `living …`), plus a
  composite `builder start` that goes only as far as the flags allow (`--answers` or `--defaults`,
  `--approve-outline`, `--apply`, `--publish`) and otherwise returns the next question or review in
  `data.pending` with exit 0. Nothing past the interview happens without its flag: AI proposes, the
  author approves.
- The interview questions are read by replaying the stored events of the session (`builder interview
  show`), the same data the studio renders, so no new server endpoint is needed.
- `--wait` (default) polls `GET /api/admin/course-builder/runs/{run}` (S4) through a `builder-run:<id>`
  operation kind, and the session status for the multi-run stages; a failed run exits 9 with the run's
  error, AI off exits 12, a timeout exits 10 with the handle.
- `builder events` streams the AG-UI events as NDJSON (`{"type":"event","id","data"}`), resuming with
  `Last-Event-ID` across the server's 25 s cap and de-duplicating by id; `--until-run` ends on that
  run's `RUN_FINISHED` (0) or `RUN_ERROR` (9). It is CLI only (kind `stream`); MCP gets the bounded
  `builder_events_list`.
- Over MCP every long-running tool takes `wait` and `timeout_seconds`; the wait is capped at 55 s by
  default and, when it runs out, the result is a success with `status: running`, the handle and a
  `STILL_RUNNING` warning, so a client with a short tool timeout resumes with `operations_wait` instead
  of seeing an error.

## Consequences

- Good: a course can be generated from a Markdown file with one command, reviewed stage by stage by an
  agent, and every step is also an MCP tool from the same registry.
- Good: no server change; the S4 endpoint and the event stream already carry what is needed.
- Bad: replaying the event stream costs one short SSE connection per `interview show`; if it becomes a
  bottleneck a read endpoint for open surfaces is the follow-up.
- Bad: until the builder routes are in the OpenAPI spec the commands list them as undocumented
  (coverage reports them); the spec follow-up moves them to covered.
