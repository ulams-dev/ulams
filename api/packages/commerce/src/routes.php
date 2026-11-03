<?php

use Illuminate\Support\Facades\Route;
use Ulams\Commerce\Http\Controllers\CatalogueController;
use Ulams\Commerce\Http\Controllers\OrderWebhookController;

// Public: the provider (Sylius) calls it, so there is no session or token. The HMAC signature, its timestamp,
// the event id and the tenant channel are the credentials (ADR 0130); see the controller.
Route::post('api/commerce/webhooks/{provider}', [OrderWebhookController::class, 'receive'])
    ->middleware('throttle:commerce-webhook')
    ->where('provider', '[a-z0-9_-]{1,32}');

// What the tenant sells and for how much; permission commerce_prices_manage (checked in the controller).
Route::prefix('api/admin/commerce')->middleware('auth:api')->where(['id' => '[0-9]+', 'type' => '[a-z_]+'])->group(function () {
    Route::get('products', [CatalogueController::class, 'index']);
    Route::put('products/{type}/{id}', [CatalogueController::class, 'update']);
    Route::post('bundles', [CatalogueController::class, 'createBundle']);
    Route::post('access-passes', [CatalogueController::class, 'createPass']);
});
