<?php

use EscolaLms\ConsultationAccess\Http\Controllers\Admin\ConsultationAccessEnquiryAdminApiController;
use EscolaLms\ConsultationAccess\Http\Controllers\ConsultationAccessEnquiryApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:api'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::prefix('consultation-access-enquiries')->group(function () {
            Route::get(null, [ConsultationAccessEnquiryAdminApiController::class, 'index']);
            Route::post('approve/{proposedTermId}', [ConsultationAccessEnquiryAdminApiController::class, 'approve']);
            Route::post('disapprove/{id}', [ConsultationAccessEnquiryAdminApiController::class, 'disapprove']);
        });
    });

    Route::prefix('consultation-access-enquiries')->group(function () {
        Route::get(null, [ConsultationAccessEnquiryApiController::class, 'index']);
        Route::post(null, [ConsultationAccessEnquiryApiController::class, 'create']);
        Route::get('{id}', [ConsultationAccessEnquiryApiController::class, 'read'])->whereNumber('id');
        Route::get('{id}/join', [ConsultationAccessEnquiryApiController::class, 'join'])->whereNumber('id');
        Route::patch('{id}', [ConsultationAccessEnquiryApiController::class, 'update'])->whereNumber('id');
        Route::delete('{id}', [ConsultationAccessEnquiryApiController::class, 'delete'])->whereNumber('id');
    });
});
