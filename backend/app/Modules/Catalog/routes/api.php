<?php

use App\Modules\Catalog\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('catalogs', CatalogController::class);
});