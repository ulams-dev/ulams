<?php

/**
 * Routes that are not behind `auth:api`, each with the reason it is allowed to be (ADR 0190).
 * `Tests\Integrations\TenantIsolationCoverageTest` fails when a registered route is neither behind
 * `auth:api` nor matched here, and when an entry here matches no route.
 *
 * Entry: method ('GET', 'POST', ... or '*'), uri (as in `php artisan route:list`, `*` is a wildcard,
 * the first matching entry wins), kind, reason, and optionally:
 *   - covered_by: the test that proves what the probe cannot (own tokens, data separation);
 *   - optional:   the route exists only in some environments, so it may match nothing;
 *   - probe:      false = the test does not call the route (it is slow or renders a UI).
 *
 * Kinds (what the test asserts for a guest request and for a request with another tenant's token):
 *   - open:            anybody may call it, nothing is asserted. Tenant data separation comes from the host
 *                      (every tenant has its own database), not from a token. Use only when the route
 *                      needs no credential at all.
 *   - inline-auth:     the route checks the user itself (Gate, Horizon gate, `auth:web`) and must answer
 *                      401 or 403.
 *   - own-credentials: the caller proves itself with something other than a Passport token (signature,
 *                      signed URL, tool token, client credentials in the body) and must answer 4xx to a
 *                      request that carries none.
 *   - known-gap:       a real hole that is not fixed yet; nothing is asserted, the reason names the issue.
 *
 * A new route is almost never "open": put it behind `auth:api`. Adding an entry is a security review.
 */
