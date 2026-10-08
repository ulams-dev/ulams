<?php

use EscolaLms\Vouchers\Http\Controllers\VouchersAdminApiController;
use EscolaLms\Vouchers\Http\Controllers\VouchersApiController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {
    Route::middleware('auth:api')->prefix('admin')->group(function () {
        Route::get('/vouchers', [VouchersAdminApiController::class, 'index']);
        Route::post('/vouchers', [VouchersAdminApiController::class, 'create']);
        Route::get('/vouchers/{id}', [VouchersAdminApiController::class, 'read']);
        Route::put('/vouchers/{id}', [VouchersAdminApiController::class, 'update']);
        Route::patch('/vouchers/{id}', [VouchersAdminApiController::class, 'update']);
        Route::delete('/vouchers/{id}', [VouchersAdminApiController::class, 'delete']);
    });

    Route::post('/cart/voucher/', [VouchersApiController::class, 'apply']);
    Route::delete('/cart/voucher/', [VouchersApiController::class, 'unapply']);
});
