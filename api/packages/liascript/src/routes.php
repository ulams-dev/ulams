<?php

use Illuminate\Support\Facades\Route;
use Ulams\LiaScript\Http\Controllers\LiaScriptController;
use Ulams\LiaScript\Http\Controllers\LiaScriptLearnerController;

Route::group(['prefix' => 'api/admin/liascript', 'middleware' => ['auth:api'], 'where' => ['id' => '[0-9]+', 'version' => '[0-9]+']], function () {
    Route::get('/', [LiaScriptController::class, 'index']);
    Route::post('/', [LiaScriptController::class, 'store']);
    Route::get('{id}', [LiaScriptController::class, 'show']);
    Route::put('{id}', [LiaScriptController::class, 'update']);
    Route::delete('{id}', [LiaScriptController::class, 'destroy']);
    Route::get('{id}/source', [LiaScriptController::class, 'source']);
    Route::get('{id}/versions', [LiaScriptController::class, 'versions']);
    Route::post('{id}/versions', [LiaScriptController::class, 'addVersion']);
    Route::post('{id}/versions/{version}/restore', [LiaScriptController::class, 'restore']);
});

// Learners: play on the content origin; the player reports progress with a topic-scoped token
Route::post('api/liascript/launches/{topic}', [LiaScriptLearnerController::class, 'launch'])->whereNumber('topic')->middleware('auth:api');
Route::post('api/liascript/progress/{topic}', [LiaScriptLearnerController::class, 'progress'])->whereNumber('topic')->middleware('throttle:120,1');
