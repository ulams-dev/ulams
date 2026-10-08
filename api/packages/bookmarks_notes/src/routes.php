<?php

use EscolaLms\Bookmarks\Http\Controllers\AdminBookmarkController;
use EscolaLms\Bookmarks\Http\Controllers\BookmarkController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/admin/bookmarks')
    ->middleware(['auth:api'])
    ->group(function (): void {
        Route::get(null, [AdminBookmarkController::class, 'findAll']);
        Route::post(null, [AdminBookmarkController::class, 'create']);
        Route::patch('{id}', [AdminBookmarkController::class, 'update']);
        Route::delete('{id}', [AdminBookmarkController::class, 'delete']);
    });

Route::prefix('api/bookmarks')
    ->middleware(['auth:api'])
    ->group(function (): void {
        Route::post(null, [BookmarkController::class, 'create']);
        Route::patch('{id}', [BookmarkController::class, 'update']);
        Route::delete('{id}', [BookmarkController::class, 'delete']);
        Route::get(null, [BookmarkController::class, 'findAll']);
    });
