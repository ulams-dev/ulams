<?php

use Ulams\Lrs\Http\Controllers\StatementController;
use Ulams\Lrs\Http\Controllers\LrsController;
use Ulams\Lrs\Http\Controllers\Xapi\XapiAboutController;
use Ulams\Lrs\Http\Controllers\Xapi\XapiDocumentController;
use Ulams\Lrs\Http\Controllers\Xapi\XapiStatementController;
use Ulams\Lrs\Http\Middleware\AuthenticateXapiAccess;
use Ulams\Lrs\Http\Middleware\XapiVersion;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {
    Route::group(['prefix' => '/cmi5'], function () {
        Route::post('/fetch', [LrsController::class, 'fetch'])->name("cmi5.fetch");
        Route::group(['middleware' => ['auth:api']], function () {
            Route::get('/courses/{id}', [LrsController::class, 'launchParams']);
        });
    });

    Route::group(['prefix' => '/admin/cmi5', 'middleware' => ['auth:api']], function () {
        Route::get('/statements', [StatementController::class, 'statements']);
    });
});

// xAPI endpoint of an access: {app.url}/trax/api/{access uuid}/xapi/std (see README).
Route::group(['prefix' => 'trax/api/{source}/xapi/std'], function () {
    Route::get('/about', [XapiAboutController::class, 'get']);

    Route::group(['middleware' => [AuthenticateXapiAccess::class, XapiVersion::class]], function () {
        Route::get('/statements', [XapiStatementController::class, 'get']);
        Route::post('/statements', [XapiStatementController::class, 'post']);
        Route::put('/statements', [XapiStatementController::class, 'put']);

        $documents = [
            'activities/state' => 'state',
            'activities/profile' => 'activity_profile',
            'agents/profile' => 'agent_profile',
        ];
        foreach ($documents as $path => $resource) {
            Route::get($path, [XapiDocumentController::class, 'get'])->defaults('resource', $resource);
            Route::put($path, [XapiDocumentController::class, 'put'])->defaults('resource', $resource);
            Route::post($path, [XapiDocumentController::class, 'post'])->defaults('resource', $resource);
            Route::delete($path, [XapiDocumentController::class, 'delete'])->defaults('resource', $resource);
        }
    });
});
