<?php

use Illuminate\Support\Facades\Route;
use Ulams\CourseBuilder\Http\Controllers\CourseBuilderController;
use Ulams\CourseBuilder\Http\Controllers\EventStreamController;
use Ulams\CourseBuilder\Http\Middleware\EnsureAiEnabled;

Route::group([
    'prefix' => 'api/admin/course-builder',
    'middleware' => ['auth:api', EnsureAiEnabled::class],
], function () {
    Route::get('sessions', [CourseBuilderController::class, 'index']);
    Route::post('sessions', [CourseBuilderController::class, 'store']);
    Route::get('sessions/{session}', [CourseBuilderController::class, 'show']);
    Route::delete('sessions/{session}', [CourseBuilderController::class, 'destroy']);

    Route::post('sessions/{session}/sources', [CourseBuilderController::class, 'upload']);
    Route::get('sessions/{session}/sources/{source}', [CourseBuilderController::class, 'source']);
    Route::get('fragments/{fragment}', [CourseBuilderController::class, 'fragment']);

    Route::get('sessions/{session}/brief', [CourseBuilderController::class, 'brief']);
    Route::put('sessions/{session}/brief', [CourseBuilderController::class, 'updateBrief']);

    Route::post('sessions/{session}/runs', [CourseBuilderController::class, 'run']);
    Route::get('sessions/{session}/events', [EventStreamController::class, 'stream']);
    Route::post('runs/{run}/cancel', [CourseBuilderController::class, 'cancel']);
    Route::post('runs/{run}/steps/{step}/retry', [CourseBuilderController::class, 'retryStep']);

    Route::get('sessions/{session}/versions', [CourseBuilderController::class, 'versions']);
    Route::get('versions/{version}', [CourseBuilderController::class, 'version']);
    Route::get('versions/{version}/diff', [CourseBuilderController::class, 'diff']);
    Route::post('versions/{version}/approve', [CourseBuilderController::class, 'approve']);
    Route::post('versions/{version}/reject', [CourseBuilderController::class, 'reject']);
    Route::post('versions/{version}/restore', [CourseBuilderController::class, 'restore']);
    Route::post('sessions/{session}/undo', [CourseBuilderController::class, 'undo']);
    Route::post('sessions/{session}/redo', [CourseBuilderController::class, 'redo']);

    Route::post('sessions/{session}/apply', [CourseBuilderController::class, 'apply']);
    Route::post('sessions/{session}/publish', [CourseBuilderController::class, 'publish']);
    Route::get('sessions/{session}/usage', [CourseBuilderController::class, 'usage']);
});
