# 0141. Department academies as branded host aliases inside one tenant

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-remaining.md` (4.3); spec `docs/ROADMAP-PROMPT.md` 6.1.1

## Context and problem statement

The policy lifecycle asks for distinct branded department domains. Each tenant has its own database and one
`front_host`. Departments of one organisation share one policy, its versions, its owner dashboard and its
certificates.

## Considered options

1. One tenant; `tenant_host_aliases` (platform database: host, label, theme, default group) resolved like
   `front_host`.
2. One tenant per department, linked to a parent tenant.
3. One host; departments only as themes chosen after login.

## Decision

Option 1. An alias resolves to its tenant, applies its own theme and shows its department's catalogue first.
Access is still decided by enrolment, never by the host. Aliases are unique across the platform and validated like
`front_host`; DNS and TLS stay with the operator, and demos and tests use `.example.test` hosts.

## Consequences

- Good: one policy, one audit chain, one dashboard; no cross-database joins.
- Good: tenant resolution keeps one code path with one extra lookup.
- Bad: department data is not physically separated; departments that need that are separate tenants with
  separate policies.

## Alternatives considered

- Option 2 splits one policy over several databases and needs cross-tenant reporting.
- Option 3 does not give the department its own address, which the spec asks for.
