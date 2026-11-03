<?php

use Illuminate\Support\Facades\Route;
use Ulams\CodeExercises\Http\Controllers\CodeExerciseController;

// Learners: the exercise and the runner's address, the results the lesson page reports, their own history
Route::group(['prefix' => 'api/code-exercises', 'middleware' => ['auth:api'], 'where' => ['topic' => '[0-9]+']], function () {
    Route::post('launches/{topic}', [CodeExerciseController::class, 'launch']);
    Route::post('topics/{topic}/attempts', [CodeExerciseController::class, 'store'])->middleware('throttle:code-exercise-attempts');
    Route::get('topics/{topic}/attempts', [CodeExerciseController::class, 'mine']);
});

// Authors and teachers: preview without tracking, and every learner's attempts
Route::group(['prefix' => 'api/admin/code-exercises', 'middleware' => ['auth:api'], 'where' => ['topic' => '[0-9]+']], function () {
    Route::get('topics/{topic}/preview', [CodeExerciseController::class, 'preview']);
    Route::get('topics/{topic}/attempts', [CodeExerciseController::class, 'report']);
});
