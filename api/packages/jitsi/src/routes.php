<?php

use Ulams\Jitsi\Http\Controllers\JitsiApiController;
use Ulams\Jitsi\Http\Middleware\VerifyJitsiWebhook;
use Illuminate\Support\Facades\Route;

Route::post('api/jitsi/recorded-video', [JitsiApiController::class, 'recordedVideo'])
    ->middleware(['throttle:60,1', VerifyJitsiWebhook::class]);
