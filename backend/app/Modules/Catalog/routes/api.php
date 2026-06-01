<?php

use App\Modules\Catalog\Http\Controllers\CatalogController;
use App\Modules\Catalog\Http\Controllers\CatalogVariantController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\LabelController;
use App\Modules\Catalog\Http\Controllers\ProductAttributeController;
use App\Modules\Catalog\Http\Controllers\ProductCodeController;
use App\Modules\Catalog\Http\Controllers\ProductVariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->name('catalog.')->group(function () {

    // ── Authenticated routes ──────────────────────────────────────────
    Route::middleware(['auth:sanctum', \App\Modules\Auth\Http\Middleware\EnsureUserBelongsToTenant::class])->group(function () {

        // ── READ routes (all authenticated roles) ─────────────────────

        // Products lookup by SKU (moved from public — requires auth)
        Route::get('products/sku/{sku}', [CatalogController::class, 'findBySku'])->name('products.by-sku');

        // Products — read
        Route::get('products',      [CatalogController::class, 'index'])->name('products.index');
        Route::get('products/{id}', [CatalogController::class, 'show'])->name('products.show');

        // QR codes and barcodes (raw SVG or JSON sheet)
        Route::get('products/{productId}/qrcode',  [ProductCodeController::class, 'qrCode'])->name('products.qrcode');
        Route::get('products/{productId}/barcode', [ProductCodeController::class, 'barcode'])->name('products.barcode');
        Route::get('products/{productId}/codes',   [ProductCodeController::class, 'sheet'])->name('products.codes');

        Route::get('products/{productId}/variants/{variantId}/qrcode',  [ProductCodeController::class, 'variantQrCode'])->name('variants.qrcode');
        Route::get('products/{productId}/variants/{variantId}/barcode', [ProductCodeController::class, 'variantBarcode'])->name('variants.barcode');

        // Étiquettes imprimables (read only)
        // ?format=thermal|a4sheet  ?copies=N  ?price=1  ?qr=1
        Route::get('products/{productId}/label',                      [LabelController::class, 'product'])->name('products.label');
        Route::get('products/{productId}/variants/{variantId}/label', [LabelController::class, 'variant'])->name('variants.label');

        // Categories — read
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

        // Cross-product variant browser (frontend "Variantes" tab)
        Route::get('variants',       [CatalogVariantController::class, 'index'])->name('variants.index');
        Route::get('variants/stats', [CatalogVariantController::class, 'stats'])->name('variants.stats');

        // Product attributes — read
        Route::get('products/{productId}/attributes', [ProductAttributeController::class, 'index'])->name('attributes.index');

        // ── WRITE routes (manager and admin only) ─────────────────────
        Route::middleware('role:manager|admin')->group(function () {

            // Products — write
            Route::post('products',                [CatalogController::class, 'store'])->name('products.store')->middleware('quota:products');
            Route::put('products/{id}',            [CatalogController::class, 'update'])->name('products.update');
            Route::patch('products/{id}/archive',  [CatalogController::class, 'archive'])->name('products.archive');
            Route::patch('products/{id}/activate', [CatalogController::class, 'activate'])->name('products.activate');

            // Product variants — write
            Route::post('products/{productId}/variants',              [ProductVariantController::class, 'store'])->name('variants.store');
            Route::put('products/{productId}/variants/{variantId}',   [ProductVariantController::class, 'update'])->name('variants.update');
            Route::delete('products/{productId}/variants/{variantId}',[ProductVariantController::class, 'destroy'])->name('variants.destroy');

            // Labels batch — write
            Route::post('products/labels/batch', [LabelController::class, 'batch'])->name('products.labels.batch');

            // Categories — write
            Route::post('categories',         [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{id}',     [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{id}',  [CategoryController::class, 'destroy'])->name('categories.destroy');

            // Product attributes & values — write
            Route::post('products/{productId}/attributes',                                   [ProductAttributeController::class, 'store'])->name('attributes.store');
            Route::put('products/{productId}/attributes/{attributeId}',                     [ProductAttributeController::class, 'update'])->name('attributes.update');
            Route::delete('products/{productId}/attributes/{attributeId}',                  [ProductAttributeController::class, 'destroy'])->name('attributes.destroy');
            Route::post('products/{productId}/attributes/{attributeId}/values',             [ProductAttributeController::class, 'addValue'])->name('attributes.values.store');
            Route::delete('products/{productId}/attributes/{attributeId}/values/{valueId}', [ProductAttributeController::class, 'removeValue'])->name('attributes.values.destroy');
        });
    });
});
