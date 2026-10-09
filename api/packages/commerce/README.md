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

The Course Builder uses it to create an inactive product when a paid course is applied and to
activate it when the course is published.

## Installing

- Registered in `config/app.php`; `php artisan migrate` creates `commerce_product_links`.
- Settings: `COMMERCE_PROVIDER` (default `wellms`), `COMMERCE_FRONT_URL` (default `FRONTEND_URL`).
- The cart sells in the currency set by `PAYMENTS_DEFAULT_CURRENCY`; a price in another currency is
  refused.

## Endpoints

None. The package is a library for other packages.
