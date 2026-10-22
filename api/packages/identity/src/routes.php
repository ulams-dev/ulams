<?php

use Illuminate\Support\Facades\Route;
use Ulams\Identity\Http\Controllers\Admin\AuditController;
use Ulams\Identity\Http\Controllers\Admin\ProviderController;
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
        Route::get('audit', [AuditController::class, 'index']);
        Route::get('audit/verify', [AuditController::class, 'verify']);
    });
