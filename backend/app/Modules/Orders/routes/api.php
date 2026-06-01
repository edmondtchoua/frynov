<?php

use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Orders\Http\Controllers\OrderReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('api/orders')
    ->group(function () {
        // ── Returns / RMA (Sprint 10) — MUST be before /{id} to avoid route shadowing
        Route::get('returns',                [OrderReturnController::class, 'index']);
        Route::get('returns/{id}',           [OrderReturnController::class, 'show']);
        Route::post('returns/{id}/approve',  [OrderReturnController::class, 'approve']);
        Route::post('returns/{id}/restock',  [OrderReturnController::class, 'restock']);
        Route::post('returns/{id}/reject',   [OrderReturnController::class, 'reject']);

        // ── Orders CRUD
        Route::get('/',              [OrderController::class, 'index']);
        Route::post('/',             [OrderController::class, 'store'])->middleware('quota:orders');
        Route::get('/{id}',          [OrderController::class, 'show']);
        Route::post('/{id}/confirm', [OrderController::class, 'confirm']);
        Route::post('/{id}/fulfill', [OrderController::class, 'fulfill']);
        Route::post('/{id}/cancel',  [OrderController::class, 'cancel']);
        Route::post('{orderId}/returns', [OrderReturnController::class, 'store']);
    });
