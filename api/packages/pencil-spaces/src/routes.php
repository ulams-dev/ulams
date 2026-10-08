<?php

use EscolaLms\PencilSpaces\Http\Controllers\PencilSpaceApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:api'])->group(function () {
    Route::prefix('pencil-spaces')->group(function () {
        Route::post('login', [PencilSpaceApiController::class, 'login']);
    });
});
