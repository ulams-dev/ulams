<?php

use EscolaLms\Consultations\Http\Controllers\ConsultationAPIController;
use EscolaLms\Consultations\Http\Controllers\ConsultationController;
use Illuminate\Support\Facades\Route;

// admin endpoints
Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/admin'], function () {
    Route::post('consultations/{id}', [ConsultationController::class, 'update']);
    Route::resource('consultations', ConsultationController::class);
    Route::get('consultations/{id}/schedule', [ConsultationController::class, 'schedule']);
    Route::post('consultations/change-term/{consultationTermId}', [ConsultationController::class, 'changeTerm']);
    Route::get('consultations/users/assignable', [ConsultationController::class, 'assignableUsers']);
});

// user endpoints
Route::group(['middleware' => ['auth:api'], 'prefix' => 'api/consultations'], function () {
    Route::get('/me', [ConsultationAPIController::class, 'forCurrentUser']);
    Route::get('/my-schedule', [ConsultationAPIController::class, 'schedule']);
    Route::post('/report-term/{consultationTermId}', [ConsultationAPIController::class, 'reportTerm']);
    Route::get('/proposed-terms/{consultationTermId}', [ConsultationAPIController::class, 'proposedTerms']);
    Route::get('/approve-term/{consultationTermId}', [ConsultationAPIController::class, 'approveTerm']);
    Route::get('/reject-term/{consultationTermId}', [ConsultationAPIController::class, 'rejectTerm']);
    Route::get('/generate-jitsi/{consultationTermId}', [ConsultationAPIController::class, 'generateJitsi']);
    Route::post('/change-term/{consultationTermId}', [ConsultationController::class, 'changeTerm']);
    Route::post('/finish-term/{consultationTermId}', [ConsultationAPIController::class, 'finishTerm']);
});

Route::group(['prefix' => 'api/consultations'], function () {
    Route::get('/', [ConsultationAPIController::class, 'index']);
    Route::get('/{id}', [ConsultationAPIController::class, 'show']);
    Route::post('/save-screen', [ConsultationAPIController::class, 'screenSave']);
    Route::post('/signed-screen-urls', [ConsultationAPIController::class, 'generateSignedScreenUrls']);
});
