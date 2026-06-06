<?php
use App\Modules\Marketplace\Http\Controllers\MarketplaceListingController;
use Illuminate\Support\Facades\Route;

// NOTE: ModuleServiceProvider already adds prefix('api') — use 'marketplace' only here
Route::middleware(['auth:sanctum', 'tenant'])
    ->prefix('marketplace')
    ->name('marketplace.')
    ->group(function () {
        Route::get('platforms',              [MarketplaceListingController::class, 'platforms'])->name('platforms');
        Route::get('listings',               [MarketplaceListingController::class, 'index'])->name('listings.index');
        Route::get('alerts',                 [MarketplaceListingController::class, 'alerts'])->name('alerts.index');
        Route::patch('alerts/{id}/read',     [MarketplaceListingController::class, 'markRead'])->name('alerts.read');

        Route::middleware(['role_or_permission:manager|admin|marketplace.manage'])->group(function () {
            Route::post('listings',              [MarketplaceListingController::class, 'store'])->name('listings.store');
            Route::patch('listings/{id}',        [MarketplaceListingController::class, 'update'])->name('listings.update');
            Route::delete('listings/{id}',       [MarketplaceListingController::class, 'destroy'])->name('listings.destroy');
        });
    });
