<?php

use App\Modules\Demo\Http\Controllers\AdminDemoRequestController;
use App\Modules\Demo\Http\Controllers\DemoRequestController;
use App\Modules\Platform\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;

// ── Public : soumission d'une demande de démo (formulaire de contact) ───────────
// Throttle dédié (config demo.form_throttle) en plus du plafond global api/*.
Route::post('demo-requests', [DemoRequestController::class, 'store'])
    ->middleware('throttle:'.config('demo.form_throttle', '5,1'))
    ->name('demo-requests.store');

// ── Back-office super-admin : gestion des demandes de démo ──────────────────────
Route::middleware(['auth:sanctum', RequireAdmin::class])
    ->prefix('admin/demo-requests')
    ->name('admin.demo-requests.')
    ->group(function () {
        Route::get('/',                     [AdminDemoRequestController::class, 'index'])->name('index');
        Route::get('{demoRequest}',         [AdminDemoRequestController::class, 'show'])->name('show');
        Route::post('{demoRequest}/approve', [AdminDemoRequestController::class, 'approve'])->name('approve');
        Route::post('{demoRequest}/resend',  [AdminDemoRequestController::class, 'resend'])->name('resend');
        Route::post('{demoRequest}/reject',  [AdminDemoRequestController::class, 'reject'])->name('reject');
        Route::post('{demoRequest}/expire',  [AdminDemoRequestController::class, 'expire'])->name('expire');
        Route::post('{demoRequest}/convert', [AdminDemoRequestController::class, 'convert'])->name('convert');
        Route::patch('{demoRequest}/notes',  [AdminDemoRequestController::class, 'notes'])->name('notes');
    });
