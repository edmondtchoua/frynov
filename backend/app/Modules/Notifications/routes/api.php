<?php

use App\Modules\Notifications\Http\Controllers\NotificationAdminController;
use Illuminate\Support\Facades\Route;

/**
 * RC-6A — notifications sortantes : canaux, modèles, journal. Consultation ouverte au tenant,
 * configuration réservée manager/admin.
 */
Route::middleware(['auth:sanctum', 'tenant'])->prefix('api/notifications')->group(function () {
    Route::get('channels',   [NotificationAdminController::class, 'channels']);
    Route::get('templates',  [NotificationAdminController::class, 'templates']);
    Route::get('outbox',     [NotificationAdminController::class, 'outbox']);

    Route::middleware('role_or_permission:manager|admin')->group(function () {
        Route::post('channels',              [NotificationAdminController::class, 'storeChannel']);
        Route::patch('channels/{id}',        [NotificationAdminController::class, 'updateChannel']);
        Route::delete('channels/{id}',       [NotificationAdminController::class, 'destroyChannel']);
        Route::post('channels/{id}/test',    [NotificationAdminController::class, 'testChannel']);
        Route::put('templates',              [NotificationAdminController::class, 'upsertTemplate']);
    });
});