return [
    // API reference and framework tooling ----------------------------------------------------------
    ['method' => 'GET', 'uri' => 'api/documentation', 'kind' => 'open', 'probe' => false,
        'reason' => 'Swagger UI (l5-swagger): the API reference, no tenant data. Not called by the walker: it renders the UI.'],
    ['method' => 'GET', 'uri' => 'docs', 'kind' => 'open', 'probe' => false,
        'reason' => 'Swagger UI documentation page, no tenant data. Not called by the walker: it renders the UI.'],
    ['method' => 'GET', 'uri' => 'docs/asset/{asset}', 'kind' => 'open',
        'reason' => 'Static assets of the Swagger UI.'],
    ['method' => 'GET', 'uri' => 'api/oauth2-callback', 'kind' => 'open',
        'reason' => 'l5-swagger OAuth2 redirect page for the documentation UI; static HTML, no data.'],
    ['method' => '*', 'uri' => 'horizon/*', 'kind' => 'inline-auth',
        'reason' => 'Horizon dashboard and its API: the `viewHorizon` gate answers 403 outside local development.'],
    ['method' => '*', 'uri' => '_ignition/*', 'kind' => 'own-credentials', 'optional' => true,
        'reason' => 'Ignition debug endpoints: only registered with the dev dependencies and answer 404 unless APP_DEBUG and runnable solutions are on (development only).'],
    ['method' => 'GET', 'uri' => 'stripe-test', 'kind' => 'open', 'optional' => true,
        'reason' => 'Stripe test page, registered only in local, staging and testing; it shows a publishable (public) key.'],
    ['method' => 'GET', 'uri' => 'email', 'kind' => 'open',
        'reason' => 'Static "your email is now verified" text shown after the verification link.'],

    // Health and platform metadata -----------------------------------------------------------------
    ['method' => 'GET', 'uri' => 'api/name', 'kind' => 'open', 'reason' => 'Application name, read by the front end and by the tenant probe.'],
    ['method' => 'GET', 'uri' => 'api/events', 'kind' => 'open', 'reason' => 'Event catalogue of the API (names only).'],
    ['method' => 'GET', 'uri' => 'api/health', 'kind' => 'open', 'reason' => 'Liveness probe for load balancers.'],
    ['method' => 'GET', 'uri' => 'api/healthcheck', 'kind' => 'open', 'reason' => 'Liveness probe (legacy path).'],
    ['method' => 'GET', 'uri' => 'api/core/health-check', 'kind' => 'open', 'reason' => 'Readiness probe for orchestrators (database and cache reachable).'],
    ['method' => 'POST', 'uri' => 'api/contact', 'kind' => 'open', 'reason' => 'Contact form of the public site: honeypot and throttle, mails the private inbox.'],
    ['method' => 'POST', 'uri' => 'api/csp-report', 'kind' => 'open', 'reason' => 'Browsers post CSP violation reports without credentials; throttled, answers 204.'],
    ['method' => 'GET', 'uri' => 'api/settings', 'kind' => 'open', 'reason' => 'Public settings of the tenant (branding, feature flags) for the front end, read from the database chosen by host.'],
    ['method' => 'GET', 'uri' => 'api/settings/{group}/{key}', 'kind' => 'open', 'reason' => 'One public setting (only keys marked public are returned).'],
    ['method' => 'GET', 'uri' => 'api/config', 'kind' => 'open', 'reason' => 'Public front-end configuration of the tenant.'],
    ['method' => 'GET', 'uri' => 'api/model-fields', 'kind' => 'open', 'reason' => 'Field metadata of the registration and profile forms.'],
    ['method' => 'GET', 'uri' => 'api/meta', 'kind' => 'open', 'reason' => 'Version and capability metadata, throttled.'],
    ['method' => 'GET', 'uri' => 'api/translations', 'kind' => 'open', 'reason' => 'UI translations for the front end.'],
    ['method' => 'GET', 'uri' => 'api/lti/jwks', 'kind' => 'open', 'covered_by' => 'api/packages/lti/tests/Feature/TenantIsolationTest.php',
        'reason' => 'Public key set of the tenant (LTI 1.3 requires it to be public); every tenant publishes its own keys.'],
    ['method' => 'GET', 'uri' => '.well-known/jwks.json', 'kind' => 'open', 'covered_by' => 'api/packages/lti/tests/Feature/TenantIsolationTest.php',
        'reason' => 'Same key set at the well-known path.'],
    ['method' => 'GET', 'uri' => 'api/lti/frame-origins', 'kind' => 'open', 'reason' => 'Origins allowed to frame the LTI tool (public, from the tenant configuration).'],

    // Sign in, registration, recovery (no session exists yet) ---------------------------------------
    ['method' => 'POST', 'uri' => 'oauth/token', 'kind' => 'own-credentials', 'reason' => 'Passport token endpoint: client id and secret or user credentials travel in the body.'],
    ['method' => 'GET', 'uri' => 'oauth/authorize', 'kind' => 'open', 'reason' => 'Passport authorization screen, needs a web session; the API issues personal access tokens only, so nothing links here.'],
    ['method' => '*', 'uri' => 'oauth/*', 'kind' => 'inline-auth', 'reason' => 'Passport routes behind `auth:web` (session): refresh and approve/deny, 401 without a session.'],
    ['method' => 'POST', 'uri' => 'api/auth/device/code', 'kind' => 'open', 'reason' => 'Device authorization for the CLI: starts the flow, a signed-in user approves it at api/auth/device/requests (auth:api).'],
    ['method' => 'POST', 'uri' => 'api/auth/device/token', 'kind' => 'open', 'reason' => 'Device authorization: the CLI polls with its device code.'],
    ['method' => 'POST', 'uri' => 'api/auth/register', 'kind' => 'open', 'reason' => 'Self-registration of a new learner (no account exists yet).'],
    ['method' => 'POST', 'uri' => 'api/auth/login', 'kind' => 'open', 'reason' => 'Login with credentials, issues the token.'],
    ['method' => 'GET', 'uri' => 'api/auth/registerable-groups', 'kind' => 'open', 'reason' => 'Groups a visitor may pick when registering.'],
    ['method' => 'POST', 'uri' => 'api/auth/password/forgot', 'kind' => 'open', 'reason' => 'Password reset request (always answers the same).'],
    ['method' => 'POST', 'uri' => 'api/auth/password/reset', 'kind' => 'open', 'reason' => 'Password reset with the emailed token.'],
    ['method' => 'GET', 'uri' => 'api/auth/social/{provider}', 'kind' => 'open', 'covered_by' => 'api/packages/tenancy/tests/Integration/TenantIsolationTest.php',
        'reason' => 'Starts a social login; the state is signed with the tenant APP_KEY and stored in its database.'],
    ['method' => 'GET', 'uri' => 'api/auth/social/{provider}/callback', 'kind' => 'open', 'covered_by' => 'api/packages/tenancy/tests/Integration/TenantIsolationTest.php',
        'reason' => 'Provider redirect: the signed state is the credential, another tenant refuses it.'],
    ['method' => 'POST', 'uri' => 'api/auth/social/exchange', 'kind' => 'open', 'covered_by' => 'api/packages/tenancy/tests/Integration/TenantIsolationTest.php',
        'reason' => 'Redeems a one-time login code with the PKCE verifier; codes are per tenant.'],
    ['method' => 'POST', 'uri' => 'api/auth/social/complete/{token}', 'kind' => 'open', 'reason' => 'Completes a social registration with a one-time token.'],
    ['method' => 'GET', 'uri' => 'api/auth/email/verify/{id}/{hash}', 'kind' => 'open', 'reason' => 'E-mail verification link: the hash of the address is the credential.'],
    ['method' => 'GET', 'uri' => 'api/profile/delete/{userId}/{token}', 'kind' => 'open', 'reason' => 'Account deletion confirmation link: the emailed token is the credential. Answers 500 to a malformed token (not an isolation matter).'],

    // Public catalogue, read from the tenant database chosen by host --------------------------------
    ['method' => 'GET', 'uri' => 'api/products', 'kind' => 'open', 'reason' => 'Public product catalogue.'],
    ['method' => 'GET', 'uri' => 'api/products/{id}', 'kind' => 'open', 'reason' => 'Public product page.'],
    ['method' => 'GET', 'uri' => 'api/categories', 'kind' => 'open', 'reason' => 'Public course categories.'],
    ['method' => 'GET', 'uri' => 'api/categories/tree', 'kind' => 'open', 'reason' => 'Public course category tree.'],
    ['method' => 'GET', 'uri' => 'api/tags', 'kind' => 'open', 'reason' => 'Public tags.'],
    ['method' => 'GET', 'uri' => 'api/tags/unique', 'kind' => 'open', 'reason' => 'Public tags.'],
    ['method' => 'GET', 'uri' => 'api/tags/uniqueTags', 'kind' => 'open', 'reason' => 'Public tags (legacy path).'],
    ['method' => 'GET', 'uri' => 'api/courses', 'kind' => 'open', 'reason' => 'Public course catalogue (published courses only).'],
    ['method' => 'GET', 'uri' => 'api/courses/{course}', 'kind' => 'open', 'reason' => 'Public course page (published courses only).'],
    ['method' => 'GET', 'uri' => 'api/courses/{course}/program', 'kind' => 'open', 'reason' => 'Public course programme.'],
    ['method' => 'GET', 'uri' => 'api/courses/{course}/preview/{topic_id}', 'kind' => 'open', 'reason' => 'Free preview of a topic the author marked as preview.'],
    ['method' => 'GET', 'uri' => 'api/tutors', 'kind' => 'open', 'reason' => 'Public instructor profiles.'],
    ['method' => 'GET', 'uri' => 'api/tutors/{id}', 'kind' => 'open', 'reason' => 'Public instructor profile.'],
    ['method' => 'GET', 'uri' => 'api/pages', 'kind' => 'open', 'reason' => 'Public CMS pages.'],
    ['method' => 'GET', 'uri' => 'api/pages/{slug}', 'kind' => 'open', 'reason' => 'Public CMS page.'],
    ['method' => 'GET', 'uri' => 'api/webinars', 'kind' => 'open', 'reason' => 'Public webinar list.'],
    ['method' => 'GET', 'uri' => 'api/webinars/{id}', 'kind' => 'open', 'reason' => 'Public webinar page.'],
    ['method' => 'GET', 'uri' => 'api/stationary-events', 'kind' => 'open', 'reason' => 'Public on-site event list.'],
    ['method' => 'GET', 'uri' => 'api/stationary-events/{id}', 'kind' => 'open', 'reason' => 'Public on-site event page.'],
    ['method' => 'GET', 'uri' => 'api/consultations', 'kind' => 'open', 'reason' => 'Public consultation list.'],
    ['method' => 'GET', 'uri' => 'api/consultations/{id}', 'kind' => 'open', 'reason' => 'Public consultation page.'],
    ['method' => 'GET', 'uri' => 'api/dictionaries/{slug}/words', 'kind' => 'open', 'reason' => 'Public dictionary words (games).'],
    ['method' => 'GET', 'uri' => 'api/dictionaries/{slug}/words/categories', 'kind' => 'open', 'reason' => 'Public dictionary categories.'],
    ['method' => 'GET', 'uri' => 'api/dictionaries/{slug}/words/{id}', 'kind' => 'open', 'reason' => 'Public dictionary word.'],
    ['method' => 'GET', 'uri' => 'api/payments-gateways', 'kind' => 'open', 'reason' => 'Payment methods the tenant offers (names only).'],
    ['method' => 'GET', 'uri' => 'api/interactive/showcase', 'kind' => 'open', 'reason' => 'Public showcase of interactive content types, throttled.'],
    ['method' => 'GET', 'uri' => 'api/pdfs/fonts', 'kind' => 'open', 'reason' => 'Fonts bundled with the PDF renderer, used by the designer.'],
    ['method' => 'GET', 'uri' => 'api/pdfs/fonts/{file}', 'kind' => 'open', 'reason' => 'One bundled font file.'],
    ['method' => 'GET', 'uri' => 'api/certificates/verify/{certificateId}', 'kind' => 'own-credentials',
        'covered_by' => 'api/packages/templates-pdf/tests/Api/CertificateTenantIsolationTest.php',
        'reason' => 'Certificate verification: what the QR code leads to; the unguessable certificate id is the credential, answers 404 to an unknown one.'],
    ['method' => '*', 'uri' => 'api/images/img', 'kind' => 'open', 'reason' => 'Renders a public image from the tenant disk with a size and format (cached, throttled). Answers 500 without parameters.'],
    ['method' => 'GET', 'uri' => 'api/questionnaire/stars/{model_type_title}/{model_id}', 'kind' => 'open', 'reason' => 'Aggregate star ratings of a course or other model.'],
    ['method' => 'GET', 'uri' => 'api/questionnaire/{model_type_title}/{model_id}', 'kind' => 'open', 'reason' => 'Questionnaires assigned to a model.'],
    ['method' => 'GET', 'uri' => 'api/questionnaire/{model_type_title}/{model_id}/questions/{question_id}/answers', 'kind' => 'open', 'reason' => 'Answers the author marked as public.'],
    ['method' => 'GET', 'uri' => 'api/questionnaire/{model_type_title}/{model_id}/questions/{question_id}/stars', 'kind' => 'open', 'reason' => 'Aggregate stars of one question.'],

    // Content origin, SCORM and LRS (own tokens or unguessable ids) ---------------------------------
    ['method' => 'GET', 'uri' => 'api/content/{path}', 'kind' => 'own-credentials',
        'covered_by' => 'api/tests/Integrations/ContentOriginHeadersConfigTest.php',
        'reason' => 'Package files for the content origin: only answers the Caddy proxy that sets X-Ulams-Content-Origin (docs/content-origin.md).'],
    ['method' => 'GET', 'uri' => 'api/scorm/assets/scorm-again.min.js', 'kind' => 'open', 'reason' => 'Vendored scorm-again library, static.'],
    ['method' => 'GET', 'uri' => 'api/scorm/service-worker', 'kind' => 'open', 'reason' => 'Service worker of the legacy SCORM player, static.'],
    ['method' => 'GET', 'uri' => 'api/scorm/content/{uuid}', 'kind' => 'own-credentials', 'covered_by' => 'api/packages/scorm/tests/API/ScormContentOriginApiTest.php',
        'reason' => 'SCO data for the content-origin player: needs the SCO-scoped tracking token.'],
    ['method' => 'POST', 'uri' => 'api/scorm/content/{uuid}/track', 'kind' => 'own-credentials', 'covered_by' => 'api/packages/scorm/tests/API/ScormContentOriginApiTest.php',
        'reason' => 'SCORM tracking: needs the SCO-scoped tracking token (forgery and cross-tenant use are tested).'],
    ['method' => 'GET', 'uri' => 'api/scorm/play/{uuid}', 'kind' => 'open', 'reason' => 'Legacy player page for a SCO; the unguessable uuid is the capability.'],
    ['method' => 'GET', 'uri' => 'api/scorm/show/{uuid}', 'kind' => 'open', 'reason' => 'SCO description for the legacy player; the unguessable uuid is the capability.'],
    ['method' => 'GET', 'uri' => 'api/scorm/zip/{uuid}', 'kind' => 'open', 'reason' => 'Builds the zip of a package for the legacy player and answers true or false; the unguessable uuid is the capability, no data is returned.'],
    ['method' => 'GET', 'uri' => 'storage/scorm/{path}', 'kind' => 'open', 'reason' => 'Package files on a local SCORM disk, under unguessable uuid paths; registered only for a local disk.'],
    ['method' => 'POST', 'uri' => 'api/cmi5/fetch', 'kind' => 'own-credentials', 'reason' => 'cmi5 fetch URL: the one-time fetch token from the launch is the credential.'],
    ['method' => 'POST', 'uri' => 'api/liascript/progress/{topic}', 'kind' => 'own-credentials', 'reason' => 'LiaScript progress beacon: the topic-scoped token issued at launch (api/liascript/launches, auth:api); throttled.'],
    ['method' => 'GET', 'uri' => 'trax/api/{source}/xapi/std/about', 'kind' => 'open', 'covered_by' => 'api/packages/lrs/tests',
        'reason' => 'xAPI "about" resource: the specification makes it public, it lists versions only.'],
    ['method' => '*', 'uri' => 'trax/api/{source}/xapi/std/*', 'kind' => 'own-credentials', 'covered_by' => 'api/packages/lrs/tests',
        'reason' => 'Learning record store (xAPI): own basic or bearer credentials per statement source.'],

    // Webhooks, payment callbacks and the LTI protocol (own signatures) -----------------------------
    ['method' => 'POST', 'uri' => 'api/commerce/webhooks/{provider}', 'kind' => 'own-credentials', 'reason' => 'Commerce webhook: HMAC signature, its timestamp, the event id and the tenant channel are the credentials (ADR 0130); throttled.'],
    ['method' => 'POST', 'uri' => 'api/living-course/webhooks/{webhookId}', 'kind' => 'own-credentials', 'reason' => 'Living Course source webhook: signature with the per-source secret.'],
    ['method' => 'GET', 'uri' => 'api/living-course/oauth/{provider}/callback', 'kind' => 'own-credentials', 'reason' => 'Source provider OAuth redirect: the signed state is the credential.'],
    ['method' => 'POST', 'uri' => 'api/jitsi/recorded-video', 'kind' => 'own-credentials', 'reason' => 'Jitsi recording callback: the VerifyJitsiWebhook middleware checks the webhook before the controller runs.'],
    ['method' => '*', 'uri' => 'api/payments-gateways/callback/*', 'kind' => 'own-credentials', 'reason' => 'Payment provider return and refund callbacks: the payment is confirmed with the provider, not trusted from the request.'],
    ['method' => 'POST', 'uri' => 'api/payments-gateways/webhook/stripe', 'kind' => 'own-credentials', 'reason' => 'Stripe webhook: Stripe-Signature is verified.'],
    ['method' => '*', 'uri' => 'api/lti/platform/*', 'kind' => 'own-credentials', 'covered_by' => 'api/packages/lti/tests/Feature/TenantIsolationTest.php',
        'reason' => 'LTI 1.3 platform side (authorize, token, deep links, AGS, NRPS): the tool authenticates with its own client assertion or an LTI access token.'],
    ['method' => '*', 'uri' => 'api/lti/tool/*', 'kind' => 'own-credentials', 'covered_by' => 'api/packages/lti/tests/Feature/TenantIsolationTest.php',
        'reason' => 'LTI 1.3 tool side (login, launch, deep link): signed id_token, state and nonce; one-time codes are per tenant.'],

    // Known gaps ------------------------------------------------------------------------------------
    ['method' => 'GET', 'uri' => 'api/refresh-token', 'kind' => 'known-gap',
        'reason' => 'KNOWN GAP (security issue ulams-dev/ulams#370): Google OAuth redirect that stores a YouTube refresh token in the tenant configuration without authentication or a state check. Owner: live-streaming (packages/youtube).'],
];
