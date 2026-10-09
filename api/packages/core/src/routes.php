<?php

use Ulams\Core\Http\Controllers\CoreController;
use Ulams\Core\Http\Controllers\CspReportController;
use Ulams\Core\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/core'], function () {
    Route::get('/packages', [CoreController::class, 'packages']);
});

Route::prefix('api/core')->group(function () {
    Route::get('health-check', [HealthCheckController::class, 'healthCheck']);
});

// Content Security Policy reports of browsers (ADR 0044): public, rate limited, tiny, aggregated.
Route::post('api/csp-report', [CspReportController::class, 'store'])->middleware('throttle:60,1');
Route::get('api/admin/csp-reports', [CspReportController::class, 'index'])->middleware('auth:api');
