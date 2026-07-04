<?php

use App\Modules\Inventory\Http\Controllers\FiscalPeriodController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\SerializedUnitController;
use App\Modules\Inventory\Http\Controllers\StockAdjustmentController;
use App\Modules\Inventory\Http\Controllers\StockTransferController;
use App\Modules\Inventory\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', \App\Modules\Auth\Http\Middleware\EnsureUserBelongsToTenant::class, 'module:inventory'])->prefix('api/inventory')->group(function () {

    // ── Stock consultation ─────────────────────────────────────────────
    Route::get('stock',                              [InventoryController::class, 'index']);
    Route::get('stock/{productId}',                  [InventoryController::class, 'show']);
    Route::get('stock/{productId}/movements',        [InventoryController::class, 'movements']);
    Route::get('alerts',                             [InventoryController::class, 'alerts']);

    // ── Unités sérialisées (RC-5B) — consultation + recherche par IMEI/VIN ──
    Route::get('products/{productId}/units',         [SerializedUnitController::class, 'index']);
    Route::get('units/search',                       [SerializedUnitController::class, 'search']);

    // ── Lots & péremption (RC-6H) ──────────────────────────────────────
    Route::get('products/{productId}/batches',       [\App\Modules\Inventory\Http\Controllers\BatchController::class, 'index']);
    Route::get('batches/expiring',                   [\App\Modules\Inventory\Http\Controllers\BatchController::class, 'expiring']);

    // ── Définitions d'identifiants métier (RC-6D) — fusion globales + tenant ──
    Route::get('special-attributes',                 [\App\Modules\Inventory\Http\Controllers\SpecialAttributeController::class, 'index']);
    Route::middleware('role_or_permission:manager|admin')->group(function () {
        Route::post('special-attributes',            [\App\Modules\Inventory\Http\Controllers\SpecialAttributeController::class, 'store']);
        Route::patch('special-attributes/{id}',      [\App\Modules\Inventory\Http\Controllers\SpecialAttributeController::class, 'update']);
    });

    // ── Stock operations (manager/admin only) ─────────────────────────
    Route::middleware('role_or_permission:manager|admin|inventory.adjust|inventory.receive')->group(function () {
        Route::post('stock/{productId}/move-in',         [InventoryController::class, 'moveIn']);
        Route::post('stock/{productId}/move-out',        [InventoryController::class, 'moveOut']);
        Route::post('stock/{productId}/adjust',          [InventoryController::class, 'adjust']);

        // ── Scan-to-action (POS / Bluetooth scanner) ───────────────────
        Route::post('scan',                              [InventoryController::class, 'scan']);

        // ── Batch operations ────────────────────────────────────────────
        Route::post('deliveries',                        [InventoryController::class, 'receiveDelivery']);
        Route::post('count',                             [InventoryController::class, 'inventoryCount']);

        // ── Réception d'unités sérialisées (IMEI/VIN…) ─────────────────
        Route::post('products/{productId}/units',        [SerializedUnitController::class, 'store']);

        // ── Réception par lot (RC-6H) ──────────────────────────────────
        Route::post('products/{productId}/batches',      [\App\Modules\Inventory\Http\Controllers\BatchController::class, 'store']);
    });

    // ── Stock adjustment requests (dual-approval workflow) ─────────────
    Route::get('adjustments',                        [StockAdjustmentController::class, 'pending']);
    Route::get('adjustments/history',                [StockAdjustmentController::class, 'history']);
    Route::post('adjustments',                       [StockAdjustmentController::class, 'request']);
    Route::middleware('role_or_permission:manager|admin|inventory.audit')->group(function () {
        Route::post('adjustments/{id}/approve',      [StockAdjustmentController::class, 'approve']);
        Route::post('adjustments/{id}/reject',       [StockAdjustmentController::class, 'reject']);
    });

    // ── Threshold configuration per product ───────────────────────────
    Route::middleware('role_or_permission:manager|admin|inventory.adjust')->group(function () {
        Route::patch('stock/{stockId}/threshold',    [InventoryController::class, 'updateThreshold']);
    });

    // Warehouses
    Route::get('warehouses',                         [WarehouseController::class, 'index']);
    Route::get('warehouses/{id}/summary',            [InventoryController::class, 'warehouseSummary']);
    Route::middleware('role_or_permission:manager|admin|inventory.audit')->group(function () {
        Route::post('warehouses',                    [WarehouseController::class, 'store']);
        Route::patch('warehouses/{id}',              [WarehouseController::class, 'update']);
        Route::patch('warehouses/{id}/default',      [WarehouseController::class, 'setDefault']);
    });

    // ── Fiscal periods
    Route::get('fiscal-periods',              [FiscalPeriodController::class, 'index']);
    Route::get('fiscal-periods/{id}/verify',  [FiscalPeriodController::class, 'verify']);
    Route::middleware('role_or_permission:manager|admin|inventory.audit')->group(function () {
        Route::post('fiscal-periods',             [FiscalPeriodController::class, 'store']);
        Route::post('fiscal-periods/{id}/lock',   [FiscalPeriodController::class, 'lock']);
    });

    // ── Stock transfers (inter-warehouse)
    Route::get('transfers',                   [StockTransferController::class, 'index']);
    Route::get('transfers/{id}',              [StockTransferController::class, 'show']);
    Route::middleware('role_or_permission:manager|admin|inventory.transfer')->group(function () {
        Route::post('transfers',                     [StockTransferController::class, 'store']);
        Route::post('transfers/{id}/ship',           [StockTransferController::class, 'ship']);
        Route::post('transfers/{id}/receive',        [StockTransferController::class, 'receive']);
        Route::post('transfers/{id}/resolve',        [StockTransferController::class, 'resolve']);
    });
});
