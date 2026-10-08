<?php

use \EscolaLms\Files\Http\Controllers\FileApiController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api', 'middleware' => ['auth:api']], function () {
    Route::group(['prefix' => 'admin/file'], function () {
        Route::get('list', [FileApiController::class, 'list']);
        Route::get('find', [FileApiController::class, 'findByName']);
        Route::post('upload', [FileApiController::class, 'upload']);
        Route::post('move', [FileApiController::class, 'move']);
        Route::delete('delete', [FileApiController::class, 'delete']);
    });
});
