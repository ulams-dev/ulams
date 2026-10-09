<?php

use Illuminate\Support\Facades\Route;
use Ulams\LivingCourse\Http\Controllers\AuditController;
use Ulams\LivingCourse\Http\Controllers\ConnectionsController;
use Ulams\LivingCourse\Http\Controllers\NoticesController;
use Ulams\LivingCourse\Http\Controllers\ProposalsController;
use Ulams\LivingCourse\Http\Controllers\SourcesController;
use Ulams\LivingCourse\Http\Controllers\StalenessController;

// Authors and admins (session policy). Detection and staleness work with AI disabled; only the
// analysis endpoints answer 503 then.
Route::group([
    'prefix' => 'api/admin/living-course',
    'middleware' => ['auth:api'],
], function () {
    Route::get('sessions/{session}/sources', [SourcesController::class, 'index']);
    Route::get('sources/{source}/revisions', [SourcesController::class, 'revisions']);
    Route::post('sources/{source}/revisions', [SourcesController::class, 'upload']);
    Route::get('revisions/{revision}', [SourcesController::class, 'revision']);
    Route::get('sessions/{session}/staleness', [StalenessController::class, 'show']);
    Route::get('sessions/{session}/audit', [AuditController::class, 'index']);
    Route::get('sessions/{session}/audit/export', [AuditController::class, 'export']);
    Route::get('sessions/{session}/audit/verify', [AuditController::class, 'verify']);
    Route::get('audit/export', [AuditController::class, 'exportAll']);
    Route::get('audit/verify', [AuditController::class, 'verifyAll']);
    Route::get('sessions/{session}/proposals', [ProposalsController::class, 'index']);
    Route::get('proposals/{proposal}', [ProposalsController::class, 'show']);
    Route::post('proposals/{proposal}/analyse', [ProposalsController::class, 'analyse']);
    Route::post('proposals/{proposal}/items/{item}/accept', [ProposalsController::class, 'accept']);
    Route::post('proposals/{proposal}/items/{item}/reject', [ProposalsController::class, 'reject']);
    Route::post('proposals/{proposal}/items/{item}/reset', [ProposalsController::class, 'reset']);
    Route::post('proposals/{proposal}/items/{item}/regenerate', [ProposalsController::class, 'regenerate']);
    Route::post('proposals/{proposal}/reanalyse', [ProposalsController::class, 'reanalyse']);
    Route::put('proposals/{proposal}/learner-note', [ProposalsController::class, 'learnerNote']);
    Route::put('connections/{connection}', [ConnectionsController::class, 'update']);
    Route::delete('connections/{connection}', [ConnectionsController::class, 'destroy']);
    Route::post('proposals/{proposal}/apply', [ProposalsController::class, 'apply']);
    Route::post('proposals/{proposal}/accept-all', [ProposalsController::class, 'acceptAll']);
    Route::post('proposals/{proposal}/reject', [ProposalsController::class, 'rejectAll']);
    Route::get('revisions/{revision}/changes', [SourcesController::class, 'changes']);
});

// Learners: their own notices and the opt-in "an update is under review" marker.
Route::group([
    'prefix' => 'api/living-course',
    'middleware' => ['auth:api'],
], function () {
    Route::get('courses/{course}/notices', [NoticesController::class, 'index'])->whereNumber('course');
    Route::post('notices/{notice}/dismiss', [NoticesController::class, 'dismiss'])->whereNumber('notice');
    Route::get('courses/{course}/freshness', [NoticesController::class, 'freshness'])->whereNumber('course');
});
