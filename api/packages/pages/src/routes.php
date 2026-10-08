<?php

use EscolaLms\Pages\Http\Controllers\PagesApiController;
use EscolaLms\Pages\Http\Controllers\PagesAdminApiController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/admin/pages', 'middleware' => ['auth:api']], function () {
    Route::get('/', [PagesAdminApiController::class, 'list']);
    Route::get('/{id}', [PagesAdminApiController::class, 'read']);
    Route::post('/', [PagesAdminApiController::class, 'create']);
    Route::delete('/{id}', [PagesAdminApiController::class, 'delete']);
    Route::patch('/{id}', [PagesAdminApiController::class, 'update']);
});


Route::group(['prefix' => 'api/pages'], function () {
    Route::get('/', [PagesApiController::class, 'list']);
    Route::get('/{slug}', [PagesApiController::class, 'read']);
});
