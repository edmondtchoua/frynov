<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\CatalogService;
use App\Modules\Catalog\Services\ProductCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ProductCodeController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ProductCodeService $codeService,
    ) {}

    /** GET /api/catalog/products/{product}/qrcode — returns SVG QR code directly. */
    public function qrCode(Request $request, string $productId): Response|JsonResponse
    {
        $product = $this->resolveProduct($request, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $size = (int) $request->query('size', 200);
        $svg  = $this->codeService->qrCode($product, max(50, min(1000, $size)));

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    /** GET /api/catalog/products/{product}/barcode?type=code128 — returns SVG barcode directly. */
    public function barcode(Request $request, string $productId): Response|JsonResponse
    {
        $product = $this->resolveProduct($request, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $type = $request->query('type', 'code128');
        $svg  = $this->codeService->barcode($product, $type);

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    /** GET /api/catalog/products/{product}/codes — returns JSON with both QR and barcode as SVG strings. */
    public function sheet(Request $request, string $productId): JsonResponse
    {
        $product = $this->resolveProduct($request, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        return response()->json(['data' => $this->codeService->sheet($product)]);
    }

    /** GET /api/catalog/products/{product}/variants/{variant}/qrcode */
    public function variantQrCode(Request $request, string $productId, string $variantId): Response|JsonResponse
    {
        $variant = $this->resolveVariant($request, $productId, $variantId);

        if (! $variant) {
            return response()->json(['message' => 'Variante introuvable.'], 404);
        }

        $svg = $this->codeService->qrCode($variant);

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    /** GET /api/catalog/products/{product}/variants/{variant}/barcode */
    public function variantBarcode(Request $request, string $productId, string $variantId): Response|JsonResponse
    {
        $variant = $this->resolveVariant($request, $productId, $variantId);

        if (! $variant) {
            return response()->json(['message' => 'Variante introuvable.'], 404);
        }

        $svg = $this->codeService->barcode($variant);

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    private function resolveProduct(Request $request, string $id): ?Product
    {
        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-ID');

        return $this->catalog->findProduct($tenantId, $id);
    }

    private function resolveVariant(Request $request, string $productId, string $variantId): ?ProductVariant
    {
        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-ID');
        $product  = $this->catalog->findProduct($tenantId, $productId);

        if (! $product) {
            return null;
        }

        return ProductVariant::where('product_id', $product->id)
            ->where('tenant_id', $tenantId)
            ->find($variantId);
    }
}
