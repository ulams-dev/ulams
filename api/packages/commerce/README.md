# Commerce

One interface between the LMS and whatever sells courses (ADR 0049). The LMS owns entitlements: a
provider mirrors a sellable as a product, starts a checkout and reports verified order events; it
never decides who has access.

## What it does

- `Ulams\Commerce\Contracts\CommerceProvider`: `key()`, `syncProduct(SellableRef, Price, active)`,
  `createCheckout(User, ProductRef, returnUrl)`, `handleOrderEvent(Request)`.
- `WellmsCartProvider` (default): creates or updates a single-productable cart `Product` through
  `ProductServiceContract`, keeps the mapping in `commerce_product_links`, sends buyers to the cart,
  and returns no order events (the cart and payments packages grant access themselves).
- `CommerceManager` resolves the provider named by `commerce.provider` (`COMMERCE_PROVIDER`);
  another adapter registers with `CommerceManager::extend($key, fn)`.

- `POST /api/commerce/webhooks/{provider}`: signed, idempotent order events of a provider that reports
  orders by webhook (HMAC + timestamp + event id + tenant channel). `ApplyOrderEvent` re-reads the order
  through the optional `FetchesOrders` capability and grants or revokes entitlements
  (`Ulams\Entitlements`, ADR 0130). `ulams:commerce:reconcile` reads the optional `ReconcilesOrders` feed to
  repair missed webhooks. Prototype (CM-1): the only provider with these capabilities is the test-only fake.

- Shadow mode (CM-2, ADR 0130): while the Wellms cart sells, `RecordWellmsPurchase` records each purchase as a
  `wellms_order` entitlement (on `ProductBought`, without touching `course_user`; on `ProductAttached` it aligns the end
  date the cart wrote). `ulams:entitlements:backfill [--dry-run]` gives every existing `course_user` row an entitlement
  and proves, in the same transaction, that no access changed (all or nothing); the parity check is
  `ulams:entitlements:parity` in the `entitlements` package. `COMMERCE_SHADOW_WELLMS=false` turns the listener off.

The Course Builder uses it to create an inactive product when a paid course is applied and to
activate it when the course is published.

## Installing

- Registered in `config/app.php`; `php artisan migrate` creates `commerce_product_links`.
- Settings: `COMMERCE_PROVIDER` (default `wellms`), `COMMERCE_FRONT_URL` (default `FRONTEND_URL`),
  `COMMERCE_CHANNEL`, `COMMERCE_WEBHOOK_SECRET`, `COMMERCE_WEBHOOK_SECRET_PREVIOUS`, `COMMERCE_RECONCILE`
  (see the developer guide "Entitlements and order events").
- The cart sells in the currency set by `PAYMENTS_DEFAULT_CURRENCY`; a price in another currency is
  refused.

## Endpoints

One public endpoint, `POST /api/commerce/webhooks/{provider}` (no session or token; the signature is the
credential). Otherwise the package is a library for other packages.
