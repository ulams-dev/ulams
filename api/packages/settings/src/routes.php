<?php

use EscolaLms\Settings\Http\Controllers\Admin\ConfigController as AdminConfigController;
use EscolaLms\Settings\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use EscolaLms\Settings\Http\Controllers\ConfigController;
use EscolaLms\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/settings'], function () {
    Route::get('/', [SettingsController::class, "index"]);
    Route::get('/{group}/{key}', [SettingsController::class, "show"]);
});

Route::group(['prefix' => 'api/config'], function () {
    Route::get('/', [ConfigController::class, "list"]);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin'], function () {
    Route::get('settings/groups', [AdminSettingsController::class, 'groups']);
    Route::resource('settings', AdminSettingsController::class);

    Route::get('config', [AdminConfigController::class, 'list']);
    Route::post('config', [AdminConfigController::class, 'update']);
});
