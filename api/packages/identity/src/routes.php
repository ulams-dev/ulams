<?php

use Illuminate\Support\Facades\Route;
use Ulams\Identity\Http\Controllers\Admin\AuditController;
use Ulams\Identity\Http\Controllers\Admin\ProviderController;
use Ulams\Identity\Http\Controllers\Admin\ScimTokenController;
use Ulams\Identity\Http\Controllers\Scim\ScimDiscoveryController;
use Ulams\Identity\Http\Controllers\Scim\ScimUsersController;
use Ulams\Identity\Http\Middleware\ScimAuthenticate;
use Ulams\Identity\Http\Controllers\SamlController;
use Ulams\Identity\Http\Controllers\SsoController;

// Public sign-in (throttled per IP by `ulams-identity`). Skipped when IDENTITY_ENABLED is false.
if (config('ulams_identity.enabled')) {
    Route::prefix('api/identity')->middleware('throttle:ulams-identity')->group(function (): void {
        Route::get('providers', [SsoController::class, 'providers']);
        Route::get('sso/{slug}/start', [SsoController::class, 'start']);
        Route::get('oidc/{slug}/callback', [SsoController::class, 'callback']);
        Route::post('sso/exchange', [SsoController::class, 'exchange']);
        // SAML 2.0: SP metadata, assertion consumer service and single logout (ACS and SLO are exempt from the
        // trusted-origin check in ulams.core.security.origin_exempt: the IdP posts from its own origin)
        Route::get('saml/{slug}/metadata', [SamlController::class, 'metadata']);
        Route::post('saml/{slug}/acs', [SamlController::class, 'acs']);
        Route::match(['get', 'post'], 'saml/{slug}/slo', [SamlController::class, 'slo']);
    });
    // Signing out from this academy and starting single logout at the IdP needs the user's token
    Route::prefix('api/identity')->middleware(['auth:api', 'throttle:ulams-identity'])->group(function (): void {
        Route::post('saml/{slug}/logout', [SamlController::class, 'logout']);
    });
}

// Admin (permissions identity_provider_manage, identity_audit_read; token scope identity:*), throttled per user.
Route::prefix('api/admin/identity')
    ->middleware(['auth:api', 'throttle:ulams-identity-admin'])
    ->group(function (): void {
        Route::get('providers', [ProviderController::class, 'index']);
        Route::post('providers', [ProviderController::class, 'store']);
        Route::get('providers/{provider}', [ProviderController::class, 'show']);
        Route::put('providers/{provider}', [ProviderController::class, 'update']);
        Route::delete('providers/{provider}', [ProviderController::class, 'destroy']);
        Route::post('providers/{provider}/metadata/refresh', [ProviderController::class, 'refreshMetadata']);
        Route::post('providers/{provider}/sp-keys/rotate', [ProviderController::class, 'rotateSpKeys']);
        // SCIM tokens of a provider (permission identity_scim_manage)
        Route::get('providers/{provider}/scim-tokens', [ScimTokenController::class, 'index']);
        Route::post('providers/{provider}/scim-tokens', [ScimTokenController::class, 'store']);
        Route::delete('providers/{provider}/scim-tokens/{token}', [ScimTokenController::class, 'destroy']);
        Route::get('audit', [AuditController::class, 'index']);
        Route::get('audit/verify', [AuditController::class, 'verify']);
    });

// SCIM 2.0 server (RFC 7643, 7644). Authenticated by ScimAuthenticate: only a SCIM token of one provider, which answers
// in the SCIM error format and limits requests per token. Skipped when IDENTITY_ENABLED is false.
if (config('ulams_identity.enabled')) {
    // ScimAuthenticate runs first and answers in the SCIM format; `auth:api` stays on the route so the route walk of
    // TenantIsolationCoverageTest (every route needs a token or is listed as public) holds for these too.
    Route::prefix('api/scim/v2')->middleware([ScimAuthenticate::class, 'auth:api'])->group(function (): void {
        Route::get('ServiceProviderConfig', [ScimDiscoveryController::class, 'config']);
        Route::get('ResourceTypes', [ScimDiscoveryController::class, 'resourceTypes']);
        Route::get('ResourceTypes/{id}', [ScimDiscoveryController::class, 'resourceType']);
        Route::get('Schemas', [ScimDiscoveryController::class, 'schemas']);
        Route::get('Schemas/{id}', [ScimDiscoveryController::class, 'schema'])->where('id', '.*');

        Route::get('Users', [ScimUsersController::class, 'index']);
        Route::post('Users', [ScimUsersController::class, 'store']);
        Route::post('Users/.search', [ScimUsersController::class, 'search']);
        Route::get('Users/{id}', [ScimUsersController::class, 'show']);
        Route::put('Users/{id}', [ScimUsersController::class, 'replace']);
        Route::patch('Users/{id}', [ScimUsersController::class, 'patch']);
        Route::delete('Users/{id}', [ScimUsersController::class, 'destroy']);
    });
}
