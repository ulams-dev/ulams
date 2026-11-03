<?php

use Illuminate\Support\Facades\Route;
use Ulams\KnowledgeExport\Http\Controllers\KnowledgeExportController;

// Admin only (permission knowledge_manage). `ulams-knowledge` for everything, `ulams-knowledge-start` on top for new exports.
Route::prefix('api/admin/knowledge')
    ->middleware(['auth:api', 'throttle:ulams-knowledge'])
    ->group(function (): void {
        Route::get('exports', [KnowledgeExportController::class, 'index']);
        Route::post('exports', [KnowledgeExportController::class, 'store'])->middleware('throttle:ulams-knowledge-start');
        Route::get('exports/{export}', [KnowledgeExportController::class, 'show']);
        Route::get('exports/{export}/download', [KnowledgeExportController::class, 'download']);
        Route::get('courses/{course}/latest', [KnowledgeExportController::class, 'latest']);
    });
