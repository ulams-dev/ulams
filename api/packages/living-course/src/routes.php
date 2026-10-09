<?php

use Illuminate\Support\Facades\Route;
use Ulams\LivingCourse\Http\Controllers\SourcesController;

// Authors and admins (session policy). Detection and staleness work with AI disabled; only the
// analysis endpoints answer 503 then.
Route::group([
    'prefix' => 'api/admin/living-course',
    'middleware' => ['auth:api'],
], function () {
    Route::get('sessions/{session}/sources', [SourcesController::class, 'index']);
    Route::get('sources/{source}/revisions', [SourcesController::class, 'revisions']);
    Route::get('revisions/{revision}', [SourcesController::class, 'revision']);
});
