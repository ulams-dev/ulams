<?php

return [
    /** `ulams:entitlements:expire`: closes entitlements whose end has passed. Runs hourly from the scheduler (per tenant). */
    'expire' => [
        'schedule' => (bool) env('ENTITLEMENTS_EXPIRE', true),
    ],

    /**
     * `ulams:entitlements:parity`: compares `course_user` with the entitlements and stores a report. Nightly while the
     * Wellms cart and Sylius run side by side (shadow mode, Phase 6.4), and again before each tenant's cutover.
     */
    'parity' => [
        'schedule' => (bool) env('ENTITLEMENTS_PARITY', true),
        /** How many differing pairs a stored report keeps. */
        'sample' => 100,
    ],
];
