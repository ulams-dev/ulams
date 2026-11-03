# 0130. Entitlements are the access source of truth, fed by verified Sylius order events

- Status: Accepted (2026-10-22, owner-approved defaults of the Phase 6.4 plan)
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

## Implementation notes (CM-2)

Decisions taken while completing the model; they refine the options above and change none of them.

- `course_user` is a pure projection. A decorator on `CourseAccessServiceContract` (`EntitlementCourseAccess`) records
  an entitlement for every access written through `CourseAccessService`: `admin` by default, `lti` and `compliance`
  inside `AccessSourceContext::as()`. `EntitlementService` itself is built on the undecorated service.
- An explicit removal (admin remove or set) revokes every entitlement of the user for the course (`admin_removed`):
  an admin who removes a learner expects the access to be gone. Refunds, cancellations and expiry stay narrower: they
  revoke or expire one source and never remove access another source still grants.
- Expiry closes the entitlement but keeps the `course_user` row with the last end date, so progress survives and a
  re-purchase restores access. Revocation without another source still removes the row (CM-1 behaviour).
- Shadow mode: the Wellms cart keeps attaching courses itself (its own events, no change in behaviour). A listener on
  `ProductBought` records a `wellms_order` entitlement without touching `course_user`; `ProductAttached` aligns its end
  date with what the cart wrote. The cart leads and the entitlements follow until the CM-10 cutover.
- The backfill is all-or-nothing and writes only `entitlements`; it never raises or lowers an end date. Parity compares
  `course_user` with the entitlements in force by the same date rule as `Course::hasUser()` and is stored in
  `entitlement_parity_reports` (kinds `check`, `backfill`, `dry_run`). Group access is not a `course_user` row and is not
  compared or recorded; `seat`, `group_legacy` and `voucher_legacy` are reserved source types that nothing writes yet.
