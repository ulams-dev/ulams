<?php

return [
    /**
     * The Sylius service of this installation (ADR 0131). One fixed base URL from the platform environment, never a URL a
     * tenant or an admin typed: the Admin API is internal and the tenant only chooses a channel (`commerce.channel`).
     */
    'url' => env('COMMERCE_SYLIUS_URL'),

    /** The one Sylius admin API user the LMS signs in with (`ROLE_API_ACCESS`). Platform env only, never a tenant env. */
    'admin' => [
        'email' => env('COMMERCE_SYLIUS_ADMIN_EMAIL'),
        'password' => env('COMMERCE_SYLIUS_ADMIN_PASSWORD'),
    ],

    /** Seconds before a request to Sylius is given up. */
    'timeout' => 15,

    /** Tax category of every product (digital service); created when missing. */
    'tax_category' => 'e-service',

    /**
     * Payment methods enabled on every tenant channel (ADR 0131). `offline` pays later and is for development and tests
     * only. `stripe` is created when both test or live keys are set (platform env only): Stripe Checkout through
     * flux-se/sylius-stripe-plugin; which methods the buyer sees (cards, BLIK, Przelewy24) is chosen in the Stripe dashboard.
     */
    'payments' => [
        'offline' => (bool) env('COMMERCE_SYLIUS_OFFLINE', false),
        'stripe' => [
            'publishable_key' => env('COMMERCE_STRIPE_PUBLISHABLE_KEY'),
            'secret_key' => env('COMMERCE_STRIPE_SECRET_KEY'),
        ],
    ],

    /** The seller's country: its tax zone applies when a buyer's billing address does not decide. Confirm with the accountant. */
    'seller_country' => env('COMMERCE_SELLER_COUNTRY', 'PL'),

    /** Countries a channel may sell to besides the 27 EU member states, e.g. "GB,CH,NO". */
    'extra_countries' => env('COMMERCE_EXTRA_COUNTRIES', ''),

    /** Base currency of new channels: empty means PLN for Polish-language tenants and EUR for every other (plan Q2). It cannot change later. */
    'default_currency' => env('COMMERCE_SYLIUS_CURRENCY'),

    /** Where `createCheckout()` sends the buyer: the single frontend's checkout page (`commerce.front_url`). */
    'checkout_path' => '/checkout/start',
];
