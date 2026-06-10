<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CatalogResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogService;
use App\Modules\Catalog\Services\ProductDuplicationService;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Inventory\Support\WarehouseScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    /** Whitelists des politiques produit (RC-5A) — alignées sur les constantes du modèle Product. */
    private const PRODUCT_TYPES = [
        Product::TYPE_SIMPLE, Product::TYPE_VARIABLE, Product::TYPE_SERVICE,
        Product::TYPE_KIT, Product::TYPE_DIGITAL,
    ];
    private const STOCK_TRACKINGS = [
        Product::STOCK_TRACKING_NONE, Product::STOCK_TRACKING_AGGREGATE,
        Product::STOCK_TRACKING_BATCH, Product::STOCK_TRACKING_SERIALIZED,
    ];
    private const FULFILLMENT_TYPES = [
        Product::FULFILLMENT_NONE, Product::FULFILLMENT_MANUAL, Product::FULFILLMENT_DELIVERY,
        Product::FULFILLMENT_DOWNLOAD, Product::FULFILLMENT_LICENSE, Product::FULFILLMENT_APPOINTMENT,
    ];

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
     * GET /api/catalog/products/{id}/variant-stock-matrix
     * Matrice d'entrée de stock « best-ERP » : lignes = variantes (ou le produit simple),
     * colonnes = entrepôts accessibles, cellule = stock courant (quantité/dispo/CMUP). Sert à
     * peupler la grille de saisie multi-variantes × entrepôt (RC-4).
     */
    public function variantStockMatrix(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }
        if (! $product->isStockable()) {
            return response()->json(['message' => 'Ce produit ne gère pas de stock.'], 422);
        }

        // Entrepôts visibles par l'utilisateur (périmètre d'accès). null = tous ceux du tenant.
        $allowed = WarehouseScope::resolve($request->user(), $request->query('warehouse_id'));
        $whQuery = Warehouse::where('tenant_id', $tenantId)->where('is_active', true);
        if ($allowed !== null) {
            $whQuery->whereIn('id', $allowed);
        }
        $warehouses = $whQuery->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'code', 'is_default']);

        // Index des lignes de stock par (variant|warehouse) pour un remplissage O(1).
        $stocks = Stock::where('tenant_id', $tenantId)
            ->where('product_id', $id)
            ->get()
            ->keyBy(fn ($s) => ($s->variant_id ?? 'null') . '|' . ($s->warehouse_id ?? 'null'));

        $cellFor = function (?string $variantId, ?string $warehouseId) use ($stocks): array {
            $row = $stocks->get(($variantId ?? 'null') . '|' . ($warehouseId ?? 'null'));
            return [
                'quantity'        => (int) ($row->quantity ?? 0),
                'available'       => (int) ($row?->available() ?? 0),
                'unit_cost_cents' => (int) ($row->unit_cost_cents ?? 0),
            ];
        };

        $buildCells = function (?string $variantId) use ($warehouses, $cellFor): array {
            $cells = [];
            foreach ($warehouses as $wh) {
                $cells[$wh->id] = $cellFor($variantId, $wh->id);
            }
            return $cells;
        };

        $rows = [];
        if ($product->has_variants) {
            foreach ($product->variants()->where('is_active', true)->orderBy('sort_order')->get() as $v) {
                $rows[] = [
                    'variant_id' => $v->id,
                    'label'      => $v->label ?: ($v->name ?: $v->sku),
                    'sku'        => $v->sku,
                    'cells'      => $buildCells($v->id),
                ];
            }
        } else {
            $rows[] = [
                'variant_id' => null,
                'label'      => $product->name,
                'sku'        => $product->sku,
                'cells'      => $buildCells(null),
            ];
        }

        return response()->json([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'has_variants' => (bool) $product->has_variants,
            'warehouses'   => $warehouses->map(fn ($w) => [
                'id'         => $w->id,
                'name'       => $w->name,
                'code'       => $w->code,
                'is_default' => (bool) $w->is_default,
            ])->values(),
            'rows'         => $rows,
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

        // A provided warehouse must belong to this tenant (prevents cross-tenant injection).
        // When omitted, the service resolves the tenant's default warehouse.
        $warehouseId = ! empty($data['warehouse_id']) ? $data['warehouse_id'] : null;
        if ($warehouseId !== null) {
            $owns = \App\Modules\Inventory\Models\Warehouse::where('tenant_id', $tenantId)
                ->where('id', $warehouseId)->exists();
            if (! $owns) {
                return response()->json(['message' => 'Entrepôt introuvable.'], 404);
            }
        }

        // Pass warehouse_id INTO the unique-key match so the right row is found/created
        // (the index is tenant+warehouse+product+variant). Never relocate an existing row.
        $stock = $stockService->findOrCreate(
            $tenantId,
            $id,
            $data['variant_id'] ?? null,
            $warehouseId,
        );

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
            'product_type'            => ['nullable', Rule::in(self::PRODUCT_TYPES)],
            'stock_tracking'          => ['nullable', Rule::in(self::STOCK_TRACKINGS)],
            'fulfillment_type'        => ['nullable', Rule::in(self::FULFILLMENT_TYPES)],
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
            'product_type'            => ['sometimes', Rule::in(self::PRODUCT_TYPES)],
            'stock_tracking'          => ['sometimes', Rule::in(self::STOCK_TRACKINGS)],
            'fulfillment_type'        => ['sometimes', Rule::in(self::FULFILLMENT_TYPES)],
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
