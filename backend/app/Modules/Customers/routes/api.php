<?php

use App\Modules\Customers\Http\Controllers\CustomersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('customerss', CustomersController::class);
});