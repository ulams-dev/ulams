<?php

$list = fn (?string $value): array => array_values(array_filter(array_map(
    fn ($item) => trim($item),
    explode(',', (string) $value)
)));

return [
    'ignore_migrations' => false,

    /*
     * Exact-origin check for state-changing requests (docs/content-origin.md, "Same-site content
     * origin"). The API authenticates with bearer tokens, which a browser does not attach by
     * itself, so this is defence in depth: a page on the content origin (third-party package
     * code) can never make the learner's browser write to the API, even where SameSite cookies
     * would otherwise be sent (a content origin that is a subdomain of the app's site).
     */
    'security' => [
        'origin_check' => filter_var(env('ORIGIN_CHECK', true), FILTER_VALIDATE_BOOLEAN),
        // Origins of the tenant's own apps: FRONTEND_URL, ADMIN_URL and APP_URL are always trusted.
        'admin_url' => env('ADMIN_URL'),
        // Extra origins, comma separated (other first-party frontends, a staging admin, ...).
        'trusted_origins' => $list(env('TRUSTED_ORIGINS')),
        // Outside production localhost and *.localhost (any port) are trusted too (dev stack).
        'trust_localhost_outside_production' => true,
        // Authenticated by something other than the browser's ambient state (a scoped token in a
        // header, a signature, HTTP Basic from an LRS client) and called by design from other
        // origins or none: the sandboxed players send `Origin: null`.
        'origin_exempt' => [
            'api/scorm/content/*/track',
            'api/liascript/progress/*',
            // cmi5 AUs on the content origin, authenticated by the one-time token of the launch URL
            'api/cmi5/fetch',
            'trax/api/*/xapi/std/*',
            'api/lti/jwks',
            'api/lti/platform/authorize',
            'api/lti/platform/token',
            'api/lti/platform/deep-links',
            'api/lti/platform/ags/*',
            'api/lti/tool/login',
            'api/lti/tool/launch',
            // second step of a launch that used the platform's storage: posted by our own page, whose
            // referrer policy makes the browser send Origin: null
            'api/lti/tool/launch/verify',
            'api/lti/tool/deep-link',
            'api/payments-gateways/callback/*',
            'api/payments-gateways/webhook/*',
        ],
    ],
];
