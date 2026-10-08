<?php

use EscolaLms\CoursesImportExport\Http\Controllers\CourseExportImportAPIController;
use Illuminate\Support\Facades\Route;

// admin endpoints
Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin'], function () {
    Route::get('courses/{course}/clone', [CourseExportImportAPIController::class, 'clone']);
    Route::get('courses/{course}/export', [CourseExportImportAPIController::class, 'export']);
    Route::post('courses/zip/import', [CourseExportImportAPIController::class, 'import']);
});
