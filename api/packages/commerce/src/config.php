<?php

return [
    /**
     * Commerce backend: "wellms" is the built-in cart and payments. A Sylius adapter registers
     * itself through CommerceManager::extend() (planned, Phase 6.4).
     */
    'provider' => env('COMMERCE_PROVIDER', 'wellms'),

    /** Learner front end; the Wellms provider sends buyers to `<front_url>/cart`. */
    'front_url' => env('COMMERCE_FRONT_URL', env('FRONTEND_URL')),

    /**
     * The provider channel this tenant sells through (one channel per tenant, ADR 0130). Events and orders of
     * another channel are refused even when their signature is valid.
     */
    'channel' => env('COMMERCE_CHANNEL'),

    /** Inbound order-event webhook: POST /api/commerce/webhooks/{provider}. */
    'webhook' => [
        /** Per-tenant HMAC secret (`whsec_...`). Without it the endpoint answers 404. */
        'secret' => env('COMMERCE_WEBHOOK_SECRET'),
        /** The secret being rotated out; both are accepted while it is set. */
        'previous_secret' => env('COMMERCE_WEBHOOK_SECRET_PREVIOUS'),
        /** Seconds a signed timestamp may differ from the server clock. Older requests are refused, never applied late. */
        'tolerance' => 300,
        /** Largest body accepted, in bytes. */
        'max_body' => 262144,
        'rate_limit' => 300,
    ],

    /** `ulams:commerce:reconcile`: repairs webhooks that never arrived by reading the provider's event feed. */
    'reconcile' => [
        /** Run it from the scheduler every five minutes (per tenant). Off until a provider with a feed is configured. */
        'schedule' => (bool) env('COMMERCE_RECONCILE', false),
        'page_size' => 200,
        /** An event that was stored but never applied for this long is queued again. */
        'stuck_after_minutes' => 5,
    ],
];
