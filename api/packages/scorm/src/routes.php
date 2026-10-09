<?php

use Ulams\Scorm\Http\Controllers\ScormContentController;
use Ulams\Scorm\Http\Controllers\ScormController;
use Ulams\Scorm\Http\Controllers\ScormFileController;

use Ulams\Scorm\Http\Controllers\ScormTrackController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/admin/scorm', 'middleware' => ['auth:api', SubstituteBindings::class]], function () {
    Route::post('/upload', [ScormController::class, "upload"]);
    Route::post('/parse', [ScormController::class, "parse"]);
    Route::delete('/{scormModel}', [ScormController::class, "delete"]);
    Route::get('/', [ScormController::class, "index"]);
    Route::get('/scos', [ScormController::class, "getScos"]);
});

Route::group(['prefix' => 'api/scorm'], function () {
    // vendored scorm-again for the legacy player (was loaded from jsDelivr)
    Route::get('/assets/scorm-again.min.js', [ScormContentController::class, 'scormAgain']);

    // content-origin player: the learner token only issues a SCO-scoped tracking token,
    // which is all the player on the content origin ever sees
    Route::post('/launch/{uuid}', [ScormContentController::class, 'launch'])->middleware('auth:api');
    Route::get('/content/{uuid}', [ScormContentController::class, 'show']);
    Route::post('/content/{uuid}/track', [ScormContentController::class, 'track']);

    Route::get('/play/{uuid}', [ScormController::class, "showView"]);
    Route::get('/service-worker', [ScormController::class, "showViewServiceWorker"]);
    Route::get('/show/{uuid}', [ScormController::class, "showJson"]);
    Route::get('/zip/{uuid}', [ScormController::class, "createOrGetZip"]);

    Route::group(['prefix' => '/track', 'middleware' => ['auth:api', SubstituteBindings::class]], function () {
        Route::post('/{uuid}', [ScormTrackController::class, 'set']);
        Route::get('/{scoId}/{key}', [ScormTrackController::class, 'get']);
    });
});

// Package files on a local SCORM disk (per tenant storage directory): <disk url>/scorm/{path}.
// An S3 disk serves them from the bucket instead.
$scormDisk = (array) config('filesystems.disks.' . config('scorm.disk'), []);
if (($scormDisk['driver'] ?? null) === 'local') {
    $scormUrlPath = trim((string) parse_url((string) ($scormDisk['url'] ?? '/storage'), PHP_URL_PATH), '/');
    Route::get(($scormUrlPath === '' ? '' : $scormUrlPath . '/') . 'scorm/{path}', [ScormFileController::class, 'show'])
        ->where('path', '.*')
        ->name('scorm.files');
}
