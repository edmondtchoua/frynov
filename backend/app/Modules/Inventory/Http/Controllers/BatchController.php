<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-6H — lots : réception, liste par produit, péremptions proches.
 */
class BatchController extends Controller
{
    public function __construct(private readonly BatchService $batches) {}

    /** POST /api/inventory/products/{productId}/batches — réceptionne un lot (produit `batch` only). */
    public function store(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $product = Product::where('tenant_id', $tenantId)->where('id', $productId)->first();
        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }
        if ($product->stock_tracking !== Product::STOCK_TRACKING_BATCH) {
            return response()->json(['message' => 'Ce produit ne gère pas de lots.'], 422);
        }

        $data = $request->validate([
            'batch_number'       => ['required', 'string', 'max:100',
                Rule::unique('product_batches')->where('tenant_id', $tenantId)->where('product_id', $productId)],
            'expiry_date'        => ['nullable', 'date'],
            'manufacturing_date' => ['nullable', 'date'],
            'variant_id'         => ['nullable', 'uuid', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id'       => ['nullable', 'uuid', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'quantity'           => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost_cents'    => ['nullable', 'integer', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ]);

        $batch = $this->batches->receive($tenantId, $productId, $data, $request->user()->id);

        return response()->json(['data' => $batch->toApiArray()], 201);
    }

    /** GET /api/inventory/products/{productId}/batches — lots du produit (FEFO). */
    public function index(Request $request, string $productId): JsonResponse
    {
        $batches = $this->batches->forProduct($request->user()->tenant_id, $productId)
            ->map(fn (ProductBatch $b) => $b->toApiArray());

        return response()->json(['data' => $batches, 'count' => $batches->count()]);
    }

    /** GET /api/inventory/batches/expiring?days=30 — lots actifs proches de la péremption. */
    public function expiring(Request $request): JsonResponse
    {
        $days = min(365, max(1, (int) $request->query('days', 30)));

        $batches = $this->batches->expiring($request->user()->tenant_id, $days)
            ->map(fn (ProductBatch $b) => array_merge($b->toApiArray(), [
                'days_left' => (int) ceil(now()->diffInDays($b->expiry_date, false)),
            ]));

        return response()->json(['data' => $batches, 'count' => $batches->count(), 'days' => $days]);
    }
}
