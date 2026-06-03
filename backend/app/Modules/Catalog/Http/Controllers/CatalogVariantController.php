<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Cross-product variant browser.
 * GET /api/catalog/variants — lists ALL variants for the tenant, paginated.
 * Used by the frontend "Variantes" tab in the unified catalog view.
 */
class CatalogVariantController extends Controller
{
    /** GET /api/catalog/variants */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = ProductVariant::whereHas('product', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with([
                'product:id,name,sku,status,category_id',
                'product.category:id,name',
                'attributeValues.attribute:id,name,code',
            ]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('product_variants.sku',    'like', "%{$search}%")  // BAS-0015-V1
                  ->orWhere('product_variants.label', 'like', "%{$search}%") // "30L / Rouge"
                  ->orWhereHas('product', fn ($pq) => $pq
                      ->where('name', 'like', "%{$search}%")     // "Bassine de cuisine"
                      ->orWhere('sku',  'like', "%{$search}%")   // "BAS-0015" ← parent SKU
                  );
            });
        }

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($status = $request->query('status')) {
            $query->whereHas('product', fn ($q) => $q->where('status', $status));
        }

        $variants = $query->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 50));

        return response()->json($variants);
    }

    /** GET /api/catalog/variants/stats — count by product */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $total = ProductVariant::whereHas('product', fn ($q) => $q->where('tenant_id', $tenantId))->count();

        $withProducts = Product::where('tenant_id', $tenantId)
            ->where('has_variants', true)
            ->withCount('variants')
            ->get(['id', 'name', 'sku'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'variants_count' => $p->variants_count]);

        return response()->json(['total' => $total, 'products_with_variants' => $withProducts]);
    }
}
