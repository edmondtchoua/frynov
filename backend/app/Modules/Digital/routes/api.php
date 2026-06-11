<?php

use App\Modules\Digital\Http\Controllers\DigitalAssetController;
use App\Modules\Digital\Http\Controllers\DigitalController;
use Illuminate\Support\Facades\Route;

/**
 * RC-5E/5I — produits digitaux. Auth + appartenance tenant ; écritures réservées manager/admin.
 */
Route::middleware(['auth:sanctum', 'tenant'])->prefix('api/digital')->group(function () {
    Route::get('orders/{orderId}/entitlements', [DigitalController::class, 'forOrder']);
    Route::get('access/{token}',                [DigitalController::class, 'access']);
    Route::get('products/{productId}/assets',   [DigitalAssetController::class, 'index']);

    Route::middleware('role_or_permission:manager|admin|catalog.manage')->group(function () {
        Route::post('entitlements/{id}/revoke',  [DigitalController::class, 'revoke']);
        Route::post('products/{productId}/assets', [DigitalAssetController::class, 'store']);
    });
});

// RC-5I — téléchargement par lien SIGNÉ et expirable (hors auth : le client n'est pas un user du tenant ;
// la signature porte la capacité, et l'accessibilité de l'entitlement est revérifiée au téléchargement).
Route::middleware('signed')
    ->get('api/digital/download/{token}/{asset}', [DigitalAssetController::class, 'download'])
    ->name('digital.download');
