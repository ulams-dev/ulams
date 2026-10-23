<?php

use Illuminate\Support\Facades\Route;
use Ulams\Tenancy\Http\Controllers\EntryPointController;
use Ulams\Tenancy\Http\Controllers\EntryPointResolveController;
use Ulams\Tenancy\Http\Controllers\PlatformOperationController;
use Ulams\Tenancy\Http\Controllers\PlatformTenantController;
use Ulams\Tenancy\Http\Middleware\EnsurePlatformApi;
use Ulams\Tenancy\Http\Middleware\EnsurePlatformHost;
use Ulams\Tenancy\Http\Middleware\EnsureTenantApi;

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

// Branded entry points of this academy (ADR 0195): tenant hosts only (permission entry_points_manage, token scope
// settings:*). `audit` is declared before the id route so it is not read as an id.
Route::group([
    'prefix' => 'api/admin/entry-points',
    'middleware' => ['auth:api', 'throttle:ulams-entry-points-admin', EnsureTenantApi::class],
], function () {
    Route::get('/', [EntryPointController::class, 'index']);
    Route::post('/', [EntryPointController::class, 'store']);
    Route::get('audit', [EntryPointController::class, 'audit']);
    Route::get('{entryPoint}', [EntryPointController::class, 'show'])->where('entryPoint', '[0-9a-z]{26}');
    Route::patch('{entryPoint}', [EntryPointController::class, 'update'])->where('entryPoint', '[0-9a-z]{26}');
    Route::delete('{entryPoint}', [EntryPointController::class, 'destroy'])->where('entryPoint', '[0-9a-z]{26}');
});

// Public lookup the learner front makes for a host it does not know (ADR 0195): platform hosts only, no switch, no
// credentials, throttled per address. Declared outside the platform tenant API group on purpose.
Route::get('api/platform/entry-points/resolve', [EntryPointResolveController::class, 'show'])
    ->middleware([EnsurePlatformHost::class, 'throttle:ulams-entry-resolve']);
