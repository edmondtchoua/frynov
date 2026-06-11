<?php

use App\Modules\Digital\Http\Controllers\DigitalController;
use Illuminate\Support\Facades\Route;

/**
 * RC-5E — produits digitaux. Auth + appartenance tenant ; révocation réservée manager/admin.
 */
Route::middleware(['auth:sanctum', 'tenant'])->prefix('api/digital')->group(function () {
    Route::get('orders/{orderId}/entitlements', [DigitalController::class, 'forOrder']);
    Route::get('access/{token}',                [DigitalController::class, 'access']);

    Route::middleware('role_or_permission:manager|admin|catalog.manage')->group(function () {
        Route::post('entitlements/{id}/revoke', [DigitalController::class, 'revoke']);
    });
});
