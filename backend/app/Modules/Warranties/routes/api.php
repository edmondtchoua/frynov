<?php

use App\Modules\Warranties\Http\Controllers\WarrantyController;
use Illuminate\Support\Facades\Route;

/**
 * RC-5D — garanties. Pas de gate `module:` dédié (fonctionnalité transverse aux produits spéciaux) ;
 * seulement auth + appartenance tenant. Les écritures sont réservées manager/admin.
 */
Route::middleware(['auth:sanctum', 'tenant'])->prefix('api/warranties')->group(function () {
    Route::get('policies',                 [WarrantyController::class, 'policies']);
    Route::get('orders/{orderId}',         [WarrantyController::class, 'forOrder']);

    Route::middleware('role_or_permission:manager|admin|catalog.manage')->group(function () {
        Route::post('policies',                          [WarrantyController::class, 'storePolicy']);
        Route::post('products/{productId}/policy',       [WarrantyController::class, 'attachToProduct']);
    });
});
