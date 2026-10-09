<?php

use Illuminate\Support\Facades\Route;
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
    Route::get('sessions/{session}/proposals', [ProposalsController::class, 'index']);
    Route::get('proposals/{proposal}', [ProposalsController::class, 'show']);
    Route::post('proposals/{proposal}/analyse', [ProposalsController::class, 'analyse']);
    Route::get('revisions/{revision}/changes', [SourcesController::class, 'changes']);
});
