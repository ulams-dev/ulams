<?php

use Ulams\TemplatesPdf\Http\Controllers\FabricPdfController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/pdfs'], function () {
    // public: fonts bundled with the PDF renderer, used by the admin designer
    Route::get('/fonts', [FabricPdfController::class, 'fonts']);
    Route::get('/fonts/{file}', [FabricPdfController::class, 'font']);

    Route::get('/', [FabricPdfController::class, 'index']);
    Route::get('/generate/{id}', [FabricPdfController::class, 'generate']);
    Route::get('/{id}', [FabricPdfController::class, 'show']);
});

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin/pdfs'], function () {
    Route::get('/', [FabricPdfController::class, 'admin']);
    Route::post('/preview', [FabricPdfController::class, 'preview']);
});
