<?php

use App\Modules\Billing\Http\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    // Promo code
    Route::post('me/promo/validate', [BillingController::class, 'validatePromo'])->name('me.promo.validate');
    Route::post('me/promo/apply',    [BillingController::class, 'applyPromo'])->middleware('role:admin')->name('me.promo.apply');

    // Manual payment proofs
    Route::get('me/manual-payments',  [BillingController::class, 'listPayments'])->name('me.manual-payments.index');
    Route::post('me/manual-payments', [BillingController::class, 'submitPayment'])->name('me.manual-payments.store');
});
