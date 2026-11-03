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

## Implementation notes (CM-4)

- **Channel at provisioning.** `TenantProvisioner` has a step `commerce_channel` after `permissions`, through a contract in
  the tenancy package (`CommerceChannelProvisionerContract`); `commerce-sylius` implements it. The channel code is the
  tenant slug, the secret (`whsec_` and 32 random bytes) is made once, stored encrypted on the tenant row and written to
  the tenant env as `COMMERCE_CHANNEL` and `COMMERCE_WEBHOOK_SECRET`. Without `COMMERCE_PROVIDER=sylius` the step is
  skipped and not recorded; the upgrade step and `ulams:tenant:commerce-channel` create the channel later, for every
  tenant that has none. Deleting a tenant disables the channel (best effort) and keeps orders and products.
- **Defaults.** Base currency PLN for Polish-language platforms and EUR otherwise (plan Q2), overridable once per tenant
  with `COMMERCE_CURRENCY` and `COMMERCE_LOCALE` (`ulams:tenant:set-env`); the base currency of an existing channel is never
  touched and a different one is reported (409).
- **EU VAT seed through the Admin API, not a bridge command.** The plan put `ulams:commerce:seed-eu-vat` in the bridge
  bundle. It is an LMS command instead (`resources/eu-vat-rates.json`, 27 countries, zones `eu-<cc>`, rates `vat-<cc>`
  included in the price, tax category `e-service`): no Doctrine code in two places, and it is tested against the real
  API. The rates are seed data marked unconfirmed until an accountant signs them off. A live checkout shows 23 % of a
  gross price for a Polish and 19 % for a German billing address.
- **Payment methods** are shared by all channels; each tenant channel is added to them. `offline` is for development
  and tests (`COMMERCE_SYLIUS_OFFLINE`), `stripe` exists when the Stripe keys are in the platform env. Stripe payments
  were not run: see `docs/reports/commerce-p24-spike.md`.
- **No telemetry.** Core telemetry is switched off in configuration and a compiler pass removes any `sylius.telemetry.*`
  service the Stripe or Refund plugin would load; in the 2.3.0 container none is loaded (plan Q12, risk R6).
- Not done: rotating a tenant's webhook secret (`ulams:commerce:rotate-secret`, plan 5.6) and the accepted-currency
  switch of a live channel.
