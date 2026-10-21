# 0172. Hosted MCP gateway on Cloudflare Workers sharing the CLI tool registry, OAuth at the gateway and scoped tokens upstream

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-7-agents-extensions.md` (5.7); follows ADR 0076

## Context and problem statement

`ulams mcp` (ADR 0076) is local. Remote agent clients need an MCP endpoint with MCP authorization
(OAuth 2.1, PKCE, client metadata). The TODO names a first version on Cloudflare Workers with the
Agents SDK and OAuth against the current REST API. A second tool implementation would drift.

## Considered options

1. A Worker (`front/mcp-worker`) using the Agents SDK and the Workers OAuth Provider, with the tool
   layer shared with `ulams mcp` through a `front/mcp-core` package.
2. MCP OAuth inside the Laravel API (Passport authorization server, MCP endpoint in PHP).
3. Self-hosters only: `ulams mcp --http` behind their own proxy.

## Decision

Option 1 for the hosted variant, option 3 stays for self-hosters. The Worker is the OAuth authorization
server for MCP clients; its authorize step sends the user to the tenant's `/agents/authorize` page,
which issues a scoped personal access token (`kind = agent`, 30 days) through a one-time code the
Worker exchanges server-to-server. The token is stored encrypted in the grant and never given to the
client. Rate limits, scopes, approvals and the agent audit log stay in the API.

## Consequences

- Good: one tool registry for CLI, local MCP and hosted MCP; nothing new in the self-hosted image.
- Good: revoking the token in "My tokens" disconnects the agent.
- Bad: the hosted gateway depends on a Cloudflare account and is deployed only when the owner opens the
  publishing gate.
- Bad: two OAuth layers (client ↔ Worker, Worker ↔ API) to test.
