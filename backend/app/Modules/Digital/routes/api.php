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

    // RC-6E — pool de clés éditeur
    Route::get('products/{productId}/license-keys/summary', [\App\Modules\Digital\Http\Controllers\LicensePoolController::class, 'summary']);

    Route::middleware('role_or_permission:manager|admin|catalog.manage')->group(function () {
        Route::post('entitlements/{id}/revoke',  [DigitalController::class, 'revoke']);
        Route::post('products/{productId}/assets', [DigitalAssetController::class, 'store']);
        Route::post('products/{productId}/license-keys', [\App\Modules\Digital\Http\Controllers\LicensePoolController::class, 'import']); // RC-6E
    });
});

// RC-5I — téléchargement par lien SIGNÉ et expirable (hors auth : le client n'est pas un user du tenant ;
// la signature porte la capacité, et l'accessibilité de l'entitlement est revérifiée au téléchargement).
Route::middleware('signed')
    ->get('api/digital/download/{token}/{asset}', [DigitalAssetController::class, 'download'])
    ->name('digital.download');

// RC-7C — comptes clients du portail (3ᵉ mode d'accès) : inscription vérifiée par code, login token.
Route::prefix('api/portal')->group(function () {
    Route::post('register', [\App\Modules\Digital\Http\Controllers\PortalAccountController::class, 'register'])
        ->middleware('throttle:3,10');
    Route::post('verify',   [\App\Modules\Digital\Http\Controllers\PortalAccountController::class, 'verify'])
        ->middleware('throttle:5,10');
    Route::post('login',    [\App\Modules\Digital\Http\Controllers\PortalAccountController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::get('my-purchases', [\App\Modules\Digital\Http\Controllers\PortalAccountController::class, 'myPurchases'])
        ->middleware('auth:sanctum');
    Route::post('logout', [\App\Modules\Digital\Http\Controllers\PortalAccountController::class, 'logout'])
        ->middleware('auth:sanctum'); // RC-8 F-2 — révocation serveur du token portail
});

// RC-6C — portail client (public, throttlé) : accès par jeton / lien magique, « mes achats » par email.
Route::prefix('api/portal/digital')->group(function () {
    Route::post('access',        [\App\Modules\Digital\Http\Controllers\PortalController::class, 'access'])
        ->middleware('throttle:20,1');
    Route::post('request-links', [\App\Modules\Digital\Http\Controllers\PortalController::class, 'requestLinks'])
        ->middleware('throttle:3,10'); // anti-énumération d'emails (durci en recette QA : 3 / 10 min)
});
