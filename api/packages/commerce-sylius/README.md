# commerce-sylius

The Sylius 2.3 adapter of the `commerce` package (ADR 0131, 0133; documentation: `developers/commerce-sylius`).

- `SyliusProvider` implements `CommerceProvider` and the capabilities `ReportsOrderEvents`, `ReconcilesOrders`,
  `InspectsCatalogue` and `ListsOrders` over the Sylius Admin API, bound to the tenant's channel (`commerce.channel`).
  Select it with `COMMERCE_PROVIDER=sylius`.
- `SyliusClient` signs in as the one admin API user (`COMMERCE_SYLIUS_ADMIN_EMAIL`, `COMMERCE_SYLIUS_ADMIN_PASSWORD`),
  caches the token, renews it once on a 401 and talks to the fixed `COMMERCE_SYLIUS_URL`. Credentials and tokens never
  appear in an error message.
- Product codes are `ul-<channel>-<type>-<id>` with a variant `<code>-v`; products are disabled, never deleted.

Tests: `tests/Feature` runs against an in-memory Sylius (`tests/Support/FakeSylius.php`, shapes recorded in
`tests/fixtures`). `tests/Live` needs a real Sylius (compose profile `commerce`):

```bash
COMMERCE_SYLIUS_LIVE_URL=http://sylius:8080 COMMERCE_SYLIUS_LIVE_ADMIN_EMAIL=... COMMERCE_SYLIUS_LIVE_ADMIN_PASSWORD=... \
  ./vendor/bin/phpunit --testsuite commerce-sylius
```

Each live run creates a channel of its own and only ever adds data: use a throwaway database.
