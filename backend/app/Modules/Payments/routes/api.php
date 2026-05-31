<?php

use App\Modules\Payments\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    // Standalone payments
    Route::prefix('api/payments')->group(function () {
        Route::get('/',        [PaymentController::class, 'index']);
        Route::post('/',       [PaymentController::class, 'store']);
        Route::get('/{id}',    [PaymentController::class, 'show']);
        Route::delete('/{id}', [PaymentController::class, 'destroy']);
    });

    // Payments scoped to an order
    Route::get('api/orders/{orderId}/payments', [PaymentController::class, 'forOrder']);
});
