<?php

use App\Modules\Billing\Http\Controllers\BillingController;
use App\Modules\Billing\Http\Controllers\PspController;
use App\Modules\Billing\Http\Controllers\PublicPricingController;
use Illuminate\Support\Facades\Route;

Route::get('public/pricing', [PublicPricingController::class, 'index'])->name('public.pricing');
Route::get('public/geo', [PublicPricingController::class, 'geo'])->name('public.geo');
Route::get('public/payment-methods', [PublicPricingController::class, 'paymentMethods'])->name('public.payment-methods');

// Option PSP — webhook public (server-to-server), authentifié par le driver (HMAC).
Route::post('webhooks/psp', [PspController::class, 'webhook'])->name('webhooks.psp');

Route::middleware(['auth:sanctum'])->group(function () {
    // Promo code
    Route::post('me/promo/validate', [BillingController::class, 'validatePromo'])->name('me.promo.validate');
    Route::post('me/promo/apply', [BillingController::class, 'applyPromo'])->middleware('role:admin')->name('me.promo.apply');

    // Manual payment proofs
    Route::get('me/manual-payments', [BillingController::class, 'listPayments'])->name('me.manual-payments.index');
    Route::post('me/manual-payments', [BillingController::class, 'submitPayment'])->name('me.manual-payments.store');

    // Proration preview (RC-2) — lecture seule du reliquat avant un changement de plan.
    Route::post('me/subscription/preview-upgrade', [BillingController::class, 'previewUpgrade'])->name('me.subscription.preview-upgrade');

    // Devis autoritatif (P0) — brut + promo + proration + net à payer, calculé serveur-side.
    Route::post('me/subscription/calculate-upgrade', [BillingController::class, 'calculateUpgrade'])->name('me.subscription.calculate-upgrade');

    // Option PSP — initier un paiement automatisé (checkout).
    Route::post('me/subscription/psp/initiate', [PspController::class, 'initiate'])->name('me.subscription.psp.initiate');

    // Texte + version du consentement (P2) — affiché verbatim dans la case obligatoire.
    Route::get('me/subscription/consent-text', [BillingController::class, 'consentText'])->name('me.subscription.consent-text');

    // Aperçu d'impact d'un downgrade (P3) — modules retirés + quotas dépassés, avant confirmation.
    Route::post('me/subscription/downgrade-impact', [BillingController::class, 'downgradeImpact'])->name('me.subscription.downgrade-impact');

    // Notifications in-app d'abonnement (P2b) — fil de la cloche.
    Route::get('me/subscription/notifications', [BillingController::class, 'listNotifications'])->name('me.subscription.notifications.index');
    Route::post('me/subscription/notifications/{id}/read', [BillingController::class, 'readNotification'])->name('me.subscription.notifications.read');

    // Demandes de changement de plan (P1) — objet de premier plan + machine à états.
    Route::get('me/subscription/change-requests', [BillingController::class, 'listChangeRequests'])->name('me.subscription.change-requests.index');
    Route::post('me/subscription/change-requests', [BillingController::class, 'createChangeRequest'])->name('me.subscription.change-requests.store');
    Route::get('me/subscription/change-requests/{id}', [BillingController::class, 'showChangeRequest'])->name('me.subscription.change-requests.show');
    Route::post('me/subscription/change-requests/{id}/submit', [BillingController::class, 'submitChangeRequest'])->name('me.subscription.change-requests.submit');
    Route::post('me/subscription/change-requests/{id}/cancel', [BillingController::class, 'cancelChangeRequest'])->name('me.subscription.change-requests.cancel');
});
