<?php

use EscolaLms\Templates\Http\Controllers\EventAdminApiController;
use EscolaLms\Templates\Http\Controllers\TemplatesAdminApiController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/admin/templates', 'middleware' => ['auth:api']], function () {
    Route::get('/', [TemplatesAdminApiController::class, 'list']);
    Route::post('/', [TemplatesAdminApiController::class, 'create']);

    Route::get('/events', [TemplatesAdminApiController::class, 'events']);
    Route::get('/variables', [TemplatesAdminApiController::class, 'variables']);
    Route::get('/assignable', [TemplatesAdminApiController::class, 'assignable']);
    Route::get('/assigned', [TemplatesAdminApiController::class, 'assigned']);

    Route::get('/{id}', [TemplatesAdminApiController::class, 'read']);
    Route::get('/{id}/preview', [TemplatesAdminApiController::class, 'preview']);
    Route::patch('/{id}', [TemplatesAdminApiController::class, 'update']);
    Route::delete('/{id}', [TemplatesAdminApiController::class, 'delete']);

    Route::post('/{id}/assign', [TemplatesAdminApiController::class, 'assign']);
    Route::post('/{id}/unassign', [TemplatesAdminApiController::class, 'unassign']);
});

Route::group(['prefix' => 'api/admin/events/', 'middleware' => ['auth:api']], function () {
    Route::post('trigger-manually/{id}', [EventAdminApiController::class, 'triggerManually']);
});
