<?php

return [
    /**
     * Defaults settings
     */
    'default_gateway' => env('PAYMENTS_DEFAULT_GATEWAY', 'Stripe'),
    'default_currency' => env('PAYMENTS_DEFAULT_CURRENCY', Ulams\Payments\Enums\Currency::USD),

    /**
     * Driver specific settings
     */
    'drivers' => [
        'free' => [],
        'stripe' => [
            'enabled' => true,
            'secret_key' => env('PAYMENTS_STRIPE_SECRET_KEY'),
            'publishable_key' => env('PAYMENTS_STRIPE_PUBLISHABLE_KEY'),
            'allowed_payment_method_types' => ['card'],
            /**
             * Signing secret of the Stripe webhook endpoint (whsec_...). Callbacks are only accepted when they
             * carry a valid Stripe-Signature header, or when the PaymentIntent is fetched from the Stripe API.
             */
            'webhook_secret' => env('PAYMENTS_STRIPE_WEBHOOK_SECRET'),
            'webhook_tolerance' => (int) env('PAYMENTS_STRIPE_WEBHOOK_TOLERANCE', 300),
            'api_base' => env('PAYMENTS_STRIPE_API_BASE', 'https://api.stripe.com'),
        ],
        'przelewy24' => [
            'enabled' => true,
            'live' => env('PAYMENTS_PRZELEWY24_LIVE', true),
            'merchant_id' => env('PAYMENTS_PRZELEWY24_MERCHANT_ID'),
            'pos_id' => env('PAYMENTS_PRZELEWY24_POS_ID'),
            'api_key' => env('PAYMENTS_PRZELEWY24_API_KEY'),
            'crc' => env('PAYMENTS_PRZELEWY24_CRC'),
        ],
        /**
         * RevenueCat in-app purchases. Disabled by default: a purchase is only accepted when a server-side receipt
         * verifier (a class implementing Ulams\Payments\Gateway\Contracts\ReceiptVerifier) is configured.
         */
        'revenuecat' => [
            'enabled' => env('PAYMENTS_REVENUECAT_ENABLED', false),
            'receipt_verifier' => env('PAYMENTS_REVENUECAT_RECEIPT_VERIFIER'),
        ],
    ]
];
