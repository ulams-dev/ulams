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

    /** Where `createCheckout()` sends the buyer: the single frontend's checkout page (`commerce.front_url`). */
    'checkout_path' => '/checkout/start',
];
