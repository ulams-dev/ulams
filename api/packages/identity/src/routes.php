<?php

use Illuminate\Support\Facades\Route;
use Ulams\Identity\Http\Controllers\Admin\AuditController;
use Ulams\Identity\Http\Controllers\Admin\ProviderController;
use Ulams\Identity\Http\Controllers\SsoController;

// Public sign-in (throttled per IP by `ulams-identity`). Skipped when IDENTITY_ENABLED is false.
if (config('ulams_identity.enabled')) {
    Route::prefix('api/identity')->middleware('throttle:ulams-identity')->group(function (): void {
        Route::get('providers', [SsoController::class, 'providers']);
        Route::get('sso/{slug}/start', [SsoController::class, 'start']);
        Route::get('oidc/{slug}/callback', [SsoController::class, 'callback']);
        Route::post('sso/exchange', [SsoController::class, 'exchange']);
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
        Route::get('audit', [AuditController::class, 'index']);
        Route::get('audit/verify', [AuditController::class, 'verify']);
    });
