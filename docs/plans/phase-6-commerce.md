# Phase 6.4: Sylius commerce and extended enterprise

Status: Proposed. The owner pre-approved the 2026-10-21 batch of plans ("All approved"), so every open question
in section 16 has a recommended default that is used unless the owner says otherwise. Inputs that only the
owner can provide (accounts, credentials, money, legal facts, hosting) are listed separately in section 17.

ADRs proposed with this plan: [0130](../decisions/0130-entitlements-are-the-access-source-of-truth.md)
(entitlements and the order-event contract), [0131](../decisions/0131-sylius-service-and-bridge-bundle.md)
(the Sylius service and its bridge bundle), [0132](../decisions/0132-commerce-identity-lms-issued-shop-tokens.md)
(single login: LMS-issued shop tokens).

## 1. Goal

A tenant can sell a course, a bundle, a time-limited access pass or a B2B seat package. The learner buys it in the
tenant's own frontend, pays through Sylius, and gets access only after Sylius reports a verified payment to the LMS.
A refund removes the access. A lost webhook is repaired by a reconciliation job within minutes. Nobody who has
access through the Wellms cart today loses it during the move.

## 2. Scope: TODO items covered

Phase 6.4 "Commerce (Sylius) and extended enterprise" (`docs/ROADMAP-TODO.md`):

| # | TODO item (quoted) | Milestone |
|---|---|---|
| T1 | "`CommerceProvider` interface (sync product, create checkout, handle order events) (partial: interface and the Wellms cart adapter shipped in `api/packages/commerce`, ADR 0049; the Sylius adapter is pending)" | CM-3 |
| T2 | "Sylius adapter as default implementation" | CM-3, default switch in CM-10 |
| T3 | "Entitlements model in LMS (access, validity, source order or seat package)" | CM-1 (core), CM-2 |
| T4 | "Catalogue sync: course/bundle/subscription/seat package → digital Sylius product" | CM-3, seats in CM-9 |
| T5 | "Order → entitlement via signed, idempotent webhooks + reconciliation job; never grant access from frontend redirect" | CM-1, hardened in CM-3 |
| T6 | "Single login: LMS as identity source; Sylius customer linked on first checkout" | CM-5 |
| T7 | "Tenant ↔ Sylius channel (catalogue, prices, currency, locale, tax zone), created at provisioning" | CM-4 |
| T8 | "Unified admin: prices, coupons, orders, refunds in LMS admin; native Sylius admin for advanced settings" | CM-8 |
| T9 | "B2B seat packages (Sylius product + LMS seat pool), invoices from Sylius" | CM-9 |
| T10 | "Migration of orders, vouchers and access from Wellms packages with zero lost access" | CM-2 (access), CM-10 (cutover) |
| T11 | "EU VAT (OSS) for digital services: confirm Sylius handling, document options" | Section 11 (confirmed here), CM-4 (setup), CM-9 (B2B reverse charge) |
| T12 | "Per-tenant branding, catalogue, sales pages" | CM-6 |
| T13 | "Partner/customer portals with scoped admins and reports" | CM-11 |

Also covered:

- Phase 5: "Single frontend for LMS **and** Sylius commerce (catalogue, checkout, account)" (CM-5, CM-6).
- Open decision: "Prototype the Sylius order → entitlement flow early (highest-risk commerce piece)" (CM-1).
- Quality bar: "Commerce E2E: buy → webhook → access granted; refund → access revoked; missed webhook repaired by
  reconciliation" (CM-1 as a script, CM-7 in CI).
- Phase 8 (touched, not finished): "Commerce as optional profile: + one Sylius image, shared PostgreSQL server
  (separate DB)" (compose profile in CM-1, launcher and Helm in CM-12).

Out of scope: recurring billing (section 16, Q5), Polish KSeF e-invoicing (Q11), the 7.5 item "Delegate commerce
actions to Sylius MCP tool" (needs CM-8 first; the Sylius admin MCP plugin is pre-1.0), the RevenueCat in-app path
(kept on the Wellms flow, routed into the entitlement service in CM-10).

## 3. Current state (what exists, extend it)

**LMS commerce seam** (`api/packages/commerce`, ADR 0049, Proposed):

- `Ulams\Commerce\Contracts\CommerceProvider`: `key()`, `syncProduct(SellableRef, Price, bool $active): ProductRef`,
  `createCheckout(User, ProductRef, string $returnUrl): CheckoutRef`, `handleOrderEvent(Request): ?OrderEvent`.
- Value objects in `src/Support/`: `Price` (minor units, ISO currency), `SellableRef` (`course`, `bundle`),
  `ProductRef`, `CheckoutRef`, `OrderEvent` (`paid`, `refunded`, `cancelled`, `idempotencyKey`).
- `CommerceManager::extend()` and `provider()`, selected by `commerce.provider` (`COMMERCE_PROVIDER`, default `wellms`).
- `WellmsCartProvider` writes a cart `Product` and a `commerce_product_links` row (unique on sellable type, id,
  provider). `handleOrderEvent` returns null; access comes from the cart.
- Only caller: `course-builder/src/Apply/CourseCommerce.php` (`apply()` from `BlueprintApplier.php:332`,
  `activate()` from `publish()` at line 442).

**Wellms commerce** (`cart`, `payments`, `vouchers`, `invoices`, audit in `docs/reports/phase-0-audit.md` section 10):

- Grant path: `PaymentSuccess` → `Ulams\Cart\Listeners\PaymentSuccessListener` → `OrderService::setPaid` →
  `ProductBought` → `ProductService::attachProductToUser` (`products_users` with `end_date`) → `Course::attachToUser`
  (`api/app/Models/Course.php`, `course_user` with `end_date`, fires `CourseAssigned`, `CourseAccessStarted`).
- Refunds never revoke access. No transactions or locks. Errors in `attachToUser` are only logged.
- Stripe webhook verification exists (`StripeDriver::isValidSignature`, HMAC over `t.payload`, 300 s tolerance).
- Admin: `admin/config/routes.ts` `/sales/{orders,payments,vouchers,products}`, pages in `admin/src/pages/{Orders,
  Payments,Vouchers,Products}`, permissions `cart_order_list`, `products_*`, `payment_*`, voucher permissions.

**Access today:**

- `course_user` (`finished`, `deadline`, `end_date`) and `course_group` with group tree inheritance;
  `Course::hasUser()` and `CoursesPolicy::attend` gate lessons and topics. `public` courses are open.
- The domain service for grants is `Ulams\CourseAccess\Services\CourseAccessService` (`addAccessForUsers`,
  `removeAccessForUsers`, ...). Compliance (`AssignmentService`), LTI and demo already use it.
