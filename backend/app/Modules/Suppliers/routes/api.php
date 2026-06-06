<?php

use App\Modules\Suppliers\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:suppliers'])->prefix('api/suppliers')->group(function () {
    Route::get('/',         [SupplierController::class, 'index']);
    Route::post('/',        [SupplierController::class, 'store']);
    Route::get('/search',   [SupplierController::class, 'search']);
    Route::get('/{id}',     [SupplierController::class, 'show']);
    Route::put('/{id}',     [SupplierController::class, 'update']);

    Route::middleware(['role:manager|admin'])->group(function () {
        Route::delete('/{id}',  [SupplierController::class, 'destroy']);
    });
});
