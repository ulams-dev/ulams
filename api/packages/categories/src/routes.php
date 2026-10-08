<?php

use EscolaLms\Categories\Http\Controllers\CategoryAPIController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api', 'middleware' => [SubstituteBindings::class]], function () {

    Route::group(['prefix' => '/categories'], function () {
        Route::get('/', [CategoryAPIController::class, 'index']);
        Route::get('tree', [CategoryAPIController::class, 'tree']);
    });

    Route::group(['prefix' => '/admin/categories', 'middleware' => ['auth:api']], function () {
        Route::get('/', [CategoryAPIController::class, 'index']);
        Route::post('sort', [CategoryAPIController::class, 'sort']);
        Route::get('tree', [CategoryAPIController::class, 'tree']);
        Route::get('{category}', [CategoryAPIController::class, 'show']);
        Route::post('/', [CategoryAPIController::class, 'create']);
        Route::delete('{category}', [CategoryAPIController::class, 'delete']);
        Route::post('{category}', [CategoryAPIController::class, 'update']);
    });
});
