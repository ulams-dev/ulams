<?php

/*
 * Outgoing webhooks (Phase 7.3). Every value can be overridden per environment; the defaults are
 * safe for production: https only, public addresses only, no redirects.
 */

return [
    // Event payload version a new endpoint is pinned to. Payloads of a pinned version never change shape.
    'api_version' => '2026-10-20',

    // Local development only: accept http:// and private addresses (a local receiver, ngrok-less testing).
    'allow_insecure' => (bool) env('WEBHOOKS_ALLOW_INSECURE', false),

    // Optional allow-list of target hosts for this tenant (comma separated). Empty means any public host.
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('WEBHOOKS_ALLOWED_HOSTS', ''))))),

    'max_endpoints' => 20,

    // Total delivery attempts (the first one included) and the wait before each retry, in seconds.
    'max_attempts' => 8,
    'backoff_seconds' => [60, 300, 1800, 7200, 21600, 43200, 86400],

    'timeout' => 10,
    'connect_timeout' => 5,
    'response_body_bytes' => 2000,

    // An endpoint whose deliveries fail this many times in a row is disabled (and can be enabled again).
    'disable_after_failures' => 25,

    // Deliveries and their attempts are deleted after this many days (`webhooks:prune`, daily).
    'retention_days' => 30,

    // Receivers should reject a signature older than this many seconds (replay protection).
    'signature_tolerance' => 300,

    'queue' => env('WEBHOOKS_QUEUE'),
    'queue_connection' => env('WEBHOOKS_QUEUE_CONNECTION'),

    // Per user and minute: the management API, and test sends plus replays (they call out to a third party).
    'api_rate_limit' => 60,
    'send_rate_limit' => 10,
];
