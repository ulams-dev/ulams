<?php

use Illuminate\Support\Facades\Route;
use Ulams\Tenancy\Http\Controllers\PlatformOperationController;
use Ulams\Tenancy\Http\Controllers\PlatformTenantController;
use Ulams\Tenancy\Http\Middleware\EnsurePlatformApi;

// The platform tenant API (ADR 0078): off by default (TENANCY_PLATFORM_API), platform hosts only.
// EnsurePlatformApi answers 404 first, so nothing about the routes shows when it is off.
Route::group([
    'prefix' => 'api/platform',
    'middleware' => [EnsurePlatformApi::class, 'auth:api', 'throttle:ulams-platform'],
], function () {
    Route::get('tenants', [PlatformTenantController::class, 'index']);
    Route::post('tenants', [PlatformTenantController::class, 'store']);
    Route::get('tenants/{slug}', [PlatformTenantController::class, 'show'])->where('slug', '[a-z0-9]{2,30}');
    Route::patch('tenants/{slug}/env', [PlatformTenantController::class, 'env'])->where('slug', '[a-z0-9]{2,30}');
    Route::delete('tenants/{slug}', [PlatformTenantController::class, 'destroy'])->where('slug', '[a-z0-9]{2,30}');
    Route::get('operations', [PlatformOperationController::class, 'index']);
    Route::get('operations/{id}', [PlatformOperationController::class, 'show'])->where('id', '[0-9a-z]{26}');
});
