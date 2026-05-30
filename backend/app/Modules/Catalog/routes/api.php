<?php

use App\Modules\Catalog\Http\Controllers\CatalogController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductCodeController;
use App\Modules\Catalog\Http\Controllers\ProductVariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->name('catalog.')->group(function () {

    // ── Public product lookup by SKU (for POS scanner) ────────────────
    Route::get('products/sku/{sku}', [CatalogController::class, 'findBySku'])->name('products.by-sku');

    // ── Authenticated routes ──────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Products — CRUD
        Route::get('products',              [CatalogController::class, 'index'])->name('products.index');
        Route::post('products',             [CatalogController::class, 'store'])->name('products.store');
        Route::get('products/{id}',         [CatalogController::class, 'show'])->name('products.show');
        Route::put('products/{id}',         [CatalogController::class, 'update'])->name('products.update');
        Route::patch('products/{id}/archive',  [CatalogController::class, 'archive'])->name('products.archive');
        Route::patch('products/{id}/activate', [CatalogController::class, 'activate'])->name('products.activate');

        // Product variants
        Route::post('products/{productId}/variants',              [ProductVariantController::class, 'store'])->name('variants.store');
        Route::put('products/{productId}/variants/{variantId}',   [ProductVariantController::class, 'update'])->name('variants.update');
        Route::delete('products/{productId}/variants/{variantId}',[ProductVariantController::class, 'destroy'])->name('variants.destroy');

        // QR codes and barcodes
        Route::get('products/{productId}/qrcode',  [ProductCodeController::class, 'qrCode'])->name('products.qrcode');
        Route::get('products/{productId}/barcode', [ProductCodeController::class, 'barcode'])->name('products.barcode');
        Route::get('products/{productId}/codes',   [ProductCodeController::class, 'sheet'])->name('products.codes');

        Route::get('products/{productId}/variants/{variantId}/qrcode',  [ProductCodeController::class, 'variantQrCode'])->name('variants.qrcode');
        Route::get('products/{productId}/variants/{variantId}/barcode', [ProductCodeController::class, 'variantBarcode'])->name('variants.barcode');

        // Categories
        Route::get('categories',            [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories',           [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{id}',       [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{id}',    [CategoryController::class, 'destroy'])->name('categories.destroy');
    });
});
