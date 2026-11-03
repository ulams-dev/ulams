<?php

use Illuminate\Support\Facades\Route;
use Ulams\LearnerInsights\Http\Controllers\AnalyticsController;
use Ulams\LearnerInsights\Http\Controllers\ClientSignalController;
use Ulams\LearnerInsights\Http\Controllers\ImproveController;
use Ulams\LearnerInsights\Http\Controllers\MeController;
use Ulams\LearnerInsights\Http\Controllers\NudgeOpenedController;
use Ulams\LearnerInsights\Http\Controllers\PresentationController;
use Ulams\LearnerInsights\Http\Controllers\SettingsController;
use Ulams\LearnerInsights\Http\Controllers\SupportController;

// Learner endpoints: every route acts on the authenticated learner only.
Route::group([
    'prefix' => 'api/learner-insights',
    'middleware' => ['auth:api'],
], function () {
    Route::post('signals', [ClientSignalController::class, 'store'])->middleware('throttle:learner-insights-signals');
    Route::get('courses/{course}/topics/{topic}/support', [SupportController::class, 'index'])->whereNumber(['course', 'topic']);
    Route::get('courses/{course}/presentation', [PresentationController::class, 'show'])->whereNumber('course');
    Route::put('courses/{course}/presentation', [PresentationController::class, 'update'])->whereNumber('course');
    Route::post('assignments/{assignment}/feedback', [SupportController::class, 'feedback']);
    Route::post('assignments/{assignment}/hide', [SupportController::class, 'hide']);
    Route::post('assignments/{assignment}/reflection', [SupportController::class, 'reflection'])->middleware('throttle:10,1');
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
    Route::get('courses/{course}/summary', [AnalyticsController::class, 'summary'])->whereNumber('course');
    Route::get('courses/{course}/elements', [AnalyticsController::class, 'elements'])->whereNumber('course');
    Route::get('courses/{course}/learners', [AnalyticsController::class, 'learners'])->whereNumber('course');
    Route::post('courses/{course}/elements/{elementKey}/improve', [ImproveController::class, 'store'])->whereNumber('course')->where('elementKey', ImproveController::KEY);
    Route::get('learners/{user}/courses/{course}/timeline', [AnalyticsController::class, 'timeline'])->whereNumber(['user', 'course']);
    Route::post('backfill', [AnalyticsController::class, 'backfill']);
});
