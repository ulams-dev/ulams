<?php

use EscolaLms\Dictionaries\Http\Controllers\DictionaryAccessAdminApiController;
use EscolaLms\Dictionaries\Http\Controllers\DictionaryAdminApiController;
use EscolaLms\Dictionaries\Http\Controllers\DictionaryAccessApiController;
use EscolaLms\Dictionaries\Http\Controllers\DictionaryWordAdminApiController;
use EscolaLms\Dictionaries\Http\Controllers\DictionaryWordApiController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin/dictionaries'], function () {
    Route::get('/', [DictionaryAdminApiController::class, 'index']);
    Route::post('/', [DictionaryAdminApiController::class, 'store']);
    Route::put('{id}', [DictionaryAdminApiController::class, 'update']);
    Route::get('{id}', [DictionaryAdminApiController::class, 'show']);
    Route::delete('{id}', [DictionaryAdminApiController::class, 'delete']);

    Route::group(['prefix' => '{id}/access'], function () {
       Route::get('/', [DictionaryAccessAdminApiController::class, 'index']);
       Route::post('/', [DictionaryAccessAdminApiController::class, 'set']);
    });
});

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin/dictionary-words'], function () {
    Route::get('/', [DictionaryWordAdminApiController::class, 'index']);
    Route::post('/', [DictionaryWordAdminApiController::class, 'store']);
    Route::post('/import', [DictionaryWordAdminApiController::class, 'import']);
    Route::put('{id}', [DictionaryWordAdminApiController::class, 'update']);
    Route::get('{id}', [DictionaryWordAdminApiController::class, 'show']);
    Route::delete('{id}', [DictionaryWordAdminApiController::class, 'delete']);
});

Route::group(['prefix' => 'api/dictionaries'], function () {
    Route::get('access', [DictionaryAccessApiController::class, 'index'])->middleware(['auth:api']);
    Route::group(['prefix' => '{slug}/words'], function () {
        Route::get('/', [DictionaryWordApiController::class, 'index']);
        Route::get('/categories', [DictionaryWordApiController::class, 'categories']);
        Route::get('/{id}', [DictionaryWordApiController::class, 'show']);
    });
});
