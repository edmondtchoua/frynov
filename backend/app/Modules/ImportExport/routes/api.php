<?php

use App\Modules\ImportExport\Http\Controllers\ImportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Import ────────────────────────────────────────────────────────────────
    // Note: /history and /template/{type} must come before /{id} to avoid conflict

    Route::get('import/history',              [ImportExportController::class, 'history']);
    Route::get('import/template/{type}',      [ImportExportController::class, 'downloadTemplate']);
    Route::post('import/upload',              [ImportExportController::class, 'upload']);
    Route::get('import/{id}',                 [ImportExportController::class, 'show']);
    Route::patch('import/{id}/mapping',       [ImportExportController::class, 'updateMapping']);
    Route::post('import/{id}/approve',        [ImportExportController::class, 'approve']);
    Route::post('import/{id}/execute',        [ImportExportController::class, 'execute']);
    Route::delete('import/{id}',              [ImportExportController::class, 'cancel']);
    Route::get('import/{id}/report',          [ImportExportController::class, 'downloadReport']);

    // ── Export ────────────────────────────────────────────────────────────────
    Route::get('export/{type}',               [ImportExportController::class, 'export']);
});