- There is no entitlements table and no link from a `course_user` row to whatever granted it.

**Tenancy:** database per tenant (ADR 0007). `tenancy/src/Services/TenantProvisioner.php` has resumable `STEPS`
(`database`, `bucket`, `env`, `migrate`, `passport_keys`, `passport_client`, `permissions`, `lti_keys`, `demo`).
Platform API `api/platform/tenants` (ADR 0085). Scheduler with a per-tenant minute lock (ADR 0068).

**Reusable patterns:** `webhooks` package signs with `Ulams-Signature: t=<unix>,v1=<hex>`
(`WebhookSignature::sign/verify`); the best inbound pattern is `living-course` `WebhookController::receive`
(unique delivery id, `insertOrIgnore`, duplicate answers 200). `core` has an `Idempotency` middleware and
`SafeHttp`. Agent and audit logs: `agent_audit_log`, `living_course_audit` (hash chain).

**Auth:** Passport 13 with the OAuth routes commented out (no OIDC provider), spatie permissions, roles `student`,
`tutor`, `admin`, scoped PATs (ADR 0074) with a `commerce` area mapped to `api/admin/{orders,products,...}`.

**Frontends:** `front/web` (Astro SSR, ADR 0008) keeps the session in an httpOnly cookie and proxies browser calls
through `src/pages/bff/[...path].ts` with an allow-list (`src/lib/bff.ts`). The course page shows
`PurchaseCard.astro` with a demo CTA ("Payments run through the shop service in production"). No cart or checkout.
`front/sdk` has `products.list` only. The legacy Wellms React app (`front/src`) still has the Stripe/P24 cart.

**Sylius 2.x facts (checked 2026-10-21 against the source at tag 2.3, Packagist, endoflife.date; docs.sylius.com
was behind a login):**

| Fact | Consequence |
|---|---|
| 2.3.1 is current (14 Oct 2026); 2.2 has security fixes to 31 Mar 2027; PHP ^8.3, Symfony 6.4/7.4/8.0, API Platform 4.3/5, MIT | Target 2.3.x |
| Admin API (`/api/v2/admin`) has full CRUD for channels, products, variants (channel pricing inside the variant), promotions and coupons (with `coupons/generate`), customers, tax categories/rates, zones; orders are read, cancel and resend only; payments have `complete` and `refund` | Catalogue sync and channel provisioning are possible through the API; orders can only be created through the shop flow |
| Shop API (`/api/v2/shop`): cart → items → address → select payment → `PATCH complete`; JWT via LexikJWT (`/shop/customers/token`, `/admin/administrators/token`) | Checkout runs through the BFF |
| Variant `shippingRequired=false`; channel `skippingShippingStepAllowed` | Digital products without a shipping step |
| Symfony Workflow is the default state machine; core listens to `workflow.sylius_order_payment.completed.pay`, `workflow.sylius_payment.completed.complete` and `.refund` | Our outbox hooks onto these |
| No built-in outgoing webhooks (one community outbox plugin, released 2026-10-11, ~1 install) | We write our own outbox in a bridge bundle |
| Tax zone from the **billing address** by default, channel `defaultTaxZone` otherwise; strategies `order_items_based`, `order_item_units_based`; nothing OSS-specific | OSS is configuration (zones and rates per EU country) |
| `flux-se/sylius-eu-vat-plugin` (VIES, reverse charge) supports 2.0–2.2, 2.3 unconfirmed | Verify in CM-1, fallback in Q3 |
| Sylius-Standard 2.3 ships Stripe (`flux-se/sylius-stripe-plugin` 2.1, MIT, Payment Requests), Mollie, Adyen, PayPal, RefundPlugin | Stripe first; P24 through Mollie or a port |
| BitBag Przelewy24 plugin: Sylius 1.12/1.13 only | P24 is a gap (Q4) |
| RefundPlugin 2.2.1 and InvoicingPlugin 2.3.0 (MIT) "enforce telemetry collection" per their READMEs; PDFs via knp-snappy (wkhtmltopdf) | Conflicts with "no telemetry by default"; see risks R6, R7 |
| PostgreSQL 13.9+ listed in the (2024) requirements page; Sylius-Standard compose uses MySQL 8.4 | Prove PostgreSQL 17 in CM-1 (risk R1) |
| No SSO/OIDC for shop users | Custom authenticator in the bridge (ADR 0132) |
| Admin MCP plugin v0.1.2 targets 2.1–2.2; shop MCP plugin v0.3.0 is guest-checkout only | 7.5 delegation later |
| Paid Sylius Plus: B2B Suite, advanced permissions, RMA, multi-store | We do not need Plus (Q8) |

## 4. Architecture

### 4.1 Services and ownership

```
 Browser ──► front/web (Astro SSR, per-tenant host)
               │  /bff/*        (LMS API, allow-listed, session cookie)
               │  /bff/shop/*   (Sylius Shop API, allow-listed, server-side shop token)  [CM-5]
               ▼                                   ▼
  LMS API (Laravel, tenant DB)  ◄──── signed order events ────  Sylius (Symfony, one `sylius` DB)
   - courses, users, progress        POST /api/commerce/webhooks/sylius     - catalogue, channel per tenant
   - entitlements, seat pools   ────► Admin API (sync, verify, reconcile)    - cart, checkout, promotions
   - commerce_* projections          JWKS for shop tokens  ◄──── fetch     - taxes, payments, refunds, invoices
   - admin screens via API                                                 - ulams bridge bundle (outbox,
                                                                              authenticator, channel links)
  admin/ (Umi) ──► LMS API only (never Sylius directly)
  Platform staff ──► native Sylius admin (advanced settings)
```

Rules that follow from the non-negotiables:

- The LMS owns entitlements. Sylius never writes to the LMS and never decides access; it only reports events, and the
  LMS re-reads the order from the Sylius Admin API before it grants anything (section 5.3).
- The frontend return page after payment only shows status. It never triggers a grant.
- Tenant admins never hold Sylius credentials. The LMS API holds one Sylius API user per instance and enforces the
  tenant's channel on every call.
- LMS entities change only through domain services: `EntitlementService` → `CourseAccessService`.

### 4.2 Code layout

