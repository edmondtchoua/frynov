<?php

namespace App\Modules\Warranties\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Warranties\Exceptions\ClaimStateException;
use App\Modules\Warranties\Exceptions\OutOfWarrantyException;
use App\Modules\Warranties\Models\WarrantyClaim;
use App\Modules\Warranties\Models\WarrantyContract;
use App\Modules\Warranties\Services\WarrantyClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-5F — réclamations SAV : ouverture (gardée par la période), consultation, transitions de statut.
 */
class WarrantyClaimController extends Controller
{
    public function __construct(private readonly WarrantyClaimService $claims) {}

    /** POST /api/warranties/contracts/{contractId}/claims — ouvre une réclamation (manager/admin). */
    public function store(Request $request, string $contractId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $contract = WarrantyContract::where('tenant_id', $tenantId)->where('id', $contractId)->first();
        if (! $contract) {
            return response()->json(['message' => 'Contrat de garantie introuvable.'], 404);
        }

        $data = $request->validate([
            'reason'      => ['required', Rule::in(WarrantyClaim::REASONS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'override'    => ['nullable', 'boolean'],
        ]);

        try {
            $claim = $this->claims->open($contract, $data, $request->user()->id);
        } catch (OutOfWarrantyException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $claim->toApiArray()], 201);
    }

    /** GET /api/warranties/contracts/{contractId}/claims — réclamations d'un contrat. */
    public function indexForContract(Request $request, string $contractId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $contract = WarrantyContract::where('tenant_id', $tenantId)->where('id', $contractId)->first();
        if (! $contract) {
            return response()->json(['message' => 'Contrat de garantie introuvable.'], 404);
        }

        $claims = $this->claims->forContract($tenantId, $contractId)
            ->map(fn (WarrantyClaim $c) => $c->toApiArray());

        return response()->json(['data' => $claims, 'count' => $claims->count()]);
    }

    /** GET /api/warranties/orders/{orderId}/claims — réclamations rattachées à une commande. */
    public function indexForOrder(Request $request, string $orderId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $order = Order::where('tenant_id', $tenantId)->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        $claims = $this->claims->forOrder($tenantId, $orderId)
            ->map(fn (WarrantyClaim $c) => $c->toApiArray());

        return response()->json(['data' => $claims, 'count' => $claims->count()]);
    }

    /** POST /api/warranties/claims/{id}/transition — fait avancer une réclamation (manager/admin). */
    public function transition(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $claim = WarrantyClaim::where('tenant_id', $tenantId)->where('id', $id)->first();
        if (! $claim) {
            return response()->json(['message' => 'Réclamation introuvable.'], 404);
        }

        $data = $request->validate([
            'status'          => ['required', Rule::in([
                WarrantyClaim::STATUS_IN_REPAIR, WarrantyClaim::STATUS_RESOLVED,
                WarrantyClaim::STATUS_REPLACED, WarrantyClaim::STATUS_REJECTED,
            ])],
            'diagnostic'      => ['nullable', 'string', 'max:2000'],
            'resolution'      => ['nullable', 'string', 'max:32'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->claims->transition($claim, $data['status'], $data, $request->user()->id);
        } catch (ClaimStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $claim->fresh()->toApiArray()]);
    }
}
