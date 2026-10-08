<?php

use EscolaLms\CourseAccess\Http\Controllers\Admin\CourseAccessAPIController;
use EscolaLms\CourseAccess\Http\Controllers\CourseAccessApiController as CourseAccessApiStudentController;
use EscolaLms\CourseAccess\Http\Controllers\Admin\CourseAccessEnquiryApiAdminController;
use EscolaLms\CourseAccess\Http\Controllers\CourseAccessEnquiryApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:api'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::prefix('courses')->group(function () {
            Route::get('{id}/access', [CourseAccessAPIController::class, 'list']);
            Route::post('{id}/access/add', [CourseAccessAPIController::class, 'add']);
            Route::post('{id}/access/remove', [CourseAccessAPIController::class, 'remove']);
            Route::post('{id}/access/set', [CourseAccessAPIController::class, 'set']);
        });

        Route::prefix('course-access-enquiries')->group(function () {
            Route::get(null, [CourseAccessEnquiryApiAdminController::class, 'list']);
            Route::delete('{id}', [CourseAccessEnquiryApiAdminController::class, 'delete']);
            Route::post('approve/{id}', [CourseAccessEnquiryApiAdminController::class, 'approve']);
        });
    });

    Route::prefix('course-access-enquiries')->group(function () {
        Route::get(null, [CourseAccessEnquiryApiController::class, 'list']);
        Route::post(null, [CourseAccessEnquiryApiController::class, 'create']);
        Route::delete('{id}', [CourseAccessEnquiryApiController::class, 'delete']);
    });

    Route::prefix('courses')->group(function () {
        Route::get('my', [CourseAccessApiStudentController::class, 'getMyCourseIds']);
    });
});
