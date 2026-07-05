<?php

use App\Modules\Pos\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', \App\Modules\Auth\Http\Middleware\EnsureUserBelongsToTenant::class])->group(function () {

    Route::prefix('api/pos')->group(function () {
        // Cash-register sessions
        Route::get('sessions',                  [PosController::class, 'index']);
        Route::get('sessions/current',          [PosController::class, 'current']);
        Route::post('sessions',                 [PosController::class, 'open']);
        Route::post('sessions/{id}/checkout',   [PosController::class, 'checkout']);
        Route::post('sessions/{id}/close',      [PosController::class, 'close']);
    });
});
