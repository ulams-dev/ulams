<?php

use EscolaLms\Reports\Http\Controllers\Admin\ReportsController;
use EscolaLms\Reports\Http\Controllers\Admin\StatsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {
    Route::middleware('auth:api')->prefix('admin')->group(function () {
        Route::group(['prefix' => 'reports'], function () {
            Route::get('/metrics', [ReportsController::class, 'metrics']);
            Route::get('/report', [ReportsController::class, 'report']);
            Route::get('/available-for-user', [ReportsController::class, 'availableForUser']);
        });
        Route::group(['prefix' => 'stats'], function () {
            Route::get('/available', [StatsController::class, 'available']);
            Route::get('/course/{course_id}', [StatsController::class, 'course']);
            Route::get('/course/{course_id}/export', [StatsController::class, 'courseExport']);
            Route::post('/course/{course_id}/import', [StatsController::class, 'courseImport']);
            Route::get('/cart', [StatsController::class, 'cart']);
            Route::get('/date-range', [StatsController::class, 'dateRange']);
            Route::get('/topic/{topic_id}', [StatsController::class, 'topic']);
            Route::get('/topic/{topic_id}/export', [StatsController::class, 'topicExport']);
        });
    });
});
