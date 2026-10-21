# 0130. Entitlements are the access source of truth, fed by verified Sylius order events

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-commerce.md` (sections 5, 6, 7.1)
- Amends: [0049](0049-commerce-provider-before-sylius.md) (interface v2)

## Context and problem statement

The spec makes the LMS own entitlements and forbids granting access from a frontend redirect. Today access is a
`course_user` row with `end_date`, written by the Wellms cart, admins, compliance, LTI and the demo, with no record
of what granted it. Refunds never revoke access. Sylius has no outgoing webhooks. We need a model that can grant
and revoke from orders without removing access someone else granted, and a delivery contract that survives lost,
duplicated, delayed and forged events.

## Considered options

1. `entitlements` table (one row per source, unique `source_key`) as the source of truth; `course_user` stays as a
   projection maintained only through `CourseAccessService`; Sylius events via a transactional outbox, signed,
   applied idempotently after re-reading the order from the Sylius Admin API; reconciliation from an event feed and a
   nightly sweep.
2. Keep `course_user` as the only store and add an `order_id` column.
3. Let the access check call Sylius (no local entitlements).

## Decision

Option 1.

- Every grant has a `source_key` (`sylius:<channel>:order_item:<id>`, `admin:…`, `migration:…`); revoking one
  source recomputes `course_user` and removes it only when no active entitlement remains.
- Webhooks: `Ulams-Signature` HMAC (ADR 0103 scheme), 300 s tolerance, per-tenant secret, channel binding, unique
  event id, per-order sequence. The job re-fetches the order and acts on Sylius's current state, never the payload.
- Reconciliation: feed every 5 minutes, full sweep nightly; both repair grants and revocations.
- Interface v2: core `CommerceProvider` adds `fetchOrder()`; optional `ReconcilesOrders`, `ProvisionsChannels`,
  `ManagesCommerce` capabilities keep the core small for a future Stripe-only provider.
- Existing access is backfilled with a parity check that must show zero differences before any cutover.

## Consequences

- Good: refunds can revoke safely; existing policies, H5P and SCORM checks are unchanged (they still read
  `course_user`); a leaked secret cannot create access Sylius did not record.
- Good: access survives LMS or network outages; reconciliation is the safety net.
- Bad: a second store to keep in step with `course_user`; mitigated by the `CourseAccessService` decorator and a
  nightly parity check.
- Bad: each event costs one Admin API call.
