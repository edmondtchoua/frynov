<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CatalogResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogService;
use App\Modules\Catalog\Services\ProductDuplicationService;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ?StockService $stockService = null,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $request->user()->tenant_id;

        $products = $this->catalog->listProducts($tenantId, $request->only([
            'status', 'category_id', 'search', 'per_page',
        ]));

        return CatalogResource::collection($products);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // ?detail=1 loads supplier + attributes for the show page
        if ($request->boolean('detail')) {
            $product = $this->catalog->findProductDetail($tenantId, $id);
        } else {
            $product = $this->catalog->findProduct($tenantId, $id);
        }

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        return response()->json(['data' => new CatalogResource($product)]);
    }

    /**
     * GET /api/catalog/products/{id}/stock-summary
     * Returns aggregated stock across all warehouses + per-warehouse breakdown.
     * Used by the product show page Overview tab.
     */
    public function stockSummary(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        // Aggregate stock across all warehouses (incl. per-variant)
        $stocks = Stock::where('tenant_id', $tenantId)
            ->where('product_id', $id)
            ->with('warehouse:id,name,code,type')
            ->get();

        $total     = $stocks->sum('quantity');
        $reserved  = $stocks->sum('reserved_quantity');
        $available = $stocks->sum(fn ($s) => $s->available());
        $lowStock  = $stocks->filter(fn ($s) => $s->isLowStock())->count();

        // Per-warehouse summary (product-level stock only)
        $byWarehouse = $stocks->filter(fn ($s) => $s->variant_id === null)
            ->map(fn ($s) => [
                'warehouse_id'      => $s->warehouse_id,
                'warehouse_name'    => $s->warehouse?->name ?? 'Sans entrepôt',
                'quantity'          => $s->quantity,
                'reserved'          => $s->reserved_quantity,
                'available'         => $s->available(),
                'low_stock'         => $s->isLowStock(),
                'unit_cost_cents'   => $s->unit_cost_cents,
                'total_value_cents' => $s->total_value_cents,
            ])->values();

        // Per-variant stock: aggregate across all warehouses per variant
        $byVariant = $stocks->filter(fn ($s) => $s->variant_id !== null)
            ->groupBy('variant_id')
            ->map(fn ($variantStocks, $variantId) => [
                'variant_id' => $variantId,
                'quantity'   => $variantStocks->sum('quantity'),
                'reserved'   => $variantStocks->sum('reserved_quantity'),
                'available'  => $variantStocks->sum(fn ($s) => $s->available()),
                'low_stock'  => $variantStocks->contains(fn ($s) => $s->isLowStock()),
            ])->values();

        return response()->json([
            'total_quantity'     => $total,
            'reserved_quantity'  => $reserved,
            'available_quantity' => $available,
            'low_stock_count'    => $lowStock,
            'by_warehouse'       => $byWarehouse,
            'by_variant'         => $byVariant,  // per-variant stock map
        ]);
    }

    /**
     * POST /api/catalog/products/{id}/initial-stock
     * Creates an initial stock entry (move-in) for a newly created product.
     * This creates a real StockMovement for traceability.
     * Only available for stockable products (not services).
     */
    public function initialStock(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        if ($product->isService()) {
            return response()->json(['message' => 'Les services ne gèrent pas de stock.'], 422);
        }

        $data = $request->validate([
            'quantity'        => ['required', 'integer', 'min:1'],
            'warehouse_id'    => ['nullable', 'uuid'],
            'unit_cost_cents' => ['nullable', 'integer', 'min:0'],
            'note'            => ['nullable', 'string', 'max:500'],
            'variant_id'      => ['nullable', 'uuid'],
        ]);

        /** @var StockService $stockService */
        $stockService = app(StockService::class);

        $stock = $stockService->findOrCreate(
            $tenantId,
            $id,
            $data['variant_id'] ?? null,
        );

        // Assign warehouse if provided
        if (! empty($data['warehouse_id'])) {
            $stock->update(['warehouse_id' => $data['warehouse_id']]);
            $stock->refresh();
        }

        $movement = $stockService->moveIn(
            $stock,
            $data['quantity'],
            StockMovement::REASON_MANUAL,
            'initial-stock',
            $data['note'] ?? 'Stock initial à la création du produit',
            $request->user()->id,
            $data['unit_cost_cents'] ?? 0,
        );

        return response()->json([
            'message'  => "Stock initial de {$data['quantity']} unité(s) créé avec succès.",
            'movement' => [
                'id'              => $movement->id,
                'type'            => $movement->type,
                'quantity'        => $movement->quantity,
                'quantity_before' => $movement->quantity_before,
                'quantity_after'  => $movement->quantity_after,
                'reason'          => $movement->reason,
            ],
            'stock' => [
                'id'        => $stock->fresh()->id,
                'quantity'  => $stock->fresh()->quantity,
                'available' => $stock->fresh()->available(),
            ],
        ], 201);
    }

    public function findBySku(Request $request, string $sku): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProductBySku($tenantId, $sku);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        return response()->json(['data' => new CatalogResource($product)]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'sku'                     => ['nullable', 'string', 'max:100',
                Rule::unique('products')->where('tenant_id', $tenantId)],
            'sku_prefix'              => ['nullable', 'string', 'max:5'],
            'description'             => ['nullable', 'string'],
            'price_amount'            => ['required', 'integer', 'min:0'],
            'price_currency'          => ['required', 'string', 'size:3'],
            'compare_at_price_amount' => ['nullable', 'integer', 'min:0'],
            'cost_amount'             => ['nullable', 'integer', 'min:0'],
            'status'                  => ['nullable', 'in:draft,active,archived'],
            'category_id'             => ['nullable', 'uuid'],
            'barcode'                 => ['nullable', 'string', 'max:50'],
            'internal_barcode'        => ['nullable', 'string', 'max:50'],
            'gtin'                    => ['nullable', 'string', 'max:20'],
            'barcode_type'            => ['nullable', 'in:INTERNAL,EAN13,UPC_A,GTIN_13,GTIN_14,CODE128,QR'],
            'weight_kg'               => ['nullable', 'numeric', 'min:0'],
            'metadata'                => ['nullable', 'array'],
            'has_variants'            => ['nullable', 'boolean'],
        ]);

        $product  = $this->catalog->createProduct($tenantId, $data);

        return response()->json(['data' => new CatalogResource($product)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $data = $request->validate([
            'name'                    => ['sometimes', 'string', 'max:255'],
            'description'             => ['nullable', 'string'],
            'price_amount'            => ['sometimes', 'integer', 'min:0'],
            'price_currency'          => ['sometimes', 'string', 'size:3'],
            'compare_at_price_amount' => ['nullable', 'integer', 'min:0'],
            'cost_amount'             => ['nullable', 'integer', 'min:0'],
            'status'                  => ['sometimes', 'in:draft,active,archived'],
            'category_id'             => ['nullable', 'uuid'],
            'barcode'                 => ['nullable', 'string', 'max:50'],
            'weight_kg'               => ['nullable', 'numeric', 'min:0'],
            'metadata'                => ['nullable', 'array'],
        ]);

        $product = $this->catalog->updateProduct($product, $data);

        return response()->json(['data' => new CatalogResource($product)]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $this->catalog->archiveProduct($product);

        return response()->json(['message' => 'Produit archivé.']);
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $product = $this->catalog->activateProduct($product);

        return response()->json(['data' => new CatalogResource($product)]);
    }

    /**
     * POST /api/catalog/products/{id}/duplicate-preview
     * Aperçu NON persisté de la duplication (politique §5bis) — pour le wizard.
     */
    public function duplicatePreview(Request $request, string $id): JsonResponse
    {
        $product = $this->catalog->findProduct($request->user()->tenant_id, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        return response()->json(['data' => app(ProductDuplicationService::class)->previewProduct($product)]);
    }

    /**
     * POST /api/catalog/products/{id}/duplicate
     * Duplique le produit (+ attributs + variantes) — SKU/identifiants régénérés, stock NON copié,
     * status draft, transactionnel. Cf. ProductDuplicationService.
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $product = $this->catalog->findProduct($request->user()->tenant_id, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $new = app(ProductDuplicationService::class)->duplicateProduct($product);

        return response()->json(['data' => new CatalogResource($new)], 201);
    }
}
