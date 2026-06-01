<?php

use App\Modules\Sync\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('syncs', SyncController::class)->only(['index', 'show']);
    Route::apiResource('syncs', SyncController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:manager|admin');
});