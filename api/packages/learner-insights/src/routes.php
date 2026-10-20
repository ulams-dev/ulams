<?php

use Illuminate\Support\Facades\Route;
use Ulams\LearnerInsights\Http\Controllers\ClientSignalController;
use Ulams\LearnerInsights\Http\Controllers\MeController;
use Ulams\LearnerInsights\Http\Controllers\NudgeOpenedController;
use Ulams\LearnerInsights\Http\Controllers\SettingsController;

// Learner endpoints: every route acts on the authenticated learner only.
Route::group([
    'prefix' => 'api/learner-insights',
    'middleware' => ['auth:api'],
], function () {
    Route::post('signals', [ClientSignalController::class, 'store'])->middleware('throttle:learner-insights-signals');
    Route::post('nudges/opened', [NudgeOpenedController::class, 'store']);
    Route::get('me', [MeController::class, 'show']);
    Route::put('me/settings', [MeController::class, 'updateSettings']);
    Route::get('me/export', [MeController::class, 'export']);
    Route::delete('me', [MeController::class, 'erase']);
});

// Admin: thresholds. Permission checks live in the controller and the form request.
Route::group([
    'prefix' => 'api/admin/learner-insights',
    'middleware' => ['auth:api'],
], function () {
    Route::get('settings', [SettingsController::class, 'tenant']);
    Route::get('courses/{course}/settings', [SettingsController::class, 'showCourse'])->whereNumber('course');
    Route::put('courses/{course}/settings', [SettingsController::class, 'updateCourse'])->whereNumber('course');
});
