<?php

// admin endpoints
use Ulams\CsvUsers\Http\Controllers\CsvGroupAPIController;
use Ulams\CsvUsers\Http\Controllers\CsvUserAPIController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin/csv'], function () {
    Route::prefix('users')->group(function () {
        Route::get(null, [CsvUserAPIController::class, 'export']);
        Route::post(null , [CsvUserAPIController::class, 'import']);
    });
    Route::prefix('groups')->group(function () {
        Route::get('{group}', [CsvGroupAPIController::class, 'export']);
        Route::post(null , [CsvGroupAPIController::class, 'import']);
    });
});
