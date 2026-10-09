# 0074. Scoped personal access tokens with an agent audit log and `Idempotency-Key`

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (6.1–6.5)

## Context and problem statement

Login tokens live 5 minutes (1 month with remember-me), carry no scopes and cannot be listed or revoked
by the user. Passport scopes are not defined. Agents need least-privilege, revocable, long-lived tokens,
a record of what they did (principle 7, spec 7.5) and safe retries.

## Considered options

1. Passport personal access tokens with scopes, a metadata table, scope enforcement from a route map,
   an audit log and an idempotency middleware.
2. A separate API-key system (own table, own guard).
3. Laravel Sanctum next to Passport.

## Decision

Option 1. Scopes are `<area>:read|write` (courses, users, enrolments, settings, events, certificates,
commerce, reports, lti, builder, living-course, learner, tokens, platform) plus `*`, with presets
(read-only, author, admin, learner, ci). A middleware enforces scopes only for tokens that have an
`api_token_meta` row, fails closed on unmapped routes, and narrows the user's existing permissions.
Tokens are returned once, prefixed `ulams_pat_` (stripped before Passport) for secret scanning, max
365 days. An append-only `agent_audit_log` records every mutating request made with a scoped token
(no bodies). `Idempotency-Key` replays responses for 24 h; `X-Request-Id` is accepted and echoed.
`GET /api/meta` reports capabilities.

## Consequences

- Good: one guard (`auth:api`) for everything; existing clients unchanged.
- Good: least privilege and an audit trail for agents.
- Bad: every new route must be added to the scope map (a coverage test enforces it).
