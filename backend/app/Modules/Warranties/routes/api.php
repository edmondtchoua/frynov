<?php

use App\Modules\Warranties\Http\Controllers\WarrantyClaimController;
use App\Modules\Warranties\Http\Controllers\WarrantyController;
use Illuminate\Support\Facades\Route;

/**
 * RC-5D/5F — garanties & SAV. Pas de gate `module:` dédié (fonctionnalité transverse aux produits
 * spéciaux) ; seulement auth + appartenance tenant. Les écritures sont réservées manager/admin.
 */
Route::middleware(['auth:sanctum', 'tenant'])->prefix('api/warranties')->group(function () {
    Route::get('policies',                 [WarrantyController::class, 'policies']);
    Route::get('orders/{orderId}',         [WarrantyController::class, 'forOrder']);

    // RC-5F — SAV (consultation)
    Route::get('contracts/{contractId}/claims', [WarrantyClaimController::class, 'indexForContract']);
    Route::get('orders/{orderId}/claims',        [WarrantyClaimController::class, 'indexForOrder']);

    Route::middleware('role_or_permission:manager|admin|catalog.manage')->group(function () {
        Route::post('policies',                          [WarrantyController::class, 'storePolicy']);
        Route::post('products/{productId}/policy',       [WarrantyController::class, 'attachToProduct']);

        // RC-5F — SAV (écritures)
        Route::post('contracts/{contractId}/claims', [WarrantyClaimController::class, 'store']);
        Route::post('claims/{id}/transition',        [WarrantyClaimController::class, 'transition']);
    });
});
