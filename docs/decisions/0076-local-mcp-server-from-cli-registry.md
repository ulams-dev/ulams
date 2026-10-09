# 0076. `ulams mcp`: a local MCP server (spec 2026-07-28, SDK v2) generated from the CLI registry

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (8); owner question #75

## Context and problem statement

Spec 7.5 asks for an MCP server; the TODO has an item for a first version on Cloudflare Workers. A
separate tool list would drift from the CLI. MCP's current spec is 2026-07-28 (stateless, no sessions,
`server/discover`, multi round-trip requests). The official TypeScript SDK v2
(`@modelcontextprotocol/server`) implements it; existing code is MIT, new contributions Apache-2.0.

## Decision

`ulams mcp` serves the registry's commands as tools over stdio (default) and Streamable HTTP on
loopback (bearer = a ulams token, forwarded per request). Annotations come from each command's kind
(read-only, destructive, idempotent). Destructive tools first return `CONFIRMATION_REQUIRED` with a plan
and a single-use confirm token, or use an elicitation request where the client supports it. Toolsets keep
the default list small (`core` plus `commands_search`, `commands_describe`, `commands_run`); `--read-only`
and `--no-destructive` restrict further. Courses and builder sessions are resources. The hosted (Workers
or API-hosted) variant with MCP OAuth comes later from the same registry (default, pending #75).

## Consequences

- Good: CLI and MCP share one implementation, tests and docs; no new hosting now.
- Bad: local only until the hosted variant; SDK v2 is new (v1 is the fallback).
