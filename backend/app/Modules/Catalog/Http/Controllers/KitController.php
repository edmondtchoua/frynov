<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\KitComponent;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\KitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-6I — nomenclature d'un kit : consultation et définition (produit `kit` uniquement).
 */
class KitController extends Controller
{
    public function __construct(private readonly KitService $kits) {}

    /** GET /api/catalog/products/{productId}/components */
    public function index(Request $request, string $productId): JsonResponse
    {
        $components = $this->kits->componentsFor($request->user()->tenant_id, $productId)
            ->map(fn (KitComponent $c) => $c->toApiArray());

        return response()->json(['data' => $components, 'count' => $components->count()]);
    }

    /** PUT /api/catalog/products/{productId}/components — remplace la nomenclature (manager/admin). */
    public function upsert(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $kit = Product::where('tenant_id', $tenantId)->where('id', $productId)->first();
        if (! $kit) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }
        if ($kit->product_type !== Product::TYPE_KIT) {
            return response()->json(['message' => 'Seul un produit de type kit porte une nomenclature.'], 422);
        }

        $data = $request->validate([
            'components'               => ['required', 'array', 'min:1', 'max:50'],
            'components.*.product_id'  => ['required', 'uuid', 'different:' . $productId,
                Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'components.*.variant_id'  => ['nullable', 'uuid', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'components.*.quantity'    => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $components = $this->kits->setComponents($tenantId, $kit, $data['components'])
            ->map(fn (KitComponent $c) => $c->toApiArray());

        return response()->json(['data' => $components, 'count' => $components->count()]);
    }
}
