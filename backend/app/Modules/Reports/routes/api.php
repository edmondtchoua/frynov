<?php

use App\Modules\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'module:reports', 'role_or_permission:manager|admin|reports.export'])->prefix('reports')->group(function () {
    Route::get('dashboard',         [ReportController::class, 'dashboard']);
    Route::get('sales',             [ReportController::class, 'sales']);
    Route::get('stock',             [ReportController::class, 'stock']);
    Route::get('special-products',  [ReportController::class, 'specialProducts']); // RC-5G
    // RC-17 (M-2) — rapports d'analyse d'inventaire (auparavant code mort : service+tests sans route)
    Route::get('abc',               [ReportController::class, 'abc']);
    Route::get('inventory-kpis',    [ReportController::class, 'inventoryKpis']);
    Route::get('reconciliation',    [ReportController::class, 'reconciliation']);
});
