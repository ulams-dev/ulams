<?php

use EscolaLms\Permissions\Http\Controllers\PermissionsAdminApiController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/admin/roles', 'middleware' => ['auth:api']], function () {
    Route::get('/', [PermissionsAdminApiController::class, 'index']);
    Route::get('/{name}', [PermissionsAdminApiController::class, 'show']);
    Route::post('/', [PermissionsAdminApiController::class, 'create']);
    Route::delete('/{name}', [PermissionsAdminApiController::class, 'delete']);
    Route::patch('/{name}', [PermissionsAdminApiController::class, 'update']);
});
