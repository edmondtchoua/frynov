<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductVariantController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function store(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $data = $request->validate([
            'sku'            => ['nullable', 'string', 'max:100'],
            'name'           => ['nullable', 'string', 'max:255'],
            'attributes'     => ['nullable', 'array'],
            'price_amount'   => ['nullable', 'integer', 'min:0'],
            'price_currency' => ['nullable', 'string', 'size:3'],
            'cost_amount'    => ['nullable', 'integer', 'min:0'],
            'barcode'        => ['nullable', 'string', 'max:50'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $variant = $this->catalog->createVariant($product, $data);

        return response()->json(['data' => new ProductVariantResource($variant->load('product'))], 201);
    }

    public function update(Request $request, string $productId, string $variantId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('tenant_id', $tenantId)
            ->find($variantId);

        if (! $variant) {
            return response()->json(['message' => 'Variante introuvable.'], 404);
        }

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'attributes'     => ['nullable', 'array'],
            'price_amount'   => ['nullable', 'integer', 'min:0'],
            'price_currency' => ['nullable', 'string', 'size:3'],
            'barcode'        => ['nullable', 'string', 'max:50'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $variant->update($data);

        return response()->json(['data' => new ProductVariantResource($variant->fresh('product'))]);
    }

    public function destroy(Request $request, string $productId, string $variantId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('tenant_id', $tenantId)
            ->find($variantId);

        if (! $variant) {
            return response()->json(['message' => 'Variante introuvable.'], 404);
        }

        $variant->delete();

        return response()->json(['message' => 'Variante supprimée.']);
    }
}
