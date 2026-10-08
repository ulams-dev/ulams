<?php

use Illuminate\Support\Facades\Route;
use Ulams\H5P\Http\Controllers\H5PContentAdminApiController;

// Everything else (create, edit, upload, play, download, libraries) is served
// by the H5P service at /h5p/* on the API host.
Route::prefix('api/admin/h5p')
    ->middleware(['auth:api'])
    ->group(function (): void {
        Route::get('contents', [H5PContentAdminApiController::class, 'index']);
        Route::delete('unused', [H5PContentAdminApiController::class, 'deleteUnused']);
    });
