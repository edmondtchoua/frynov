<?php

use App\Modules\Platform\Http\Controllers\AdminDashboardController;
use App\Modules\Platform\Http\Controllers\AdminModuleController;
use App\Modules\Platform\Http\Controllers\AdminPlanController;
use App\Modules\Platform\Http\Controllers\AdminTenantController;
use App\Modules\Platform\Http\Controllers\ModulesController;
use App\Modules\Platform\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;

// ── Client API: active modules for the current tenant ─────────────────────────
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('me/modules', [ModulesController::class, 'forCurrentTenant'])->name('me.modules');
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

        // Audit log
        Route::get('audit-logs',            [AdminPlanController::class, 'auditLogs'])->name('audit-logs');
    });
