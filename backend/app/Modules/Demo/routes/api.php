<?php

use App\Modules\Demo\Http\Controllers\DemoRequestController;
use Illuminate\Support\Facades\Route;

// ── Public : soumission d'une demande de démo (formulaire de contact) ───────────
// Throttle dédié (config demo.form_throttle) en plus du plafond global api/*.
Route::post('demo-requests', [DemoRequestController::class, 'store'])
    ->middleware('throttle:'.config('demo.form_throttle', '5,1'))
    ->name('demo-requests.store');
