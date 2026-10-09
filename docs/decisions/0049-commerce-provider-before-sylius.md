# 0049. A CommerceProvider interface before Sylius, with a Wellms cart adapter

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L2-07)

## Context and problem statement

The builder interview asks free or paid. The spec says pricing goes through the active
`CommerceProvider` (6.4), and "until then the existing `payments` package". Sylius is not built yet.

## Considered options

1. Add the small 6.4 interface now (`syncProduct`, `createCheckout`, `handleOrderEvent`), with a
   `WellmsCartProvider` adapter.
2. Call the cart package directly from the builder.
3. Keep builder courses free until 6.4.

## Decision

Option 1, in a new `commerce` package:

- **Adapter.** `WellmsCartProvider` creates or updates a cart `Product` with the course as the
  productable, through the cart services.
- **Mapping.** The adapter keeps the mapping in `commerce_product_links`.
- **Order events.** `handleOrderEvent` returns null; access still comes from the cart package's
  existing flow.
- **Selection.** The provider is chosen by `commerce.provider`. The Sylius adapter replaces it in 6.4.

## Consequences

- Good: the builder can price courses now, and only the adapter changes later.
- Good: entitlements stay in the LMS.
- Bad: the interface may grow when Sylius lands; it is versioned here.
- Default pending #54.

## Implementation notes (L2-07)

- `Price` carries minor units and an ISO currency; the Wellms cart sells in one currency
  (`PAYMENTS_DEFAULT_CURRENCY`) and refuses any other, so the builder skips the product with a note
  instead of failing the apply.
- A product is only created for a paid course with a confirmed amount. The model's suggested price
  (task `price`, light) lives in the session state, never in the brief, until the author confirms it.
- The applier creates the product inactive (`purchasable = false`); publishing the course activates it.
  An existing single product for the course (made in the admin) is adopted, not duplicated.
- `commerce.sellables.course` can name the productable class; otherwise the first registered
  productable that is a `Ulams\Courses\Models\Course` is used.
