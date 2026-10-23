# 0133. The LMS owns prices and intent; the Sylius adapter mirrors them and reads orders back

- Status: Proposed
- Date: 2026-10-23
- Plan: `docs/plans/phase-6-commerce.md` (sections 5, 7, CM-3)
- Refines: [0130](0130-entitlements-are-the-access-source-of-truth.md), [0131](0131-sylius-service-and-bridge-bundle.md)

## Context and problem statement

CM-3 puts a real Sylius behind the `CommerceProvider` seam. Three questions the plan left open: where a price lives
when the provider can be switched, what a "bundle" and an "access pass" are in the LMS (it has neither), and how the
bridge reports orders without a message broker.

## Considered options

1. Prices live on the provider's product (as the Wellms cart does); the LMS reads them back when needed.
2. The LMS keeps the price and the "for sale" intent (`commerce_sellable_prices`); a provider only mirrors a product,
   enabled while the sellable is for sale and published.
3. Bundles and passes as Sylius-only constructs (a product with several variants).

For events: (a) Symfony Messenger with an async transport, (b) a Doctrine outbox table delivered by a small
polling command.

## Decision

Option 2, with LMS-side `Bundle` (courses sold together) and `AccessPass` (a course or bundle for N days) models, and
(b) for events.

- **Intent in the LMS.** Switching providers (CM-10) fills the new catalogue from `commerce_sellable_prices`; no price
  is ever read back from a provider. Existing prices on the builder's product links are copied by the migration.
- **Enabled = for sale and published.** Unpublishing a course disables its product and every bundle and pass that
  contains it; nothing is deleted because orders reference the variant. The drift check compares the product with
  that rule nightly.
- **Bundles and passes expand in the LMS** (one entitlement per course, source key `...:course:<id>`; a pass sets
  `valid_until`), so Sylius sees one ordinary digital product per sellable and the entitlement rules stay in one place.
- **Interface v2** as in 0130, with one change: `fetchOrder()` is core, while `ReportsOrderEvents` (accepts webhooks),
  `ReconcilesOrders`, `InspectsCatalogue` and `ListsOrders` are optional capabilities. One contract suite
  (`ProviderContract`) runs against the Wellms adapter and a real Sylius.
- **Outbox without Messenger.** The bridge writes an outbox row in the transition's transaction and a polling command
  (`ulams:outbox:deliver --watch`, the `sylius-worker` service) delivers it with exponential backoff for 72 hours. The
  pull feed reads the same table, so it never depends on delivery. Messenger would add a transport to operate for no
  gain at this volume; the table keeps the "event exists iff the transition committed" guarantee.
- **Customer link.** `ulamsUserRef = <channel>:<LMS user id>` on the Sylius customer, written by the LMS. The channel
  prefix is checked on read, so a customer linked in another tenant's channel is unlinked here (ADR 0132's rule against
  linking by e-mail address holds from CM-3, not from CM-5).

## Consequences

- Good: a provider outage never loses a price; the sweep and the feed repair what a failed push or a lost webhook missed.
- Good: tenant isolation sits in two places the LMS controls (the channel on every call, the channel in the link).
- Bad: a second place (the LMS) to keep the Sylius catalogue in step with; mitigated by the nightly check.
- Bad: unit refunds (RefundPlugin) are not read yet; a full refund or cancellation revokes, a goodwill amount keeps access.
- Bad: the paid-at time is not in the order snapshot, so a pass granted by the sweep ends N days after the sweep, not
  after the purchase.

## Implementation notes (CM-3)

- Sylius 2.3.0 on PostgreSQL 17 passed the whole contract and flow suites (risk R1 for the checkout and API paths).
  `doctrine:schema:validate` reports only comment and index-name differences (messenger table, Mollie, Refund columns);
  nine Sylius migrations are MySQL-only and skip on PostgreSQL.
- Updating a product translation or a channel price through the Admin API must name the sub-resource (`@id`); otherwise
  Sylius reads a second translation or price for the same locale or channel and answers 422.
- Adyen and PayPal are not installed; Stripe, Mollie and the Refund plugin are (CM-4 configures them).
