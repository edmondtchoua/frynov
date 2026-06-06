<?php

use App\Modules\Sync\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

// Sync is a Phase 3 scaffold (domain undefined). Hidden behind a feature flag so
// the tested code stays in the repo without exposing an unfinished CRUD API to
// real tenants. Enabled in tests via phpunit.xml (FEATURE_SYNC=true).
if (config('frynov.modules.sync', false)) {
    Route::middleware(['auth:sanctum', 'tenant'])->prefix('api')->group(function () {
        Route::apiResource('syncs', SyncController::class)->only(['index', 'show']);
        Route::apiResource('syncs', SyncController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('role:manager|admin');
    });
}