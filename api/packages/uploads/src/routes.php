<?php

use Illuminate\Support\Facades\Route;
use Ulams\Uploads\Http\ContentFileController;

// Package files for the tenant content origin only (header set by its proxy, see the controller).
Route::get('api/content/{path}', [ContentFileController::class, 'show'])->where('path', '.*')->name('ulams.content-file');
