# 0072. The agent-first `ulams` CLI: one command registry generates the parser, help, `describe`, MCP tools and docs

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md`

## Context and problem statement

The product owner wants a CLI that agents can use for almost everything the admin, studio and learner
UIs do, "like Cloudflare wrangler", plus an MCP server. Principle 6 (developer-first) and 7 (agent-ready)
require it. The API has about 550 routes in 61 packages; the OpenAPI spec documents 407 operations with
hash operationIds and mostly untyped responses. A hand-written command per endpoint would drift from the
API, and a separate MCP tool list would drift from the CLI.

## Considered options

1. One TypeScript registry of commands (zod input and output schemas, kind, scopes, endpoints, examples)
   that generates the CLI parser, help, `ulams schema`/`describe`, MCP tools, docs pages and the
   coverage matrix; noun commands generated from OpenAPI by method + path with a curated overrides file.
2. A CLI framework (oclif, commander) with hand-written commands, and a separate MCP server.
3. Only a raw `ulams api` command over the OpenAPI spec.

## Decision

Option 1, in a new workspace `front/cli` (package and bin `ulams`), built on `@ulams/sdk` and bundled with
tsup. Parsing uses `node:util.parseArgs` and our own help renderer, so errors, exit codes and JSON output
stay under our control. `ulams api` exists as the escape hatch over the whole spec, but does not count as
coverage. Command code uses only fetch-level platform APIs; file system and TTY access go through a
context object, so the same registry runs in the MCP server and, later, in a hosted runtime.

## Consequences

- Good: CLI, MCP and docs cannot drift; agents discover everything through `ulams schema`.
- Good: new API endpoints become commands by regenerating; CI fails when an operation is neither
  covered nor excluded.
- Bad: path-derived names need an overrides file and review; untyped responses limit typed output until
  the OpenAPI response schemas are filled (L0-11 and S5 in the plan).
