<?php

use Illuminate\Support\Facades\Route;
use Ulams\Demo\Http\Controllers\DemoApiController;

// Registered only when DEMO_MODE=true (see UlamsDemoServiceProvider).
Route::group(['prefix' => 'api/demo'], function () {
    Route::get('/', [DemoApiController::class, 'show']);
    Route::post('login', [DemoApiController::class, 'login'])->middleware('throttle:60,1');
});
