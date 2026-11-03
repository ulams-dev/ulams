<?php

use Illuminate\Support\Facades\Route;
use Ulams\Webhooks\Http\Controllers\WebhooksController;

// Admin only (permission webhook_manage). Rate limits: `ulams-webhooks` for everything,
// `ulams-webhooks-send` on top for the calls that contact a third party.
Route::prefix('api/admin/webhooks')
    ->middleware(['auth:api', 'throttle:ulams-webhooks'])
    ->group(function (): void {
        Route::get('events', [WebhooksController::class, 'events']);
        Route::get('endpoints', [WebhooksController::class, 'index']);
        Route::post('endpoints', [WebhooksController::class, 'store']);
        Route::get('endpoints/{endpoint}', [WebhooksController::class, 'show']);
        Route::put('endpoints/{endpoint}', [WebhooksController::class, 'update']);
        Route::delete('endpoints/{endpoint}', [WebhooksController::class, 'destroy']);
        Route::post('endpoints/{endpoint}/rotate-secret', [WebhooksController::class, 'rotateSecret']);
        Route::post('endpoints/{endpoint}/test', [WebhooksController::class, 'test'])->middleware('throttle:ulams-webhooks-send');
        Route::get('deliveries', [WebhooksController::class, 'deliveries']);
        Route::get('deliveries/{delivery}', [WebhooksController::class, 'showDelivery']);
        Route::post('deliveries/{delivery}/replay', [WebhooksController::class, 'replay'])->middleware('throttle:ulams-webhooks-send');
    });