| Path | What | New? |
|---|---|---|
| `api/packages/commerce` | Interface v2, value objects, `CommerceManager`, Wellms adapter, webhook route, order projection, reconciliation command | extended |
| `api/packages/entitlements` (`Ulams\Entitlements`) | `entitlements`, `seat_pools`, `seat_assignments`, `EntitlementService`, `SeatPoolService`, backfill command | new package |
| `api/packages/commerce-sylius` (`Ulams\CommerceSylius`) | `SyliusProvider`, Admin API client (over `SafeHttp`-style client with an allow-listed base URL), channel provisioning, catalogue mapper, shop token issuer | new package |
| `api/sylius/` | Sylius-Standard 2.3 project: `composer.json`, `config/`, `Dockerfile`, `src/` minimal | new (ADR 0131) |
| `api/sylius/bundles/UlamsBridgeBundle` | Outbox entity and Messenger handler, event feed endpoint, channel link entity and endpoints, JWT authenticator, EU VAT seed command | new |
| `front/sdk/src/commerce.ts` | Shop client (cart, checkout, orders) for `/bff/shop` and LMS commerce endpoints | new |
| `front/web/src/pages/{shop,checkout,account/orders}` | Catalogue, cart, checkout, return, orders | new |
| `admin/src/pages/Commerce/*` | Prices, coupons, orders, refunds, invoices, health | new, replaces `/sales/*` after CM-10 |
| `api/docker-compose.yml`, `front/cli/src/stack/*` | `commerce` profile | extended |

`api/sylius/` follows the `api/h5p` precedent (a separate service with its own toolchain inside `api/`), which keeps
the three-folder layout.

### 4.3 Data flow: purchase

```
1 learner (logged in) ─ "Buy" ─► front/web /checkout/start?sellable=course:12
2 BFF ─► LMS POST /api/commerce/shop-session            → short-lived shop JWT (aud=sylius, channel=<tenant>)
3 BFF ─► Sylius POST /api/v2/shop/orders (Bearer JWT)    → bridge authenticator links/creates Customer
       ─► add item (variant code from LMS catalogue), PUT billing address, select payment, PATCH complete
4 Sylius ─► Stripe Checkout (or offline in CM-1) ─► payment completed
5 Sylius workflow `sylius_order_payment.pay` ─► bridge writes outbox row (same DB transaction)
6 Messenger worker ─► POST https://<tenant-api>/api/commerce/webhooks/sylius   (signed)
7 LMS: verify signature + timestamp + channel → insertOrIgnore event → queue ApplyOrderEvent
8 ApplyOrderEvent: lock order projection → GET order from Sylius Admin API → payment_state=paid?
       → EntitlementService::grant(source_key = sylius:<channel>:order_item:<id>) → CourseAccessService
9 front/web /checkout/return polls LMS GET /api/commerce/orders/{token}/status until "granted" (or shows "processing")
```

## 5. Order → entitlement in detail (ADR 0130)

### 5.1 Events Sylius emits (bridge bundle)

| Sylius trigger | Event type | Payload essentials |
|---|---|---|
| `workflow.sylius_order_payment.completed.pay` | `order.paid` | order id, number, token, channel code, customer `ulams_user_ref`, items (item id, variant code, quantity, unit total, tax), currency, totals, billing country |
| `workflow.sylius_order_payment.completed.refund` and `.partially_refund`, plus RefundPlugin `UnitsRefunded` | `order.refunded` / `order.partially_refunded` | as above plus refunded item units |
| `workflow.sylius_order.completed.cancel` (after payment) | `order.cancelled` | as above |

Each outbox row: `id` (ULID, the event id), `channel_code`, `order_id`, `sequence` (per order, increasing),
`type`, `payload` (JSON, schema version `v1`), `created_at`, `delivered_at`, `attempts`, `next_attempt_at`,
`last_error`. The row is written in the same Doctrine transaction as the state transition, so an event exists if
and only if the transition committed. A Messenger handler (async transport on Doctrine or Redis) delivers it.

### 5.2 Signature, replay protection, tenant binding

- Header `Ulams-Signature: t=<unix>,v1=<hex>`: HMAC-SHA256 over `"<t>.<raw body>"`, the same scheme as ADR 0103;
  the LMS reuses `Ulams\Webhooks\Support\WebhookSignature::verify` (move it to `core` if the commerce package must
  not depend on `webhooks`). Constant-time compare, several `v1` values allowed during secret rotation.
- Tolerance 300 s on `t`. Older requests are refused with 400 and recovered by reconciliation, never applied late
  from a replay.
- One secret per tenant channel, generated at provisioning (`whsec_…`, 32 bytes), stored encrypted in the tenant env
  (`COMMERCE_WEBHOOK_SECRET`) and in the bridge `ulams_channel_link` table. The payload's `channel_code` must equal
  the tenant's `COMMERCE_CHANNEL`; a valid event for another tenant is refused even if posted to the wrong host.
- `Ulams-Event-Id` header equals the payload id; mismatch → 400.
- The endpoint is public (no auth middleware), rate limited, body size limited (256 KB), and listed in the token
  scope map as `public`.

### 5.3 Idempotency, ordering and verification

- `commerce_order_events` (tenant DB): `provider`, `event_id` (unique with provider), `order_id`, `sequence`,
  `type`, `payload_sha256`, `received_at`, `applied_at`, `outcome` (`applied`, `duplicate`, `stale`, `rejected`,
  `failed`), `error`. Insert with `insertOrIgnore`; a duplicate answers 200 and does nothing.
- `commerce_orders` (projection, tenant DB): `provider`, `external_order_id` (unique with provider), `number`,
  `token`, `user_id`, `state`, `payment_state`, `currency`, `total_minor`, `tax_minor`, `last_sequence`,
  `synced_at`. It is what admin lists and reports read.
- `ApplyOrderEvent` (queued, `tries=10`, backoff) runs in one DB transaction with `SELECT … FOR UPDATE` on the
  projection row. An event with `sequence <= last_sequence` is marked `stale` and skipped.
- **Verify by re-fetch.** Before any grant or revoke the job reads `GET /api/v2/admin/orders/{token}` (and its
  payments) and acts on Sylius's current state, not on the payload. So a leaked webhook secret cannot grant access
  that Sylius did not record, and out-of-order delivery converges to the true state.
- Grants are keyed by `source_key = sylius:<channel>:order_item:<item id>`, unique in `entitlements`, so applying
  the same order twice is a no-op.
- The webhook answers 202 as soon as the event row is stored; the grant happens in the job (seconds). Sylius retries
  non-2xx with backoff up to 72 h.

### 5.4 Refunds, cancellations, revocation

