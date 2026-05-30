<?php

use App\Modules\Sync\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('syncs', SyncController::class);
});