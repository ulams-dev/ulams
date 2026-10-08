<?php

use EscolaLms\TopicTypeProject\Http\Controllers\Admin\ProjectSolutionApiAdminController;
use EscolaLms\TopicTypeProject\Http\Controllers\ProjectSolutionApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:api'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::prefix('topic-project-solutions')->group(function () {
            Route::get(null, [ProjectSolutionApiAdminController::class, 'index']);
            Route::get('{id}', [ProjectSolutionApiAdminController::class, 'read']);
            Route::patch('{id}/feedback', [ProjectSolutionApiAdminController::class, 'feedback']);
            Route::patch('{id}/grade', [ProjectSolutionApiAdminController::class, 'grade']);
            Route::delete('{id}', [ProjectSolutionApiAdminController::class, 'delete']);
        });
    });

    Route::prefix('topic-project-solutions')->group(function () {
        Route::get(null, [ProjectSolutionApiController::class, 'index']);
        Route::get('{id}', [ProjectSolutionApiController::class, 'read']);
        Route::post(null, [ProjectSolutionApiController::class, 'create']);
        Route::delete('{id}', [ProjectSolutionApiController::class, 'delete']);
    });
});
