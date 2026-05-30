<?php

use App\Modules\Inventory\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('api/inventory')->group(function () {

    // ── Stock consultation ─────────────────────────────────────────────
    Route::get('stock',                              [InventoryController::class, 'index']);
    Route::get('stock/{productId}',                  [InventoryController::class, 'show']);
    Route::get('stock/{productId}/movements',        [InventoryController::class, 'movements']);
    Route::get('alerts',                             [InventoryController::class, 'alerts']);

    // ── Stock operations ───────────────────────────────────────────────
    Route::post('stock/{productId}/move-in',         [InventoryController::class, 'moveIn']);
    Route::post('stock/{productId}/move-out',        [InventoryController::class, 'moveOut']);
    Route::post('stock/{productId}/adjust',          [InventoryController::class, 'adjust']);

    // ── Scan-to-action (POS / Bluetooth scanner) ───────────────────────
    Route::post('scan',                              [InventoryController::class, 'scan']);

    // ── Batch operations ───────────────────────────────────────────────
    Route::post('deliveries',                        [InventoryController::class, 'receiveDelivery']);
    Route::post('count',                             [InventoryController::class, 'inventoryCount']);
});
