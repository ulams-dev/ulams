<?php

use Illuminate\Support\Facades\Route;
use Ulams\Commerce\Http\Controllers\OrderWebhookController;

// Public: the provider (Sylius) calls it, so there is no session or token. The HMAC signature, its timestamp,
// the event id and the tenant channel are the credentials (ADR 0130); see the controller.
Route::post('api/commerce/webhooks/{provider}', [OrderWebhookController::class, 'receive'])
    ->middleware('throttle:commerce-webhook')
    ->where('provider', '[a-z0-9_-]{1,32}');
