<?php

namespace App\Modules\Warranties\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Warranties\Models\WarrantyContract;
use App\Modules\Warranties\Models\WarrantyPolicy;
use App\Modules\Warranties\Services\WarrantyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-5D — politiques de garantie (réutilisables), rattachement produit et consultation des contrats.
 */
class WarrantyController extends Controller
{
    public function __construct(private readonly WarrantyService $warranties) {}

    /** GET /api/warranties/policies — politiques du tenant. */
    public function policies(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $policies = WarrantyPolicy::where('tenant_id', $tenantId)
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->latest()
            ->get()
            ->map(fn (WarrantyPolicy $p) => $p->toApiArray());

        return response()->json(['data' => $policies]);
    }

    /** POST /api/warranties/policies — crée une politique (manager/admin). */
    public function storePolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:600'],
            'duration_unit'   => ['nullable', Rule::in(WarrantyPolicy::UNITS)], // RC-6F — day|month|year
            'coverage'        => ['nullable', 'string', 'max:2000'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $policy = WarrantyPolicy::create([
            'tenant_id'       => $request->user()->tenant_id,
            'name'            => $data['name'],
            'duration_months' => $data['duration_months'],
            'duration_unit'   => $data['duration_unit'] ?? WarrantyPolicy::UNIT_MONTH,
            'coverage'        => $data['coverage'] ?? null,
            'is_active'       => $data['is_active'] ?? true,
        ]);

        return response()->json(['data' => $policy->toApiArray()], 201);
    }

    /** POST /api/warranties/contracts/{contractId}/extend — prolonge un contrat (RC-6F, manager/admin). */
    public function extend(Request $request, string $contractId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $contract = WarrantyContract::where('tenant_id', $tenantId)->where('id', $contractId)->first();
        if (! $contract) {
            return response()->json(['message' => 'Contrat de garantie introuvable.'], 404);
        }

        $data = $request->validate([
            'duration' => ['required', 'integer', 'min:1', 'max:600'],
            'unit'     => ['required', Rule::in(WarrantyPolicy::UNITS)],
            'reason'   => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->warranties->extend($contract, $data['duration'], $data['unit'], $data['reason'] ?? null, $request->user()->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $contract->fresh()->toApiArray()]);
    }

    /** POST /api/warranties/products/{productId}/policy — attache (ou détache) une politique (manager/admin). */
    public function attachToProduct(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $product = Product::where('tenant_id', $tenantId)->where('id', $productId)->first();
        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $data = $request->validate([
            'warranty_policy_id' => [
                'nullable', 'uuid',
                Rule::exists('warranty_policies', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        $product->update(['warranty_policy_id' => $data['warranty_policy_id'] ?? null]);

        return response()->json(['data' => ['id' => $product->id, 'warranty_policy_id' => $product->warranty_policy_id]]);
    }

    /** GET /api/warranties/orders/{orderId} — contrats de garantie générés pour une commande. */
    public function forOrder(Request $request, string $orderId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // Isolation : la commande doit appartenir au tenant (sinon 404, pas de fuite).
        $order = Order::where('tenant_id', $tenantId)->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        $contracts = $this->warranties->forOrder($tenantId, $orderId)
            ->map(fn (WarrantyContract $c) => array_merge($c->toApiArray(), [
                'product_name' => $c->product?->name,
                'policy_name'  => $c->policy?->name,
            ]));

        return response()->json(['data' => $contracts, 'count' => $contracts->count()]);
    }
}
