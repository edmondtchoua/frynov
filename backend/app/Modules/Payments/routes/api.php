<?php

use App\Modules\Payments\Http\Controllers\PaymentsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('paymentss', PaymentsController::class);
});