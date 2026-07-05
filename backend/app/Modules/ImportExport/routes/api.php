<?php

use App\Modules\ImportExport\Http\Controllers\ImportExportController;
use Illuminate\Support\Facades\Route;

// RC-8 F-8 — `tenant` ancre le contexte d'équipe Spatie (setPermissionsTeamId) : sans lui, les gardes
// `role_or_permission:import_export.*` pouvaient dévier faute de team_id posé sur ce groupe.
Route::middleware(['auth:sanctum', 'tenant', 'module:import_export'])->group(function () {

    // ── Import ────────────────────────────────────────────────────────────────
    // Note: /history and /template/{type} must come before /{id} to avoid conflict

    Route::get('import/history',              [ImportExportController::class, 'history']);
    Route::get('import/template/{type}',      [ImportExportController::class, 'downloadTemplate']);
    Route::post('import/upload',              [ImportExportController::class, 'upload']);
    Route::get('import/{id}',                 [ImportExportController::class, 'show']);
    Route::patch('import/{id}/mapping',       [ImportExportController::class, 'updateMapping']);
    Route::delete('import/{id}',              [ImportExportController::class, 'cancel']);
    Route::get('import/{id}/report',          [ImportExportController::class, 'downloadReport']);

    Route::middleware(['role_or_permission:manager|admin|import_export.create|import_export.update'])->group(function () {
        Route::post('import/{id}/approve',    [ImportExportController::class, 'approve']);
        Route::post('import/{id}/execute',    [ImportExportController::class, 'execute']);
    });

    // ── Export ────────────────────────────────────────────────────────────────
    Route::get('export/{type}',               [ImportExportController::class, 'export']);
});
