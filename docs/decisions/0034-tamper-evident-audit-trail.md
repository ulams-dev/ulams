# 0034. A tamper-evident audit trail for Living Course

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

Compliance customers need to show who accepted which AI change, when, and based on which source
revision. The Phase 2 version table records decisions per blueprint version, but not per proposal
item, not the source revision, not connector and webhook events, and it is an ordinary mutable table.
Phase 6.1 (compliance reports) and Phase 7.5 (agent audit) will build on the same record.

## Decision

- An append-only table `living_course_audit` with actor (`user`, `system`, `agent`, plus
  `on_behalf_of`), action, subject, source and revision (with the Git SHA, ETag or file hash),
  blueprint versions before and after, AI call ids, structured data (reason, fingerprints, counts),
  IP and user agent.
- Rows are hash-chained per tenant database (`hash = sha256(prev_hash ‖ canonical row)`), written
  only by `AuditLog::record()` inside the transaction of the change, serialised by a one-row head
  table lock. A PostgreSQL trigger rejects UPDATE and DELETE.
- A verify command and endpoint recompute the chain; exports in CSV and JSON per course and per
  tenant.
- Secrets and source text are never written to the audit; it references revisions and versions,
  which keep the content.

## Consequences

- Good: tampering by anyone without database superuser rights is detectable; the export answers
  audit questions without joining application tables.
- Good: the same table serves agent actions later (`actor_type = agent`).
- Bad: writes are serialised per tenant (acceptable: decisions are rare events).
- Bad: a superuser can still rewrite the whole chain; anchoring the head hash externally (e.g. in the
  platform database or a signed export) is left for Phase 6.1 if customers require it.
