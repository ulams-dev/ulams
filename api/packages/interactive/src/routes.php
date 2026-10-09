<?php

use Illuminate\Support\Facades\Route;
use Ulams\Interactive\Http\Controllers\InteractiveLearnerController;
use Ulams\Interactive\Http\Controllers\InteractivePackageController;

Route::group(['prefix' => 'api/admin/interactive', 'middleware' => ['auth:api'], 'where' => ['id' => '[0-9]+']], function () {
    Route::get('/', [InteractivePackageController::class, 'index']);
    Route::post('/', [InteractivePackageController::class, 'store']);
    Route::get('{id}', [InteractivePackageController::class, 'show']);
    Route::put('{id}', [InteractivePackageController::class, 'update']);
    Route::delete('{id}', [InteractivePackageController::class, 'destroy']);
    Route::get('{id}/versions', [InteractivePackageController::class, 'versions']);
    Route::post('{id}/versions', [InteractivePackageController::class, 'addVersion']);
    Route::get('{id}/preview', [InteractivePackageController::class, 'preview']);
});

// Learners: the entry file is served from the content origin; the page forwards the bridge's events
// with the learner's own session (no token in the frame)
Route::post('api/interactive/launches/{topic}', [InteractiveLearnerController::class, 'launch'])->whereNumber('topic')->middleware('auth:api');
Route::post('api/interactive/topics/{topic}/events', [InteractiveLearnerController::class, 'events'])->whereNumber('topic')->middleware(['auth:api', 'throttle:60,1']);
