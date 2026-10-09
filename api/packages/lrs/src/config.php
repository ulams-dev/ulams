<?php

return [
    // Lifetime of the LRS session token a cmi5 AU gets from POST /api/cmi5/fetch (ADR 0046).
    'session_minutes' => (int) env('CMI5_SESSION_MINUTES', 120),
];
