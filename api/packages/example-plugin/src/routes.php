<?php

use Illuminate\Support\Facades\Route;
use Ulams\ExamplePlugin\Http\Controllers\Admin\GreetingAdminApiController;
use Ulams\ExamplePlugin\Http\Controllers\HelloApiController;

Route::prefix('api/example-plugin')->group(function (): void {
    Route::get('hello', [HelloApiController::class, 'hello']);
});

Route::prefix('api/admin/example-plugin')
    ->middleware(['auth:api'])
    ->group(function (): void {
        Route::post('greetings', [GreetingAdminApiController::class, 'send']);
    });
