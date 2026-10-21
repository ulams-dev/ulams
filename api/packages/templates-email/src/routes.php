<?php

use Illuminate\Support\Facades\Route;
use Ulams\TemplatesEmail\Http\Controllers\EmailTemplatePreviewController;

// Draft rendering and test send for the e-mail template editor. Both read only the current tenant's
// settings and variable classes and write nothing; permission `template_update` is checked in the request.
Route::group(['prefix' => 'api/admin/templates/email', 'middleware' => ['auth:api']], function () {
    Route::post('/render', [EmailTemplatePreviewController::class, 'render'])->middleware('throttle:30,1,email-render');
    Route::post('/images', [EmailTemplatePreviewController::class, 'uploadImage'])->middleware('throttle:30,1,email-image');
    Route::post('/test-send', [EmailTemplatePreviewController::class, 'testSend'])->middleware('throttle:5,1,email-test-send');
});
