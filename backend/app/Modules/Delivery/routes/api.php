<?php

use App\Modules\Delivery\Http\Controllers\DeliveryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    Route::prefix('api/deliveries')->group(function () {
        Route::get('/',               [DeliveryController::class, 'index']);
        Route::post('/',              [DeliveryController::class, 'store']);
        Route::get('/{id}',           [DeliveryController::class, 'show']);
        Route::post('/{id}/dispatch', [DeliveryController::class, 'dispatch']);
        Route::post('/{id}/deliver',  [DeliveryController::class, 'deliver']);
        Route::post('/{id}/fail',     [DeliveryController::class, 'fail']);
    });

    Route::get('api/orders/{orderId}/deliveries', [DeliveryController::class, 'forOrder']);
});
