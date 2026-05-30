<?php

use App\Modules\Inventory\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('inventorys', InventoryController::class);
});