<?php

use EscolaLms\Notifications\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/notifications'], function () {
    Route::get('/', [NotificationsController::class, 'user']);
    Route::get('/events', [NotificationsController::class, 'events']);
    Route::post('/{notification}/read', [NotificationsController::class, 'read']);
    Route::post('/read-all', [NotificationsController::class, 'readAll']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin/notifications'], function () {
    Route::get('/events', [NotificationsController::class, 'events']);
    Route::post('/{notification}/read', [NotificationsController::class, 'read']);
    Route::get('/', [NotificationsController::class, 'user']);
    Route::get('/all', [NotificationsController::class, 'index']);
    Route::get('/{user}', [NotificationsController::class, 'user'])->where(['user' => '[0-9]+']);
    Route::post('/read-all', [NotificationsController::class, 'readAll']);
});
