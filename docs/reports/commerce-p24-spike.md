# Spike: Przelewy24 and BLIK for the Sylius shop (CM-4)

Date: 2026-10-23. Plan: `docs/plans/phase-6-commerce.md` (Q4, section 17). Decision owner: the product owner (accounts).

## Question

Polish buyers pay mostly with BLIK and Przelewy24 (P24). The plan names two ways to offer them from the Sylius shop: through Stripe, or through Mollie. The BitBag Przelewy24 plugin supports Sylius 1.12 and 1.13 only. Which way works with Sylius 2.3, and what can be tried without an account?

## What was checked locally (Sylius 2.3.0 on PostgreSQL 17, the `commerce` compose profile)

| Check | Result |
|---|---|
| `flux-se/sylius-stripe-plugin` 2.1 installed; Admin API creates a `stripe_checkout` payment method from `publishable_key` and `secret_key` | Works; the LMS does this for every tenant channel when `COMMERCE_STRIPE_PUBLISHABLE_KEY` and `COMMERCE_STRIPE_SECRET_KEY` are set (`SyliusProvider::ensureChannel`, live test `SyliusChannelLiveTest`). Dummy `pk_test_` and `sk_test_` values are accepted: Sylius validates the format, not the account |
| The Stripe plugin sets no explicit `payment_method_types` | Read from the plugin source: the Checkout Session is created without a method list, so Stripe offers the methods enabled in the Stripe dashboard for the session's currency. Not run against Stripe |
| `sylius/mollie-plugin` 3.5 installed; Admin API creates a `mollie` payment method | Works (created disabled with dummy test keys, then not kept). The plugin has a `Przelewy24` method model (`PaymentMethodRegistry`: `PaymentMethod::PRZELEWY24`) |
| Both plugins publish a telemetry policy | Handled by project configuration and a compiler pass (`DisableTelemetryPass`, plan Q12); in the 2.3.0 container built here no telemetry service is loaded in any case |
| Przelewy24 or BLIK payment end to end | Not possible without an account |

## Options

| Option | How | For | Against |
|---|---|---|---|
| A. Stripe only (recommended first) | Enable BLIK and Przelewy24 in the Stripe dashboard (they are offered for PLN, and P24 for EUR too, per Stripe's public documentation, not tested here) | One gateway, one webhook, one account for cards, BLIK and P24; nothing to build: the Stripe method already exists per channel | Stripe fees and availability decide; owner needs a Stripe account with these methods approved |
| B. Mollie | `sylius/mollie-plugin`, method `przelewy24` (and BLIK in recent Mollie releases) | A second route if Stripe refuses a method; the plugin is in Sylius-Standard | A second account, webhook and refund path to operate; the plugin brings recurring-payment code the project does not use (plan Q5); not tested here |
| C. A port of the BitBag plugin | Own code | None that A and B lack | Maintenance for a payment gateway; rejected in the plan |

## Recommendation

Option A for launch. Option B only if the owner cannot get BLIK or P24 enabled on Stripe, or wants Mollie for fees. Neither needs LMS code beyond what CM-4 contains: the method a buyer sees is decided at the gateway, and the LMS grants access only from the verified order either way.

## What the owner has to do (not possible for an agent)

1. Create a Stripe account, put the **test** keys in the API platform env (`COMMERCE_STRIPE_PUBLISHABLE_KEY`, `COMMERCE_STRIPE_SECRET_KEY`), run `php artisan ulams:tenant:commerce-channel` and make a test payment with a Stripe test card, BLIK test code and the P24 test bank. Until then the Stripe flow is **untested against Stripe**.
2. In the Stripe dashboard enable BLIK and Przelewy24 for the account; add the webhook endpoint Sylius shows for the `stripe` method.
3. Decide whether Mollie is wanted at all; if so, create the account and a payment method from the keys.
4. Replace the test keys by live keys only at launch, and have the accountant confirm the VAT rates in `api/packages/commerce-sylius/resources/eu-vat-rates.json`.
