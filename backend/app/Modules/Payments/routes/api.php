<?php

use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Payments\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// ── Webhooks PSP (P6-3) — HORS auth (protégés par signature HMAC), activés par feature flag ──
// Aucune route n'existe tant que `billing.gateways_enabled` est false (NO-GO commercial).
// Quand un PSP réel est branché (P6-4), le flag + le secret webhook activent la route signée.
if (config('billing.gateways_enabled')) {
    foreach (array_keys((array) config('billing.webhooks', [])) as $provider) {
        Route::post("api/webhooks/payments/{$provider}", [PaymentWebhookController::class, 'handle'])
            ->middleware("webhook.signature:{$provider}")
            ->defaults('provider', $provider)
            ->name("webhooks.payments.{$provider}");
    }
}

Route::middleware(['auth:sanctum', \App\Modules\Auth\Http\Middleware\EnsureUserBelongsToTenant::class, 'module:payments'])->group(function () {

    // Standalone payments
    Route::prefix('api/payments')->group(function () {
        Route::get('/',        [PaymentController::class, 'index']);
        Route::post('/',       [PaymentController::class, 'store'])->middleware('role_or_permission:manager|admin|payments.create');
        Route::get('/{id}',    [PaymentController::class, 'show']);
        Route::delete('/{id}', [PaymentController::class, 'destroy']);
    });

    // Payments scoped to an order
    Route::get('api/orders/{orderId}/payments', [PaymentController::class, 'forOrder']);
});