| Case | Default behaviour |
|---|---|
| Full refund (`payment_state=refunded`) | Revoke every entitlement from the order (`status=revoked`, `revoke_reason=refund`) |
| Partial refund that covers all units of an item (RefundPlugin unit refunds) | Revoke that item's entitlement only |
| Partial amount refund (goodwill, not tied to units) | Keep access, record in projection, show in admin |
| Order cancelled after payment | Same as full refund |
| Seat package refunded | Pool `status=revoked`; seats and learner access revoked; admin sees a warning listing affected learners first (refund from the LMS admin shows the count before confirming) |
| Chargeback / dispute | Only when Sylius moves the payment to refunded (Stripe plugin); otherwise manual revoke in the admin |
| Another source still grants the course (admin, group, compliance, other order) | Revoking one entitlement removes `course_user` only when no other active entitlement remains (section 6.2) |

Progress is never deleted on revoke (ADR 0033); a re-purchase restores access with progress intact.

### 5.5 Reconciliation and missed-webhook recovery

- `ulams:commerce:reconcile` per tenant every 5 minutes (scheduler, ADR 0068 lock): reads the bridge event feed
  `GET /api/v2/admin/ulams/events?channel=<code>&after=<cursor>&limit=200` and applies any event not in
  `commerce_order_events` through the same job. Cursor stored in `commerce_sync_state`.
- `ulams:commerce:reconcile --full` nightly: lists the channel's orders with payment state `paid`,
  `partially_refunded`, `refunded` updated in the last 35 days through the Admin API, compares with entitlements and
  repairs both directions (missing grant, grant without paid order). It writes a drift report row and an audit entry
  for every repair. Older windows on demand (`--since=`).
- Health: the admin "Commerce health" card shows last delivered event, events waiting in the bridge outbox (from the
  feed), failed jobs, last drift count. A drift count above zero for two nightly runs raises an admin notification.

### 5.6 Failure modes

| Failure | Detection | Recovery |
|---|---|---|
| LMS down while Sylius pays | Outbox retries; feed cursor lags | Retry delivery; 5-minute reconcile applies from the feed |
| Webhook lost after 2xx but job failed | `outcome=failed` row | Job retries; nightly sweep repairs |
| Sylius Admin API down during verification | Job exception | Job backoff; event stays unapplied; health card shows it |
| Duplicate delivery | Unique `event_id` | 200, no-op |
| Out-of-order refund before paid | `sequence`, re-fetch | Re-fetch reads the true final state |
| Customer not linked to an LMS user (`ulams_user_ref` missing) | Payload check | Event `rejected`, admin alert; guest checkout is disabled, so this signals a bug |
| Wrong tenant / forged signature | Channel binding, HMAC | 400, logged with source IP; no state change |
| Secret rotation | Two `v1` values for 24 h | `ulams:commerce:rotate-secret` updates both sides |
| Clock skew > 300 s | Refused requests | Reconcile applies from the feed; health card shows skew |
| Refund arrives for already-revoked entitlement | Unique key and status | No-op |
| Bridge outbox stuck (worker dead) | Feed shows undelivered age | Reconcile still applies (it reads the feed, not deliveries) |

## 6. Entitlements data model and migration of access (ADR 0130)

### 6.1 Tables (tenant DB, `api/packages/entitlements`)

`entitlements`

| Column | Notes |
|---|---|
| `id` ULID | |
| `user_id` | nullable only while a seat invite is pending |
| `subject_type`, `subject_id` | `course` (bundles expand to courses at grant time; see Q6) |
| `status` | `active`, `revoked`, `expired` |
| `valid_from`, `valid_until` | `valid_until` null = no end |
| `source_type` | `order_item`, `seat`, `admin`, `compliance`, `lti`, `group_legacy`, `wellms_order`, `migration`, `voucher_legacy` |
| `source_key` | unique; e.g. `sylius:acme:order_item:991`, `wellms:order_item:55`, `migration:course_user:12:7` |
| `source_ref` JSON | provider, order number, bundle id, seat pool id, actor |
| `seat_pool_id` | nullable FK |
| `revoked_at`, `revoke_reason`, `granted_by` | |
| timestamps | index on (`user_id`, `subject_type`, `subject_id`, `status`) |

`seat_pools`: `id`, `group_id` (the buyer organisation, from `auth` groups), `owner_user_id`, `subject_type`,
`subject_id`, `seats_total`, `valid_until`, `status`, `source_key` (unique), timestamps.
`seat_assignments`: `seat_pool_id`, `user_id` or `invite_email`, `entitlement_id`, `assigned_at`, `released_at`.

### 6.2 How access is checked

`course_user` stays the projection that `Course::hasUser()` and the policies already read, so H5P, SCORM, LTI and
every existing check keep working unchanged. `EntitlementService` is the only writer for purchased access:

- `grant()` creates the entitlement and calls `CourseAccessService::addAccessForUsers($course, [$uid => ['end_date'
  => max(valid_until of active entitlements)]])`.
- `revoke()` marks the entitlement and recomputes: if other active entitlements remain, `end_date` is updated; if
  none remain, `CourseAccessService::removeAccessForUsers()` is called. Group access (`course_group`) is untouched.
- `ulams:entitlements:expire` (hourly) marks expired rows and recomputes the same way.
- After the backfill (6.3), `CourseAccessService` writes are wrapped by a decorator that also records an `admin`
  (or `compliance`, `lti`) entitlement, so every `course_user` row has at least one entitlement and revocation can
  never remove access granted by someone else.

### 6.3 Migration of existing access (zero lost access)

1. **Snapshot** (`ulams:entitlements:backfill --dry-run`): for every `course_user` row and every
   `products_users` row, compute `hasUser()` and store a hash of the (user, course, end_date) set.
2. **Backfill**: each `course_user` row gets an entitlement. Source is `wellms_order` when a paid `orders` →
   `order_items` → `products_productables` path explains it (provenance kept), `migration` otherwise.
   `products_users` subscriptions become entitlements with `valid_until = end_date`. Idempotent by `source_key`.
3. **Parity check**: recompute `hasUser()` for every pair; the command fails (and changes nothing that run) if any
   pair differs. The report is stored and shown in the admin.
4. **Shadow mode** (CM-2 to CM-10): Wellms cart purchases keep working; a listener on `ProductBought` writes a
   `wellms_order` entitlement, so the entitlement table stays complete while both systems run.
5. The parity check runs again before the per-tenant cutover in CM-10 and nightly during shadow mode.

## 7. Catalogue sync

- `SellableRef` types grow to `course`, `bundle`, `access_pass` (a course or bundle for N days), `seat_package`
  (course or bundle sold per seat). Recurring subscriptions are out (Q5).
- Mapping: one Sylius product per sellable, code `ul-<tenant>-<type>-<id>`, one variant `…-v` with
  `shippingRequired=false`, `tracked=false`, tax category `e-service`, channel pricing for the tenant channel.
  Seat packages use quantity = seats. `commerce_product_links` gains `variant_code`, `channel_code`, `synced_at`,
  `sync_error`, `payload_hash`.
