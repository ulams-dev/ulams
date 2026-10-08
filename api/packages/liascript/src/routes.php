<?php

use Illuminate\Support\Facades\Route;
use Ulams\LiaScript\Http\Controllers\LiaScriptController;

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
