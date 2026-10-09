<?php

use Illuminate\Support\Facades\Route;
use Ulams\Tenancy\Http\Controllers\PlatformTenantsController;
use Ulams\Tenancy\Http\Middleware\PlatformApiOnly;

Route::group(['prefix' => 'api/platform', 'middleware' => [PlatformApiOnly::class, 'auth:api']], function () {
    Route::post('tenants', [PlatformTenantsController::class, 'store']);
    Route::get('tenants/{slug}', [PlatformTenantsController::class, 'show']);
});
