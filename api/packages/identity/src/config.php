<?php

/*
 * Identity (Phase 6.3): single sign-on per tenant. ID-1 ships OpenID Connect, ID-2 SAML 2.0; see docs/plans/phase-6-identity.md.
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

    'saml' => [
        // An assertion older than this (from its IssueInstant) is refused even when its own validity window is longer.
        'max_age' => (int) env('IDENTITY_SAML_MAX_AGE', 300),
        // Allowed clock difference between this server and the IdP, in seconds (the library's ALLOWED_CLOCK_DRIFT is 180).
        'clock_skew' => 180,
        // Largest SAMLResponse / SAMLRequest / LogoutResponse body read (bytes, before base64 decoding).
        'max_body_bytes' => 262144,
        // Outbound calls to fetch IdP metadata, and the largest document accepted.
        'timeout' => 10,
        'metadata_max_bytes' => 2097152,
        // SP key pairs generated per provider: RSA size and validity (plan 4.2).
        'sp_key_bits' => 3072,
        'sp_cert_years' => 5,
        // Daily refresh of the metadata of providers that have a metadata URL (identity:refresh-metadata).
        'metadata_refresh_at' => '03:30',
        // Single logout: how long a LogoutRequest we sent waits for the IdP's answer.
        'slo_ttl' => 600,
    ],

    // SCIM 2.0 server (ID-4, ID-5): /api/scim/v2 with a bearer token per identity provider.
    'scim' => [
        // Requests per minute and token. Entra ID bursts on the first sync, hence the generous default.
        'rate_limit' => (int) env('IDENTITY_SCIM_RATE', 600),
        // `deactivate` keeps the account and the learning record; `anonymise` soft-deletes and masks personal data.
        'delete_behaviour' => env('IDENTITY_SCIM_DELETE', 'deactivate'),
        'default_count' => 100,
        'max_count' => 200,
        'max_body_bytes' => 1048576,
        // Published as documentationUri in ServiceProviderConfig when set (the URL of this deployment's documentation).
        'docs_url' => env('ULAMS_DOCS_URL'),
    ],

    // Development only: lets discovery, token and JWKS URLs be http:// and private addresses (Keycloak in compose).
    // Never switch this on in production: it lifts the SSRF guard for provider URLs.
    'allow_insecure_urls' => (bool) env('IDENTITY_ALLOW_INSECURE_URLS', false),

    // Per IP and minute: public sign-in routes, and the admin API per user.
    'public_rate_limit' => 30,
    'admin_rate_limit' => 60,
];
