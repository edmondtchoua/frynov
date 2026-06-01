<?php

use App\Modules\Platform\Http\Controllers\AdminAuditController;
use App\Modules\Platform\Http\Controllers\AdminDashboardController;
use App\Modules\Platform\Http\Controllers\AdminManualPaymentController;
use App\Modules\Platform\Http\Controllers\AdminModuleController;
use App\Modules\Platform\Http\Controllers\AdminPlanController;
use App\Modules\Platform\Http\Controllers\AdminPromotionController;
use App\Modules\Platform\Http\Controllers\AdminTenantController;
use App\Modules\Platform\Http\Controllers\ModulesController;
use App\Modules\Platform\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;

// ── Client API ────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('me/modules',       [ModulesController::class, 'forCurrentTenant'])->name('me.modules');
    Route::get('me/subscription',  [ModulesController::class, 'currentSubscription'])->name('me.subscription');
});

// ── Admin back-office API ──────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', RequireAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard stats
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        // Tenants
        Route::get('tenants',                                     [AdminTenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}',                            [AdminTenantController::class, 'show'])->name('tenants.show');
        Route::patch('tenants/{tenant}',                          [AdminTenantController::class, 'update'])->name('tenants.update');
        Route::post('tenants/{tenant}/suspend',                   [AdminTenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('tenants/{tenant}/reactivate',                [AdminTenantController::class, 'reactivate'])->name('tenants.reactivate');
        Route::post('tenants/{tenant}/change-plan',               [AdminTenantController::class, 'changePlan'])->name('tenants.change-plan');

        // ERP Modules
        Route::get('modules',                                     [AdminModuleController::class, 'index'])->name('modules.index');
        Route::patch('modules/{module}',                          [AdminModuleController::class, 'update'])->name('modules.update');
        Route::get('tenants/{tenant}/modules',                    [AdminModuleController::class, 'forTenant'])->name('tenants.modules');
        Route::post('tenants/{tenant}/modules/{moduleCode}/activate',   [AdminModuleController::class, 'activateForTenant'])->name('tenants.modules.activate');
        Route::post('tenants/{tenant}/modules/{moduleCode}/deactivate', [AdminModuleController::class, 'deactivateForTenant'])->name('tenants.modules.deactivate');

        // Plans
        Route::get('plans',                 [AdminPlanController::class, 'index'])->name('plans.index');
        Route::get('plans/{plan}',          [AdminPlanController::class, 'show'])->name('plans.show');
        Route::patch('plans/{plan}',        [AdminPlanController::class, 'update'])->name('plans.update');

        // Manual payments (admin review)
        Route::get('manual-payments',                                    [AdminManualPaymentController::class, 'index'])->name('manual-payments.index');
        Route::get('manual-payments/{manualPayment}',                    [AdminManualPaymentController::class, 'show'])->name('manual-payments.show');
        Route::post('manual-payments/{manualPayment}/approve',           [AdminManualPaymentController::class, 'approve'])->name('manual-payments.approve');
        Route::post('manual-payments/{manualPayment}/reject',            [AdminManualPaymentController::class, 'reject'])->name('manual-payments.reject');

        // Promotions
        Route::get('promotions',            [AdminPromotionController::class, 'index'])->name('promotions.index');
        Route::post('promotions',           [AdminPromotionController::class, 'store'])->name('promotions.store');
        Route::get('promotions/{promotion}', [AdminPromotionController::class, 'show'])->name('promotions.show');
        Route::patch('promotions/{promotion}', [AdminPromotionController::class, 'update'])->name('promotions.update');
        Route::delete('promotions/{promotion}', [AdminPromotionController::class, 'destroy'])->name('promotions.destroy');

        // Audit log
        Route::get('audit-logs',              [AdminAuditController::class, 'index'])->name('audit-logs.index');
        Route::post('audit-logs/verify-chain', [AdminAuditController::class, 'verifyChain'])->name('audit-logs.verify-chain');
    });
