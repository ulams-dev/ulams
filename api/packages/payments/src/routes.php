<?php

use Ulams\Payments\Http\Controllers\Admin\PaymentsController as PaymentsAdminController;
use Ulams\Payments\Http\Controllers\GatewayController;
use Ulams\Payments\Http\Controllers\PaymentsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {
    Route::get('/payments-gateways', [GatewayController::class, 'index']);
    Route::any('/payments-gateways/callback/{payment}', [GatewayController::class, 'callback'])->name('payments-gateway-callback');
    Route::post('/payments-gateways/webhook/stripe', [GatewayController::class, 'stripeWebhook'])->name('payments-gateway-stripe-webhook');
    Route::any('/payments-gateways/callback/refund/{payment}', [GatewayController::class, 'callbackRefund'])->name('payments-gateway-refund-callback');

    Route::group(['prefix' => 'admin/payments', 'middleware' => ['auth:api']], function () {
        Route::get('/export', [PaymentsAdminController::class, 'export']);
        Route::get('/{payment}', [PaymentsAdminController::class, 'show']);
        Route::get('/', [PaymentsAdminController::class, 'search']);
    });

    Route::prefix('payments')->group(function () {
        Route::get('/{payment}', [PaymentsController::class, 'show']);
        Route::get('/', [PaymentsController::class, 'search']);
    });
});
