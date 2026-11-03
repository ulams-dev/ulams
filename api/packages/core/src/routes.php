<?php

use Ulams\Core\Http\Controllers\CoreController;
use Ulams\Core\Http\Controllers\CspReportController;
use Ulams\Core\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;
use Ulams\Core\Http\Controllers\LivenessController;

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/core'], function () {
    Route::get('/packages', [CoreController::class, 'packages']);
});

Route::prefix('api/core')->group(function () {
    Route::get('health-check', [HealthCheckController::class, 'healthCheck']);
});

// Content Security Policy reports of browsers (ADR 0044): public, rate limited, tiny, aggregated.
Route::post('api/csp-report', [CspReportController::class, 'store'])->middleware('throttle:60,1');
Route::get('api/admin/csp-reports', [CspReportController::class, 'index'])->middleware('auth:api');

// Liveness: the process answers. No database, cache or storage is touched, so a dependency outage never makes an
// orchestrator restart healthy web processes. Readiness is /api/health (docs/operators/monitoring).
Route::get('up', LivenessController::class);
