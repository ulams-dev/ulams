# 0131. One Sylius 2.3 service with a channel per tenant and a ulams bridge bundle

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-commerce.md` (sections 4, 8, 14)

## Context and problem statement

The spec picks Sylius 2.x as a separate headless service and an optional deployment profile sharing the PostgreSQL
server. We must decide where its code lives, how many instances run, how tenants map to it, and how the missing
pieces (outgoing events, external identity, LMS links) are added without forking Sylius.

## Considered options

1. One Sylius-Standard 2.3 project in `api/sylius` with a first-party `UlamsBridgeBundle`; one instance, one
   channel per tenant; database `sylius` on the shared PostgreSQL server; image `ulams-commerce` (web + worker).
2. A Sylius instance per tenant.
3. A separate repository for the commerce service.

## Decision

Option 1.

- `api/sylius` follows the `api/h5p` precedent (separate service inside `api/`, keeps the three-folder layout).
- The bridge bundle holds: transactional outbox and Messenger delivery, the admin event feed, channel links (webhook
  URL, secret, JWKS URL), the shop JWT authenticator (ADR 0132), the EU VAT seed command, and the `ulams_user_ref`
  customer field.
- Plugins: Stripe (`flux-se/sylius-stripe-plugin`), Refund and Invoicing plugins (telemetry disabled or patched,
  PDFs through our `pdf` service), Mollie for P24 later, EU VAT plugin if it supports 2.3.
- Tenant isolation is enforced by the LMS (channel injected and checked on every call); tenant admins never get
  Sylius credentials; the native admin is staff-only.
- Sylius Plus is not used.

## Consequences

- Good: one extra image and one database regardless of tenant count; upgrades follow Sylius-Standard.
- Good: the LMS keeps a Sylius URL per tenant env, so a dedicated instance for a large tenant stays possible.
- Bad: Sylius OSS has no per-channel admin permissions, so the native admin cannot be given to tenants.
- Bad: PostgreSQL is less exercised by Sylius than MySQL; verified in milestone CM-1 with a MySQL fallback.
