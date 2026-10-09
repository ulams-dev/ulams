<?php

return [
    /**
     * Commerce backend: "wellms" is the built-in cart and payments. A Sylius adapter registers
     * itself through CommerceManager::extend() (planned, Phase 6.4).
     */
    'provider' => env('COMMERCE_PROVIDER', 'wellms'),

    /** Learner front end; the Wellms provider sends buyers to `<front_url>/cart`. */
    'front_url' => env('COMMERCE_FRONT_URL', env('FRONTEND_URL')),
];
