<?php

use App\Modules\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('api/orders')
    ->group(function () {
        Route::get('/',              [OrderController::class, 'index']);
        Route::post('/',             [OrderController::class, 'store']);
        Route::get('/{id}',          [OrderController::class, 'show']);
        Route::post('/{id}/confirm', [OrderController::class, 'confirm']);
        Route::post('/{id}/fulfill', [OrderController::class, 'fulfill']);
        Route::post('/{id}/cancel',  [OrderController::class, 'cancel']);
    });