- Triggers: course publish/unpublish (existing `CourseCommerce` hooks), price change in the admin, bundle edits.
  `SyncSellableJob` (queued, retries) calls `syncProduct`. Unpublishing disables the product (`enabled=false`), never
  deletes it, because orders reference it.
- `ulams:commerce:sync --check` nightly reports drift (missing product, price mismatch, enabled mismatch) and
  `--repair` fixes it.
- LMS ids live in the product code and in `commerce_product_links`; the bridge also exposes them on the product as a
  read-only `ulamsRef` field so admins of the native Sylius UI can see the link.

### 7.1 Interface v2 (supersedes the signatures in ADR 0049; ADR 0130 records it)

```php
interface CommerceProvider {                       // core, every provider
    public function key(): string;
    public function syncProduct(SellableRef $ref, ProductSpec $spec): ProductRef;   // ProductSpec: name, price, active, seats
    public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef;
    public function handleOrderEvent(Request $request): ?OrderEvent;               // verified, not yet applied
    public function fetchOrder(string $orderId): OrderSnapshot;                    // truth for grants
}
interface ReconcilesOrders  { public function orderEventsAfter(?string $cursor, int $limit): OrderEventPage; }
interface ProvisionsChannels { public function ensureChannel(ChannelSpec $spec): ChannelRef; }
interface ManagesCommerce   { /* prices, coupons, order list/detail, refund, invoice URL: admin facade, CM-8 */ }
```

`WellmsCartProvider` implements the core only (with `fetchOrder` over `orders`), so the CM-1 interface change does
not break the builder. `Price` stays; `ProductSpec` wraps it.

## 8. Tenant mapping and provisioning

- One Sylius instance, **one channel per tenant**, channel code = tenant slug. Sylius has no tenant isolation of its
  own, so the LMS is the only gate (section 13).
- New provisioner step `commerce_channel` in `TenantProvisioner::STEPS` after `permissions`, skipped when
  `COMMERCE_PROVIDER` is not `sylius`. It calls `ProvisionsChannels::ensureChannel()`:
  - channel: `baseCurrency` (fixed at creation, Q2), `currencies`, `defaultLocale` + `locales` from the tenant
    language, `countries` (EU + configured), `defaultTaxZone` (seller country), `taxCalculationStrategy=
    order_items_based`, `skippingShippingStepAllowed=true`, `shippingAddressInCheckoutRequired=false`, `hostname`
    = tenant front host;
  - payment methods enabled for the channel (Stripe, offline in dev);
  - bridge `ulams_channel_link`: webhook URL (`https://<api_host>/api/commerce/webhooks/sylius`), secret, JWKS URL;
  - writes `COMMERCE_CHANNEL`, `COMMERCE_WEBHOOK_SECRET` to the tenant env (encrypted, like `db_password`).
  - Idempotent: GET by code first; resumable like the other steps.
- Upgrade step (`Upgrade/UpgradeSteps`) creates channels for existing tenants when commerce is enabled later.
- Tenant deletion disables the channel and its products; it does not delete orders (accounting retention).
- Per-tenant settings that admins can change later (prices, locales, extra currencies) go through `ManagesCommerce`;
  base currency cannot change (Sylius constraint), so the builder and provisioning ask once.

## 9. Auth and single login (ADR 0132)

- The LMS stays the identity source. Sylius shop users have no passwords and no login form; guest checkout is off.
- `POST /api/commerce/shop-session` (learner, authenticated) returns a JWT: RS256, `iss=<tenant api host>`,
  `aud=sylius`, `sub=<tenant slug>:<user id>`, `email`, `name`, `channel`, `exp` = 10 minutes, `jti`. Signed with a
  per-tenant commerce key pair generated at provisioning (not the Passport keys, so rotating one does not break the
  other); public keys at `GET /api/commerce/jwks.json`.
- The bridge registers a custom authenticator on the `api_shop` firewall: verifies signature against the JWKS of
  the channel link (cached 10 minutes), `aud`, `exp`, `channel` equals the request's channel; finds the Customer by
  `ulams_user_ref = sub`, or creates Customer + ShopUser (random unusable password) on first use. Email changes in
  the LMS update the Customer on the next token.
- The Astro BFF requests the token server-side and calls Sylius server-side; the browser never sees either token.
  New BFF rules under `/bff/shop/*` allow only the cart, checkout and own-order routes.
- Admin side: the LMS API uses one Sylius admin API user (`ROLE_API_ACCESS`), credentials in the platform env, never
  in tenant envs. Platform staff use the native Sylius admin with their own Sylius admin accounts.

## 10. Admin UX

| Screen (admin `/commerce/*`) | Source | Notes |
|---|---|---|
| Prices | LMS (`commerce_product_links`) → sync | Price per course, bundle, pass, seat package; currency of the channel |
| Coupons | Sylius promotions + coupons through `ManagesCommerce` | Percentage / fixed, validity, usage limit, generate codes; channel enforced |
| Orders | `commerce_orders` projection + detail from Admin API | Filters, CSV export; "access" column shows entitlements per item |
| Refunds | Order detail → `PATCH /admin/payments/{id}/refund` (full) | Partial and unit refunds open the native Sylius admin (RefundPlugin) for staff in CM-8; LMS-side partial refunds later |
| Invoices | InvoicingPlugin PDF streamed through the LMS | Download per order |
| Commerce health | Section 5.5 | Last event, drift, failed jobs |
| Legacy orders | Wellms `orders` (read-only) | After CM-10 |

- Permissions (spatie): `commerce_prices_manage`, `commerce_coupons_manage`, `commerce_orders_list`,
  `commerce_orders_refund`, `commerce_health_view`; token scope map `commerce` extended to `api/admin/commerce/*`.
  Refunds and coupon changes are recorded in the agent audit log when made with a token.
- "Open in Sylius" deep links are shown only to platform staff (platform admin role), not tenant admins.
- Accessibility: admin is not learner-facing, but uses existing Ant Design patterns; the learner checkout is WCAG 2.2 AA.

## 11. EU VAT (OSS): confirmation and options

**What Sylius does (confirmed in the 2.3 source):** taxes are computed per order item from the tax category and the
zone of the **billing address** (`TaxationAddressResolver`; `shipping_address_based_taxation=false` by default),
falling back to the channel's `defaultTaxZone`. Rates can be marked "included in price". There is nothing
OSS-specific: no rate tables, no evidence collection, no OSS report.

**What we configure:**

