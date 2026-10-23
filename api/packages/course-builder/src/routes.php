<?php

use Illuminate\Support\Facades\Route;
use Ulams\CourseBuilder\Http\Controllers\CompareController;
use Ulams\CourseBuilder\Http\Controllers\CourseAsCodeController;
use Ulams\CourseBuilder\Http\Controllers\CourseBuilderController;
use Ulams\CourseBuilder\Http\Controllers\EventStreamController;
use Ulams\CourseBuilder\Http\Controllers\ReleaseController;
use Ulams\CourseBuilder\Http\Controllers\VersionListController;
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
    Route::get('sessions/{session}/citations', [CourseBuilderController::class, 'citations']);
    Route::get('fragments/{fragment}', [CourseBuilderController::class, 'fragment']);

    Route::get('sessions/{session}/brief', [CourseBuilderController::class, 'brief']);
    Route::put('sessions/{session}/brief', [CourseBuilderController::class, 'updateBrief']);

    Route::post('sessions/{session}/runs', [CourseBuilderController::class, 'run']);
    Route::get('sessions/{session}/events', [EventStreamController::class, 'stream']);
    Route::get('runs/{run}', [CourseBuilderController::class, 'runStatus']);
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

    Route::post('sessions/{session}/outline', [CourseBuilderController::class, 'editOutline']);
    Route::post('sessions/{session}/elements/{element}/variants', [CourseBuilderController::class, 'variants']);
    Route::post('sessions/{session}/apply', [CourseBuilderController::class, 'apply']);
    Route::post('sessions/{session}/new-site', [CourseBuilderController::class, 'newSite']);
    Route::get('sessions/{session}/publish-check', [CourseBuilderController::class, 'publishCheck']);
    Route::post('sessions/{session}/publish', [CourseBuilderController::class, 'publish']);
    Route::get('sessions/{session}/usage', [CourseBuilderController::class, 'usage']);

    // course variants by level and role (ADR 0192)
    Route::get('sessions/{session}/variants', [CourseBuilderController::class, 'variantsIndex']);
    Route::post('sessions/{session}/variants', [CourseBuilderController::class, 'variantsStore']);
    Route::patch('variants/{variant}', [CourseBuilderController::class, 'variantUpdate']);
    Route::delete('variants/{variant}', [CourseBuilderController::class, 'variantDestroy']);
});

// Course-as-code (ADR 0160): export a course as a blueprint, import one as a proposed version. No AI gate: it works
// on installations without an AI provider. Diff, apply and publish reuse the builder's controller methods.
Route::group([
    'prefix' => 'api/admin/course-as-code',
    'middleware' => ['auth:api'],
], function () {
    Route::get('courses/{course}/blueprint', [CourseAsCodeController::class, 'export']);
    Route::post('courses', [CourseAsCodeController::class, 'create']);
    Route::post('courses/{course}/adopt', [CourseAsCodeController::class, 'adopt'])->whereNumber('course');
    Route::get('sessions/{session}', [CourseAsCodeController::class, 'session']);
    Route::post('sessions/{session}/imports', [CourseAsCodeController::class, 'import']);
    Route::post('sessions/{session}/drift', [CourseAsCodeController::class, 'absorbDrift']);
    Route::get('versions/{version}', [CourseBuilderController::class, 'version']);
    Route::get('versions/{version}/diff', [CourseBuilderController::class, 'diff']);
    Route::post('versions/{version}/approve', [CourseAsCodeController::class, 'approve']);
    Route::post('sessions/{session}/apply', [CourseBuilderController::class, 'apply']);
    Route::post('sessions/{session}/publish', [CourseBuilderController::class, 'publish']);
});

// Course versions (ADR 0192): the releases of a course and the comparison of any two of its versions, keyed by
// the LMS course id. No AI gate: it works on installations without an AI provider. Read-only; a release is written by the apply step.
Route::group([
    'prefix' => 'api/admin/course-versions',
    'middleware' => ['auth:api'],
], function () {
    Route::get('courses/{course}/releases', [ReleaseController::class, 'index'])->whereNumber('course');
    Route::get('releases/{release}', [ReleaseController::class, 'show']);
    Route::get('courses/{course}/versions', [VersionListController::class, 'index'])->whereNumber('course');
    Route::get('courses/{course}/compare', [CompareController::class, 'show'])->whereNumber('course');
});
