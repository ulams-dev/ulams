# 0099. Describe ULAMS as open source without paid product tiers

- Status: Proposed
- Date: 2026-10-12

## Context and problem statement

Earlier roadmap and landing copy described an open-core model with paid cloud and
enterprise features. The product owner explicitly rejected that model and requested
its removal, stating that these offerings do not exist and are not planned.

## Decision

- Describe ULAMS as free, self-hostable open-source software.
- Remove open-core wording and paid cloud/enterprise promises from public Overview,
  Comparison, translated copy and product documentation.
- Track product capabilities in the open-source roadmap rather than paid tiers.
- Preserve the earlier decision as superseded history in the TODO. This decision
  supersedes the commercial-model premise in ADR 0003; licensing and service-boundary
  rules remain documented in LICENSING.md.

## Consequences

The public site no longer promises paid products. Existing package licence notices
continue to describe the licences of their respective code.