1. Bridge command `ulams:commerce:seed-eu-vat` creates 27 country zones and one `e-service` tax category with each
   country's standard rate from a versioned data file `api/sylius/bundles/UlamsBridgeBundle/data/eu-vat-rates.json`
   (date, source URL per rate). Rates are seed data that an accountant must confirm (owner input).
2. Prices are entered gross (VAT included) for B2C, as consumers expect; Sylius back-calculates net per country.
   Default Q9.
3. Evidence of place of supply: billing country (required at checkout for digital products) plus the IP country
   (`CF-IPCountry` header, or GeoIP when not behind Cloudflare) stored on the order by the bridge. A mismatch is
   flagged in the admin, not blocked.
4. B2B cross-border with a valid VAT number: reverse charge (0 %) through `flux-se/sylius-eu-vat-plugin` (VIES check)
   if it works on 2.3 (checked in CM-1); otherwise a small bridge VIES client (`ec.europa.eu` VIES REST API) that
   assigns a `reverse-charge` tax category.
5. OSS return: `GET /api/admin/commerce/reports/oss?quarter=2026Q4` in the LMS sums net and VAT per country from the
   order projection (CSV). Filing stays manual.

**Options documented for the owner** (no code needed to switch between 1 and 2):

| Option | When | Effect |
|---|---|---|
| 1. Seller country VAT everywhere | EU cross-border B2C sales below EUR 10,000 per year | One zone (seller country) for all EU buyers |
| 2. Buyer country VAT via OSS (default) | Above the threshold or opted in | Zones per country, quarterly OSS return |
| 3. Merchant of record (Paddle, Lemon Squeezy) | Seller does not want VAT compliance | Not Sylius; a different `CommerceProvider`; out of scope |

Polish B2B invoices may require KSeF structured invoices; Sylius PDFs are not KSeF documents (Q11).

## 12. B2B seat packages

- Product: `seat_package` sellable, variant quantity = seats. Checkout asks for company name, VAT ID and billing
  address (Sylius billing address `company` field + VAT plugin field).
- On `order.paid`: `SeatPoolService::create()` makes a `seat_pools` row (`seats_total` = quantity), creates or reuses
  an organisation group (`auth` groups, `registerable=false`) and makes the buyer its **group manager** (CM-11 role;
  in CM-9 the buyer manages the pool from the account area).
- Assigning: the manager enters e-mails; each takes a seat, creates a `seat` entitlement (user exists) or an invite
  through `assign-without-account` (user created on first login). Releasing a seat is allowed while the learner has
  not started the course (no progress rows); then the entitlement is revoked and the seat returns.
- Top-up: buying the same package again for the same organisation adds seats to the open pool for that subject.
- Invoice: InvoicingPlugin issues it for the order (company data, VAT ID, reverse charge note when applicable).
- Refund: section 5.4.

## 13. Security and tenant isolation

- Isolation by construction on the LMS side (tenant DB); on the Sylius side by channel code enforced in three places:
  the LMS Admin API client injects and checks `channel` on every call and refuses responses for another channel;
  webhook payload channel must equal the tenant channel; shop JWT `channel` must equal the request channel.
- Tenant isolation tests for every new endpoint (non-negotiable): webhook from tenant A's secret posted to tenant B;
  shop-session token from A used against B's channel; admin order detail of B's order requested from A; coupon of B
  edited from A; JWKS of A not accepted by B's channel link.
- Webhook endpoint: no session, HMAC + timestamp + event id + channel binding + body limit + rate limit; payload is
  untrusted data, never interpreted beyond the schema, and re-verified by re-fetch.
- Sylius admin API credentials only in the platform env; the Admin API is reachable only on the internal network in
  the compose/Helm profile (not routed by Caddy). Native Sylius admin routed only on a staff host behind Caddy basic
  auth or IP allow-list (default, Q10).
- Outbound calls to Sylius use a fixed base URL (`COMMERCE_SYLIUS_URL`), never tenant-supplied URLs; the bridge
  posts only to URLs stored in channel links created by the provisioner (SSRF-safe: no admin-editable webhook URL).
- Stay on patched Sylius releases (refund-via-Shop-API CVE fixed in 2.1.16 / 2.2.9); Dependabot for `api/sylius`.
- Telemetry: RefundPlugin and InvoicingPlugin telemetry must be disabled or the plugins patched (risk R6).
- Logging: no PII in event logs beyond order number and user id; card data never reaches us (Stripe Checkout
  hosted page). The commerce flow makes no LLM calls.

## 14. Deployment as an optional profile

- Image `ghcr.io/ulams-dev/ulams-commerce` built from `api/sylius/Dockerfile`: PHP 8.3 FPM + unprivileged nginx
  (ADR 0056) in one image, two commands: `web` and `worker` (`messenger:consume async outbox`). Published by
  `publish.yml` with the same release tag as the app images.
- `api/docker-compose.yml`: profile `commerce` with `sylius` and `sylius-worker`, shared `postgres` (new database
  and role `sylius` from an extra init script), shared Redis (prefix `sylius:`), Mailhog in dev.
- `front/cli/src/stack/`: `commerce: boolean` in `config.ts`; `render.ts` adds the services and a second init SQL
  (`initSql()` today creates only `ulams`); generated secrets: Sylius `APP_SECRET`, JWT keys, admin API user
  password, per-tenant webhook secrets come from provisioning. Kubernetes manifests follow (CM-12).
- Install without commerce: nothing changes (`COMMERCE_PROVIDER=wellms` until CM-10, then `none` for free-only
  installs). Setup wizard "commerce link" (Phase 8) writes `COMMERCE_SYLIUS_URL` and the API user (CM-12).
- Resource budget (single VPS): Sylius web ~150 MB RSS, worker ~100 MB, DB a few hundred MB.

## 15. Tests

