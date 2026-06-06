<?php

use App\Modules\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:reports', 'role:manager|admin'])->prefix('reports')->group(function () {
    Route::get('dashboard', [ReportController::class, 'dashboard']);
    Route::get('sales',     [ReportController::class, 'sales']);
    Route::get('stock',     [ReportController::class, 'stock']);
});
