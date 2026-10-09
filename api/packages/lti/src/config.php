<?php

return [
    /*
     * Our issuer (platform side) and the base URL of our LTI endpoints. Defaults to APP_URL, which
     * is the tenant API URL in every tenant env file.
     */
    'issuer' => env('LTI_ISSUER'),

    /*
     * Key rotation: `ulams:lti:rotate-keys` promotes the `next` key, generates a new one and keeps
     * retired keys in the JWKS for this many days, so tokens signed just before a rotation verify.
     */
    'retired_key_grace_days' => (int) env('LTI_RETIRED_KEY_GRACE_DAYS', 30),
    'key_bits' => (int) env('LTI_KEY_BITS', 2048),

    /*
     * Lifetimes, seconds.
     */
    'login_hint_ttl' => (int) env('LTI_LOGIN_HINT_TTL', 120),
    'id_token_ttl' => (int) env('LTI_ID_TOKEN_TTL', 300),
    'access_token_ttl' => (int) env('LTI_ACCESS_TOKEN_TTL', 3600),
    'state_ttl' => (int) env('LTI_STATE_TTL', 600),
    'code_ttl' => (int) env('LTI_CODE_TTL', 60),

    /*
     * Outgoing requests to tools and platforms (JWKS, OIDC, AGS) only go to https URLs on public
     * addresses. Development only: allow http and private addresses (docker, localhost).
     */
    'allow_insecure_urls' => filter_var(env('LTI_ALLOW_INSECURE_URLS', false), FILTER_VALIDATE_BOOLEAN),
    'http_timeout' => (int) env('LTI_HTTP_TIMEOUT', 10),
    'jwks_cache_ttl' => (int) env('LTI_JWKS_CACHE_TTL', 600),

    /*
     * Tool side: where the learner lands after a launch. `{code}` is a one-time code the front
     * exchanges for a session (POST /api/lti/tool/exchange), `{course}` the course id.
     */
    'tool_landing_url' => env('LTI_TOOL_LANDING_URL', '{front}/lti/launch?code={code}&course={course}'),
];
