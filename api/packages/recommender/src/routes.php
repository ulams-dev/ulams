<?php

use EscolaLms\Recommender\Http\Controllers\RecommenderController;
use EscolaLms\Recommender\Http\Controllers\TermAnalyticController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/admin/recommender')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::get('/course/{courseId}', [RecommenderController::class, 'course']);
        Route::get('/lesson/{lessonId}/topic', [RecommenderController::class, 'topic']);
        Route::get('/analytics/aggregated-frames/{id}', [TermAnalyticController::class, 'aggregatedFrames']);
        Route::get('/analytics/{modelType}/{modelId}', [TermAnalyticController::class, 'modelAnalytics']);
        Route::get('/analytics/{modelType}/{modelId}/{id}', [TermAnalyticController::class, 'show']);
        Route::get('/terms/{modelType}', [TermAnalyticController::class, 'index']);
    });

Route::prefix('api/recommender')->group(function () {
    Route::middleware(['auth:api'])->group(function () {
        Route::post('/meet-recordings', [RecommenderController::class, 'meetRecordings']);
        Route::post('/meet-recordings/screens', [RecommenderController::class, 'meetRecordingScreen']);
    });

    Route::middleware('verifySignature')->group(function () {
        Route::post('/aggregated-frames/save', [RecommenderController::class, 'aggregateFrameSave']);
        Route::post('/satisfaction', [TermAnalyticController::class, 'satisfaction']);
    });
});
