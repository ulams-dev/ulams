<?php

use EscolaLms\Cmi5\Http\Controllers\Cmi5Controller;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => '/api', 'middleware' => ['auth:api', SubstituteBindings::class]], function () {
    Route::group(['prefix' => '/cmi5'], function () {
        Route::get('/player/{auId}', [Cmi5Controller::class, 'read']);
    });

    // admin routes
    Route::group(['prefix' => '/admin/cmi5'], function () {
        Route::get('/', [Cmi5Controller::class, 'list']);
        Route::post('/', [Cmi5Controller::class, 'upload']);
        Route::delete('/{id}', [Cmi5Controller::class, 'delete']);
    });
});

