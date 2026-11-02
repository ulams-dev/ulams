# Agent Safety for the ULAMS MCP Server

## Scoped Tokens
**Rule:** All tokens used by agents must be scoped to prevent unauthorized access to resources.
- **Good Example:** An agent token is scoped to only access the `ulams mcp` commands.
- **Bad Example:** A token with full API access is used by an agent, exposing the entire system to potential abuse.

## Dry-run First
**Rule:** All destructive actions must be performed with a dry-run option first to ensure no unintended changes are made.
- **Good Example:** An agent runs `ulams mcp delete --dry-run` to preview the deletion of a resource before executing the actual command.
- **Bad Example:** An agent deletes a resource without a dry-run, leading to accidental data loss.

## Idempotency Keys
**Rule:** All commands that modify resources must include an idempotency key to ensure that repeated executions have the same effect.
- **Good Example:** An agent includes an idempotency key in the `ulams mcp update` command to safely retry the operation.
- **Bad Example:** An agent retries a command without an idempotency key, causing duplicate changes to the resource.

## Human Approval for Destructive Actions
**Rule:** All destructive actions must be approved by a human before execution.
- **Good Example:** An agent submits a request to delete a resource, which is then reviewed and approved by a human before the command is executed.
- **Bad Example:** An agent executes a destructive command without human approval, leading to unauthorized changes.

## Rate Limits
**Rule:** All commands must be subject to rate limits to prevent abuse and ensure fair usage.
- **Good Example:** An agent's commands are rate-limited to 10 requests per minute, preventing excessive load on the server.
- **Bad Example:** An agent sends a high volume of requests without rate limiting, causing server overload.

## Audit Trail
**Rule:** All actions performed by agents must be logged in an audit trail for accountability and monitoring.
- **Good Example:** An agent's actions are logged in the audit trail, allowing for easy tracking and review.
- **Bad Example:** An agent's actions are not logged, making it difficult to track and monitor activity.