| Level | What | Where |
|---|---|---|
| Unit | Signature verify (valid, expired, rotated, wrong tenant), sequence handling, refund policy table, VAT seed parser | `api/packages/commerce/tests/Unit`, `entitlements/tests/Unit` |
| Feature (Laravel, Sylius mocked with recorded JSON fixtures, `Http::fake`) | Webhook → event row → job → entitlement → `course_user`; duplicate; stale; forged; re-fetch says unpaid → no grant; refund revokes; other source keeps access; reconcile feed applies missed event; nightly sweep repairs both directions; backfill parity (seeded Wellms data) | `api/packages/commerce/tests/Feature`, `commerce-sylius/tests/Feature`, `entitlements/tests/Feature` |
| Tenant isolation | Section 13 list | same, `IsolationTest` per package |
| Bridge (PHPUnit in `api/sylius`) | Outbox row written in the transition transaction; feed endpoint paging and channel filter; authenticator (valid, wrong aud, wrong channel, expired); channel link endpoints need admin role | `api/sylius/tests` |
| Contract | The LMS fixture files are produced by the bridge test suite (`tests/fixtures/sylius/*.json`) so both sides agree on schema `v1` | shared fixture dir, checked in CI |
| E2E script (CM-1) | `scripts/commerce-e2e.sh`: stack with `commerce` profile; create course + product; shop checkout with the offline method; admin completes payment → access within 10 s; replay the webhook → one entitlement; block the webhook URL, pay, run reconcile → access; refund → access revoked | local and nightly |
| E2E in CI (CM-7) | Same flow as Playwright in `front/web/tests/e2e/commerce.spec.ts` against the stack, Stripe test mode replaced by the offline method; nightly job `commerce-e2e` in `nightly-conformance.yml`, plus a PR job when `api/packages/commerce*`, `entitlements` or `api/sylius` change | quality-bar item |
| Accessibility | Checkout, cart, return and account orders pages in `tests/a11y` with a commerce mock in `tests/mock-api/server.mjs` | `web-a11y` job |
| CI | New PHPUnit shard paths for the new packages in the existing `commerce` shard; `api/sylius` job (composer validate, PHPUnit, PHPStan) | `.github/workflows/ci.yml` |

No LLM is involved, so no eval is needed.

## 16. Open questions with recommended defaults (defaults are used)

| # | Question | Recommended default |
|---|---|---|
| Q1 | Sylius version | 2.3.x (current, longest support); fall back to 2.2.x only if CM-1 finds a blocking plugin incompatibility |
| Q2 | Channel base currency (fixed at creation) | PLN for Polish-language tenants, EUR for every other tenant; builder and provisioning can override once |
| Q3 | VIES / reverse charge if `flux-se/sylius-eu-vat-plugin` fails on 2.3 | Own small VIES client in the bridge (no new dependency) |
| Q4 | Przelewy24 | Through Mollie (`sylius/mollie-plugin`, MIT, in Sylius-Standard) once the owner has a Mollie account; Stripe (cards, BLIK, P24 via Stripe where available) is the first gateway; no BitBag port |
| Q5 | Subscriptions / recurring billing | Not in this block: sell time-limited access passes; existing Wellms subscriptions are honoured until their `end_date` and not renewed; recurring billing gets its own plan later |
| Q6 | Bundles: grant per course or one bundle entitlement | Expand to one entitlement per course at grant time (`source_ref.bundle`); courses added to a bundle later are not granted to past buyers |
| Q7 | Partial amount refunds | Keep access (section 5.4); unit refunds revoke the item |
| Q8 | Sylius Plus | Not needed; own seat pools replace B2B Suite, channel enforcement in the LMS replaces advanced permissions |
| Q9 | Prices gross or net | Gross (VAT included) for B2C tenants; a tenant setting switches to net for B2B-only academies |
| Q10 | Native Sylius admin exposure | Staff host only (`commerce-admin.<platform host>`), Caddy basic auth plus Sylius login; never on tenant hosts |
| Q11 | KSeF (Polish structured e-invoices) | Out of scope for this block; documented; Polish B2B sellers use their accounting tool to issue the KSeF invoice from the Sylius order export until a KSeF plan exists |
| Q12 | Telemetry in RefundPlugin / InvoicingPlugin | Disable through configuration if possible; otherwise a composer patch that removes it; if neither works, drop RefundPlugin (full refunds through the core payment refund) and keep InvoicingPlugin patched |
| Q13 | PDF engine for invoices (knp-snappy needs wkhtmltopdf, unmaintained) | Use our existing `pdf` service through a small `pdf-generation-bundle` adapter in the bridge; no wkhtmltopdf in the image |
| Q14 | PostgreSQL for Sylius | Shared PostgreSQL 17 server, database `sylius`; if CM-1 shows blocking issues, MySQL 8.4 as a commerce-profile sidecar and a note against the Phase 8 item |
| Q15 | Where the shop pages live | `front/web` only (single frontend); the legacy React cart is not ported |
| Q16 | Seat release rule | Allowed while the learner has no progress on the subject; otherwise the seat stays used |
| Q17 | Webhook delivery retry window | 72 h with exponential backoff; reconciliation covers anything later |
| Q18 | Reconcile cadence | Feed every 5 minutes, full sweep nightly over 35 days |
| Q19 | Cutover order | Per tenant, demo tenants first, then the rest; Wellms cart endpoints answer 410 after a tenant's cutover; package removal one release later |
| Q20 | Vouchers that cannot be mapped (allowed-users lists, category rules without taxons) | Not migrated; listed in the cutover report; admins recreate them by hand |
| Q21 | Historical Wellms orders | Not imported into Sylius (the API cannot create orders); kept read-only in the LMS with a "Legacy orders" admin view and CSV export |

## 17. Owner-only inputs (not decisions an agent can default)

| Input | Needed by | Without it |
|---|---|---|
| Stripe account: test keys (then live keys) | CM-4 (test), launch (live) | CM-1..CM-7 use the offline payment method; Stripe flow untested |
| Mollie account for P24 (or a decision to skip P24) | CM-4 | P24 unavailable |
| Seller legal data for invoices (company, address, VAT ID), OSS registration status, accountant-confirmed VAT rates | CM-4 / CM-9 | Seed data marked "unconfirmed", invoices show placeholder seller |
| Sylius hosting: same VPS as the app (default) or separate; resource budget | CM-12, first production deploy | Runs locally only (consistent with "work locally, publish later") |
| Staff Sylius admin accounts and the staff host name | CM-8 | Native admin reachable only on localhost |
| Any spending (Sylius Plus, paid plugins) | none planned | n/a |

## 18. Risks

| # | Risk | Mitigation |
|---|---|---|
| R1 | Sylius on PostgreSQL is less exercised than MySQL | CM-1 runs migrations, fixtures and the full checkout on PG 17; fallback Q14 |
| R2 | Channel resolution in the Shop API is hostname-based; the BFF calls server-side | Bridge channel context reads the channel from the verified JWT (and only from it); tested in CM-1/CM-5 |
| R3 | Plugin lag behind 2.3 (VAT, admin MCP) | Q1, Q3; pin versions; Dependabot |
| R4 | Webhook secret leak | Verify-by-re-fetch makes a forged event harmless beyond a wasted API call |
| R5 | Two access systems during shadow mode drift | Nightly parity check (6.3), per-tenant cutover |
| R6 | Plugins enforce telemetry, conflicting with air-gapped installs | Q12, verified in CM-1 |
| R7 | wkhtmltopdf dependency | Q13 |
| R8 | One Sylius instance for all tenants: noisy neighbour, global admin | Rate limits per channel in the bridge; staff-only native admin; per-tenant Sylius instance stays possible because the LMS stores the Sylius URL per tenant env |
| R9 | `course_user` projection diverges from entitlements via code paths that bypass `CourseAccessService` (e.g. `Course::attachToUser` from the cart) | Decorator + nightly parity; CM-10 removes the cart path |
| R10 | Scope is large (13 TODO items) | Milestones are independent slices; CM-1 can stop the block early if the flow is not viable |

