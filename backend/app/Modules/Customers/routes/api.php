<?php

use App\Modules\Customers\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('api/customers')->group(function () {
    Route::get('/',             [CustomerController::class, 'index']);
    Route::post('/',            [CustomerController::class, 'store']);
    Route::get('/search',       [CustomerController::class, 'search']);
    Route::get('/{id}',         [CustomerController::class, 'show']);
    Route::put('/{id}',         [CustomerController::class, 'update']);
    Route::delete('/{id}',      [CustomerController::class, 'destroy']);
    Route::get('/{id}/orders',  [CustomerController::class, 'orders']);
});
