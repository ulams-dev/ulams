<?php

use Illuminate\Support\Facades\Route;
use EscolaLms\Mattermost\Http\Controllers\MattermostController;

Route::group(['prefix' => 'api/mattermost', 'middleware' => ['auth:api']], function () {
    Route::get('/me', [MattermostController::class, 'me']);
    Route::get('/reset_password', [MattermostController::class, 'resetPassword']);
    Route::get('/generate_credentials', [MattermostController::class, 'generateCredentials']);
});