## 19. Milestones

Each milestone is one or a few PRs, demoable on its own, with tests in the same commits.

| # | Milestone | TODO items | Demo |
|---|---|---|---|
| **CM-1** | **Risk prototype: order → entitlement with a Sylius test setup.** `api/sylius` (Sylius-Standard 2.3 on PG 17, compose profile `commerce`), bridge bundle with outbox + signed delivery + event feed for `paid`/`refunded`, offline payment method, digital channel `demo`; LMS: `entitlements` package core (table, `grant`/`revoke` via `CourseAccessService`), webhook endpoint (signature, timestamp, channel binding, idempotency), `ApplyOrderEvent` with re-fetch, `ulams:commerce:reconcile` (feed), `scripts/commerce-e2e.sh`. Also checks Q1, Q3, Q12, Q14 | open decision "Prototype…"; T3, T5 (partial); quality-bar E2E (as a script) | Pay → access in < 10 s; replay → no duplicate; webhook blocked → reconcile grants; refund → revoked |
| CM-2 | Entitlements complete + access backfill in shadow mode: all source types, expiry job, `CourseAccessService` decorator, backfill + parity report, `ProductBought` shadow listener | T3, T10 (access part) | Parity report "0 differences" on demo data; admin attach creates an `admin` entitlement |
| CM-3 | Interface v2 + `SyliusProvider` + catalogue sync (course, bundle, access pass), publish/unpublish, nightly drift check, nightly full sweep | T1, T2 (adapter), T4 (without seats), T5 | Publish a builder course → product appears in Sylius with price; unpublish → disabled |
| CM-4 | Tenant ↔ channel at provisioning + upgrade step for existing tenants; EU VAT seed; Stripe test gateway; Mollie/P24 spike | T7, T11 (setup) | `ulams tenant create` makes a channel with currency, locale, tax zone; Stripe test payment grants access |
| CM-5 | Single login and checkout in the single frontend: shop JWT + JWKS, bridge authenticator, BFF `/bff/shop`, `front/sdk` commerce client, cart, checkout, return (status only), account orders; WCAG 2.2 AA | T6; Phase 5 "Single frontend…" | Logged-in learner buys a course on `acme.app.localhost` without a second login |
| CM-6 | Per-tenant catalogue and sales pages: real `PurchaseCard` CTA, catalogue with prices, course-site pages (ADR 0048) with buy blocks, theme-aware | T12 | Two tenants show their own catalogues, prices and themes |
| CM-7 | Commerce E2E in CI (Playwright on the commerce stack, nightly + path-filtered PR job) | quality-bar "Commerce E2E…" | Green `commerce-e2e` job |
| CM-8 | Unified admin: prices, coupons, orders, full refunds, invoices, health; permissions and token scopes; staff-only native admin | T8 | Tenant admin creates a coupon and refunds an order without leaving the LMS admin |
| CM-9 | B2B seat packages: seat sellable, pools, assignment and release, top-up, company checkout fields, reverse charge, invoices | T9, T4 (seats), T11 (B2B) | Company buys 5 seats, assigns 3 learners, downloads the invoice |
| CM-10 | Migration cutover: vouchers → promotions, legacy orders view, per-tenant switch to Sylius, Wellms cart endpoints 410, RevenueCat into `EntitlementService`; `COMMERCE_PROVIDER` default `sylius` when the profile is on | T10, T2 (default) | Cutover report on a copy of a real tenant: 0 lost access, coupon mapping table |
| CM-11 | Partner/customer portals: `group_manager` role scoped to a group subtree, team pages in `front/web/account/team`, scoped reports from the 6.5 dashboard API | T13 | A customer manager sees only their learners' progress and certificates |
| CM-12 | Deployment profile finished: `ulams stack up --commerce` (Docker and Kubernetes), image publishing, setup wizard commerce link | Phase 8 "Commerce as optional profile…" (partial: Helm chart is its own item) | One command brings up LMS + Sylius on a clean machine |

Order: CM-1 first and alone (it can change the design); CM-2 and CM-3 can run in parallel after it; CM-4 → CM-5 →
CM-6/CM-7; CM-8 and CM-9 after CM-5; CM-10 after CM-8; CM-11 and CM-12 independent after CM-9/CM-4.

## 20. New dependencies (justification)

| Dependency | Where | Licence | Why / impact |
|---|---|---|---|
| `sylius/sylius` 2.3 (Sylius-Standard skeleton) | `api/sylius` | MIT | The spec's commerce engine; separate image, optional profile |
| `flux-se/sylius-stripe-plugin` 2.1 | `api/sylius` | MIT | Stripe via Payment Requests; shipped with Sylius-Standard |
| `sylius/mollie-plugin` 3.5 | `api/sylius` (CM-4) | MIT | P24 path (Q4) |
| `sylius/refund-plugin` 2.2, `sylius/invoicing-plugin` 2.3 | `api/sylius` | MIT | Unit refunds, credit memos, invoices; telemetry and PDF caveats (Q12, Q13) |
| `flux-se/sylius-eu-vat-plugin` 3.x | `api/sylius` (CM-9) | to verify in CM-1 | VIES + reverse charge; fallback Q3 |
| none in the LMS | `api/packages/*` | — | JWT signing with `lcobucci/jwt` (already in `api/composer.lock` through Passport; declared explicitly in the new package); HTTP via the Laravel client |
| none in `front/*` | — | — | Shop client is fetch-only like the rest of the SDK |

## 21. Migrations (LMS, tenant DB)

`entitlements`, `seat_pools`, `seat_assignments` (entitlements package); `commerce_order_events`, `commerce_orders`,
`commerce_sync_state`; columns on `commerce_product_links` (`variant_code`, `channel_code`, `synced_at`,
`sync_error`, `payload_hash`). All additive; no change to `course_user`. Sylius-side tables come from Sylius and the
bridge bundle's Doctrine migrations (`ulams_outbox_event`, `ulams_channel_link`, customer `ulams_user_ref` column,
order `ip_country` column).
