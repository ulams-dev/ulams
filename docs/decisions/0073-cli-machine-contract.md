# 0073. CLI machine contract: JSON envelope, NDJSON, exit codes and error codes

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (4.5–4.8)

## Context and problem statement

Agents parse CLI output and branch on failures. Human-oriented output, prompts and generic exit code 1
make that brittle. The contract has to be stable across releases.

## Decision

- JSON mode prints one envelope on stdout: `{ok, contract, command, data, meta, warnings}` or
  `{ok: false, contract, command, error: {code, message, hint, status, retryable, requestId, details}}`.
  `data` is the API's `data`, unchanged. Streams and `--all` lists use NDJSON lines typed `item`, `event`,
  `progress` and a final `end` or error line.
- Output defaults to `auto`: human on a TTY, JSON when stdout is piped.
- Fixed exit codes: 0 ok, 1 internal, 2 usage/input, 3 auth, 4 forbidden/scope, 5 not found, 6 conflict,
  7 API validation, 8 rate limited, 9 server/network, 10 wait timeout, 11 confirmation required,
  12 feature disabled/unsupported server, 13 diff found. Each error code has a default hint naming a next
  command.
- Never prompt without `--interactive`; every prompt is a flag; `--input @file` supplies whole inputs.
- `--dry-run` on every mutating command; destructive commands need `--yes` when not interactive.
- `contract` is an integer; removing or renaming a field bumps it and needs a new ADR.

## Consequences

- Good: agents can rely on stdout JSON and exit codes without scraping text.
- Bad: every command must map its errors to the table; enforced by unit and snapshot tests.
