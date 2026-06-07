<?php

use App\Modules\Delivery\Http\Controllers\DeliveryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:delivery'])->group(function () {

    Route::prefix('api/deliveries')->group(function () {
        Route::get('/',               [DeliveryController::class, 'index']);
        Route::post('/',              [DeliveryController::class, 'store']);
        Route::get('/{id}',           [DeliveryController::class, 'show']);

        Route::middleware(['role_or_permission:manager|admin|delivery.manage'])->group(function () {
            Route::post('/{id}/dispatch', [DeliveryController::class, 'dispatch']);
            Route::post('/{id}/deliver',  [DeliveryController::class, 'deliver']);
            Route::post('/{id}/fail',     [DeliveryController::class, 'fail']);
        });
    });

    Route::get('api/orders/{orderId}/deliveries', [DeliveryController::class, 'forOrder']);
});
