<?php

/*
 * Identity (Phase 6.3): single sign-on per tenant. ID-1 ships OpenID Connect; see docs/plans/phase-6-identity.md.
 */

return [
    // Switches the public sign-in routes off for the whole deployment. Admin routes stay.
    'enabled' => (bool) env('IDENTITY_ENABLED', true),

    // Identity providers one tenant may configure.
    'max_providers' => (int) env('IDENTITY_MAX_PROVIDERS', 10),

    'oidc' => [
        // Discovery documents and JWKS are cached; an unknown `kid` refetches the JWKS once.
        'discovery_ttl' => 86400,
        'jwks_ttl' => 3600,
        // Clock skew allowed on exp, nbf and iat of ID tokens.
        'leeway' => 60,
        // Outbound calls to the provider (discovery, JWKS, token, userinfo).
        'timeout' => 10,
        'max_response_bytes' => 1048576,
    ],

    // Development only: lets discovery, token and JWKS URLs be http:// and private addresses (Keycloak in compose).
    // Never switch this on in production: it lifts the SSRF guard for provider URLs.
    'allow_insecure_urls' => (bool) env('IDENTITY_ALLOW_INSECURE_URLS', false),

    // Per IP and minute: public sign-in routes, and the admin API per user.
    'public_rate_limit' => 30,
    'admin_rate_limit' => 60,
];
