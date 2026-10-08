<?php

use EscolaLms\Tracker\Http\Controllers\TrackerController;
use EscolaLms\Video\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/admin/video', 'middleware' => ['auth:api']], function () {
    Route::get('/states', [VideoController::class, 'states']);
});
