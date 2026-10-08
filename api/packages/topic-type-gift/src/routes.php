<?php

use EscolaLms\TopicTypeGift\Http\Controllers\AttemptAnswerApiAdminController;
use EscolaLms\TopicTypeGift\Http\Controllers\AttemptAnswerApiController;
use EscolaLms\TopicTypeGift\Http\Controllers\GiftQuestionApiAdminController;
use EscolaLms\TopicTypeGift\Http\Controllers\GiftQuizApiAdminController;
use EscolaLms\TopicTypeGift\Http\Controllers\QuizAttemptApiAdminController;
use EscolaLms\TopicTypeGift\Http\Controllers\QuizAttemptApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:api'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::prefix('gift-questions')->group(function () {
            Route::post(null, [GiftQuestionApiAdminController::class, 'create']);
            Route::delete('{id}', [GiftQuestionApiAdminController::class, 'delete']);
            Route::put('{id}', [GiftQuestionApiAdminController::class, 'update']);
            Route::post('sort', [GiftQuestionApiAdminController::class, 'sort']);
            Route::get('export', [GiftQuestionApiAdminController::class, 'export']);
            Route::post('import', [GiftQuestionApiAdminController::class, 'import']);
        });

        Route::prefix('quiz-attempts')->group(function () {
            Route::get(null, [QuizAttemptApiAdminController::class, 'index']);
            Route::get('export', [QuizAttemptApiAdminController::class, 'export']);
            Route::get('{id}', [QuizAttemptApiAdminController::class, 'read']);
            Route::patch('{id}/feedback', [QuizAttemptApiAdminController::class, 'feedback']);
        });

        Route::prefix('quiz-answers')->group(function () {
            Route::patch('{id}', [AttemptAnswerApiAdminController::class, 'update']);
        });

        Route::prefix('gift-quizes')->group(function () {
            Route::get(null, [GiftQuizApiAdminController::class, 'index']);
            Route::get('{id}', [GiftQuizApiAdminController::class, 'read']);
            Route::put('{id}', [GiftQuizApiAdminController::class, 'update']);
        });
    });

    Route::prefix('quiz-attempts')->group(function () {
        Route::get(null, [QuizAttemptApiController::class, 'index']);
        Route::post(null, [QuizAttemptApiController::class, 'getActiveAttempt']);
        Route::get('{id}', [QuizAttemptApiController::class, 'read']);
        Route::post('{id}/end', [QuizAttemptApiController::class, 'markAsEnded']);
    });

    Route::prefix('quiz-answers')->group(function () {
        Route::post(null, [AttemptAnswerApiController::class, 'saveAnswer']);
        Route::post('all', [AttemptAnswerApiController::class, 'saveAllAnswers']);
    });

});
