<?php

use EscolaLms\BulkNotifications\Http\Controllers\BulkNotificationController;
use EscolaLms\BulkNotifications\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::prefix('notifications/tokens')->group(function () {
            Route::post('/', [DeviceTokenController::class, 'create']);
        });

        Route::prefix('admin/bulk-notifications')->group(function () {
            Route::get('/', [BulkNotificationController::class, 'list']);
            Route::post('send', [BulkNotificationController::class, 'send']);
            Route::post('send/multicast', [BulkNotificationController::class, 'sendMulticast']);
        });
    });
