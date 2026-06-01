<?php

use App\Modules\Inventory\Http\Controllers\FiscalPeriodController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\StockAdjustmentController;
use App\Modules\Inventory\Http\Controllers\StockTransferController;
use App\Modules\Inventory\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', \App\Modules\Auth\Http\Middleware\EnsureUserBelongsToTenant::class])->prefix('api/inventory')->group(function () {

    // ── Stock consultation ─────────────────────────────────────────────
    Route::get('stock',                              [InventoryController::class, 'index']);
    Route::get('stock/{productId}',                  [InventoryController::class, 'show']);
    Route::get('stock/{productId}/movements',        [InventoryController::class, 'movements']);
    Route::get('alerts',                             [InventoryController::class, 'alerts']);

    // ── Stock operations (manager/admin only) ─────────────────────────
    Route::middleware('role:manager|admin')->group(function () {
        Route::post('stock/{productId}/move-in',         [InventoryController::class, 'moveIn']);
        Route::post('stock/{productId}/move-out',        [InventoryController::class, 'moveOut']);
        Route::post('stock/{productId}/adjust',          [InventoryController::class, 'adjust']);
    });

    // ── Scan-to-action (POS / Bluetooth scanner) ───────────────────────
    Route::post('scan',                              [InventoryController::class, 'scan']);

    // ── Batch operations ───────────────────────────────────────────────
    Route::post('deliveries',                        [InventoryController::class, 'receiveDelivery']);
    Route::post('count',                             [InventoryController::class, 'inventoryCount']);

    // ── Stock adjustment requests (dual-approval workflow) ─────────────
    Route::get('adjustments',                        [StockAdjustmentController::class, 'pending']);
    Route::get('adjustments/history',                [StockAdjustmentController::class, 'history']);
    Route::post('adjustments',                       [StockAdjustmentController::class, 'request']);
    Route::middleware('role:manager|admin')->group(function () {
        Route::post('adjustments/{id}/approve',      [StockAdjustmentController::class, 'approve']);
        Route::post('adjustments/{id}/reject',       [StockAdjustmentController::class, 'reject']);
    });

    // ── Threshold configuration per product ───────────────────────────
    Route::patch('stock/{stockId}/threshold',        [InventoryController::class, 'updateThreshold']);

    // Warehouses
    Route::get('warehouses',                         [WarehouseController::class, 'index']);
    Route::get('warehouses/{id}/summary',            [InventoryController::class, 'warehouseSummary']);
    Route::middleware('role:manager|admin')->group(function () {
        Route::post('warehouses',                    [WarehouseController::class, 'store']);
        Route::patch('warehouses/{id}',              [WarehouseController::class, 'update']);
        Route::patch('warehouses/{id}/default',      [WarehouseController::class, 'setDefault']);
    });

    // ── Fiscal periods
    Route::get('fiscal-periods',              [FiscalPeriodController::class, 'index']);
    Route::post('fiscal-periods',             [FiscalPeriodController::class, 'store']);
    Route::post('fiscal-periods/{id}/lock',   [FiscalPeriodController::class, 'lock']);
    Route::get('fiscal-periods/{id}/verify',  [FiscalPeriodController::class, 'verify']);

    // ── Stock transfers (inter-warehouse)
    Route::get('transfers',                   [StockTransferController::class, 'index']);
    Route::get('transfers/{id}',              [StockTransferController::class, 'show']);
    Route::middleware('role:manager|admin')->group(function () {
        Route::post('transfers',                     [StockTransferController::class, 'store']);
        Route::post('transfers/{id}/ship',           [StockTransferController::class, 'ship']);
        Route::post('transfers/{id}/receive',        [StockTransferController::class, 'receive']);
        Route::post('transfers/{id}/resolve',        [StockTransferController::class, 'resolve']);
    });
});
