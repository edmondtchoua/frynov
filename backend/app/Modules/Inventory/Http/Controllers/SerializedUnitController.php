<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Exceptions\DuplicateSerialException;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Services\InventoryUnitService;
use App\Modules\Inventory\Support\WarehouseScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-5B — réception, listing et recherche d'unités sérialisées (IMEI/VIN…).
 */
class SerializedUnitController extends Controller
{
    public function __construct(private readonly InventoryUnitService $units) {}

    /** POST /api/inventory/products/{productId}/units — réceptionne des unités sérialisées. */
    public function store(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $product = Product::where('tenant_id', $tenantId)->where('id', $productId)->first();
        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }
        if ($product->stock_tracking !== Product::STOCK_TRACKING_SERIALIZED) {
            return response()->json(['message' => 'Ce produit ne gère pas d\'unités sérialisées.'], 422);
        }

        $data = $request->validate([
            'items'                   => ['required', 'array', 'min:1', 'max:200'],
            'items.*.serial_type'     => ['required', 'string', 'max:32'],
            'items.*.serial_value'    => ['required', 'string', 'max:120'],
            'items.*.variant_id'      => ['nullable', 'uuid', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'items.*.warehouse_id'    => ['nullable', 'uuid', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'items.*.condition'       => ['nullable', Rule::in([
                InventoryUnit::CONDITION_NEW, InventoryUnit::CONDITION_USED,
                InventoryUnit::CONDITION_REFURBISHED, InventoryUnit::CONDITION_DAMAGED,
            ])],
            'items.*.unit_cost_cents' => ['nullable', 'integer', 'min:0'],
            'items.*.notes'           => ['nullable', 'string', 'max:500'],
        ]);

        // Périmètre d'accès : un opérateur restreint ne réceptionne que dans ses entrepôts.
        $allowed = WarehouseScope::resolve($request->user(), null);
        if ($allowed !== null) {
            foreach ($data['items'] as $item) {
                $wh = $item['warehouse_id'] ?? null;
                if ($wh !== null && ! in_array($wh, $allowed, true)) {
                    return response()->json(['message' => "Vous n'avez pas accès à cet entrepôt."], 403);
                }
            }
        }

        try {
            $created = $this->units->registerMany($tenantId, $productId, $data['items'], $request->user()->id);
        } catch (DuplicateSerialException $e) {
            return response()->json([
                'message'      => $e->getMessage(),
                'serial_type'  => $e->serialType,
                'serial_value' => $e->serialValue,
            ], 422);
        }

        return response()->json([
            'data'  => array_map(fn (InventoryUnit $u) => $u->toApiArray(), $created),
            'count' => count($created),
        ], 201);
    }

    /** GET /api/inventory/products/{productId}/units — liste les unités d'un produit (scopée). */
    public function index(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = InventoryUnit::where('tenant_id', $tenantId)->where('product_id', $productId);

        $warehouseIds = WarehouseScope::resolve($request->user(), $request->query('warehouse_id'));
        if ($warehouseIds !== null) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $units = $query->latest()->paginate(50);
        $units->getCollection()->transform(fn (InventoryUnit $u) => $u->toApiArray());

        return response()->json($units);
    }

    /** GET /api/inventory/units/search?type=imei&serial=... — retrouve une unité par identifiant. */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'type'   => ['required', 'string', 'max:32'],
            'serial' => ['required', 'string', 'max:120'],
        ]);

        $unit = $this->units->findBySerial(
            $request->user()->tenant_id,
            $request->query('type'),
            $request->query('serial'),
        );

        if (! $unit) {
            return response()->json(['message' => 'Aucune unité trouvée pour cet identifiant.'], 404);
        }

        return response()->json([
            'data' => array_merge($unit->toApiArray(), [
                'product_name' => $unit->product?->name,
                'variant_label' => $unit->variant?->label,
            ]),
        ]);
    }
}
