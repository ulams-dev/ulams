<?php

use EscolaLms\Tasks\Http\Controllers\AdminTaskController;
use EscolaLms\Tasks\Http\Controllers\AdminTaskNoteController;
use EscolaLms\Tasks\Http\Controllers\TaskController;
use EscolaLms\Tasks\Http\Controllers\TaskNoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->middleware(['auth:api'])
    ->group(function () {
        Route::prefix('admin/tasks')->group(function () {
            Route::post(null, [AdminTaskController::class, 'create']);
            Route::patch('{id}', [AdminTaskController::class, 'update']);
            Route::delete('{id}', [AdminTaskController::class, 'delete']);
            Route::post('complete/{id}', [AdminTaskController::class, 'complete']);
            Route::post('incomplete/{id}', [AdminTaskController::class, 'incomplete']);
            Route::get('', [AdminTaskController::class, 'findAll']);
            Route::get('{id}', [AdminTaskController::class, 'find']);

            Route::prefix('notes')->group(function () {
                Route::post(null, [AdminTaskNoteController::class, 'create']);
                Route::patch('{id}', [AdminTaskNoteController::class, 'update']);
                Route::delete('{id}', [AdminTaskNoteController::class, 'delete']);
            });
        });

        Route::prefix('tasks')->group(function () {
            Route::post(null, [TaskController::class, 'create']);
            Route::patch('{id}', [TaskController::class, 'update']);
            Route::delete('{id}', [TaskController::class, 'delete']);
            Route::post('complete/{id}', [TaskController::class, 'complete']);
            Route::post('incomplete/{id}', [TaskController::class, 'incomplete']);
            Route::get('', [TaskController::class, 'findAll']);
            Route::get('{id}', [TaskController::class, 'find']);

            Route::prefix('notes')->group(function () {
                Route::post(null, [TaskNoteController::class, 'create']);
                Route::patch('{id}', [TaskNoteController::class, 'update']);
                Route::delete('{id}', [TaskNoteController::class, 'delete']);
            });
        });
    });
