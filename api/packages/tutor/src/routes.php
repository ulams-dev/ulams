<?php

use Illuminate\Support\Facades\Route;
use Ulams\Tutor\Http\Controllers\TutorController;

Route::group([
    'prefix' => 'api/tutor',
    'middleware' => ['auth:api'],
], function () {
    Route::get('courses/{course}/status', [TutorController::class, 'status'])->whereNumber('course');
    Route::post('courses/{course}/messages', [TutorController::class, 'ask'])->whereNumber('course')->middleware('throttle:tutor');
    Route::get('courses/{course}/conversations', [TutorController::class, 'conversations'])->whereNumber('course');
    Route::get('conversations/{conversation}', [TutorController::class, 'show']);
    Route::delete('conversations/{conversation}', [TutorController::class, 'destroy']);
});

// Anonymous aggregates for authors and admins (permission check in the controller).
Route::get('api/admin/tutor/courses/{course}/analytics', [TutorController::class, 'analytics'])->whereNumber('course')->middleware('auth:api');
