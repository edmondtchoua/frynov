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
                // NOTE: we use the attributes JSON blob (not the normalized pivot)
                // because product_variant_attr_values pivot may be empty for
                // variants created via the N-axis builder (JSON-only path).
                // attributeValues.attribute:id,name,code  ← removed (causes 500 when pivot empty + orderBy issue)
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

        $paginator = $query->orderBy('product_variants.created_at', 'desc')
            ->paginate((int) $request->query('per_page', 50));

        // Pre-load stock quantities for all variants on this page in ONE query
        $variantIds = $paginator->getCollection()->pluck('id')->toArray();
        $stocks = \App\Modules\Inventory\Models\Stock::whereIn('variant_id', $variantIds)
            ->select('variant_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as total_qty'), \Illuminate\Support\Facades\DB::raw('SUM(quantity - reserved_quantity) as available_qty'))
            ->groupBy('variant_id')
            ->get()
            ->keyBy('variant_id');

        // Transform each item: attribute_chips (JSON blob) + stock quantities
        $paginator->getCollection()->transform(function ($variant) use ($stocks) {
            // Attribute chips from JSON blob
            $attrs = $variant->attributes ?? [];
            if (is_string($attrs)) $attrs = json_decode($attrs, true) ?? [];
            $variant->attribute_chips = collect($attrs)->map(fn ($val, $key) => [
                'name'  => $key,
                'label' => $val,
            ])->values()->toArray();

            // Stock quantities (0 if no stock record yet)
            $stock = $stocks->get($variant->id);
            $variant->stock_qty       = (int) ($stock?->total_qty     ?? 0);
            $variant->stock_available = (int) ($stock?->available_qty ?? 0);

            return $variant;
        });

        return response()->json($paginator);
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
