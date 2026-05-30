<?php

use App\Modules\Orders\Http\Controllers\OrdersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('orderss', OrdersController::class);
});