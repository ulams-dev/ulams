<?php

use Ulams\TemplatesPdf\Http\Controllers\CertificateVerificationController;
use Ulams\TemplatesPdf\Http\Controllers\FabricPdfController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/pdfs'], function () {
    // public: fonts bundled with the PDF renderer, used by the admin designer
    Route::get('/fonts', [FabricPdfController::class, 'fonts']);
    Route::get('/fonts/{file}', [FabricPdfController::class, 'font']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/', [FabricPdfController::class, 'index']);
        Route::get('/generate/{id}', [FabricPdfController::class, 'generate']);
        Route::get('/{id}', [FabricPdfController::class, 'show']);
    });
});

// public: what the certificate QR code leads to
Route::get('api/certificates/verify/{certificateId}', [CertificateVerificationController::class, 'show'])
    ->middleware('throttle:' . (int) config('ulams_templates_pdf.verify.throttle', 60) . ',1')
    ->where('certificateId', '[A-Za-z0-9-]{8,64}');

Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin/pdfs'], function () {
    Route::post('/{id}/revoke', [CertificateVerificationController::class, 'revoke'])->whereNumber('id');
    Route::get('/', [FabricPdfController::class, 'admin']);
    Route::post('/preview', [FabricPdfController::class, 'preview']);
});
