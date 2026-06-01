<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Services\PromotionService;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminPromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotions,
        private readonly AuditService     $audit,
    ) {}

    /** GET /api/admin/promotions */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->promotions->list(25);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** POST /api/admin/promotions */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'             => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/i', 'unique:promotions,code'],
            'description'      => ['nullable', 'string', 'max:255'],
            'discount_type'    => ['required', 'in:percent,fixed_cents'],
            'discount_value'   => ['required', 'integer', 'min:1'],
            'applicable_plans' => ['nullable', 'array'],
            'applicable_plans.*' => ['string'],
            'valid_from'       => ['nullable', 'date'],
            'valid_until'      => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses'         => ['nullable', 'integer', 'min:1'],
            'is_active'        => ['boolean'],
        ]);

        // Extra validation: percent must be ≤ 100
        if ($validated['discount_type'] === 'percent' && $validated['discount_value'] > 100) {
            return response()->json(['message' => 'La remise en pourcentage ne peut pas dépasser 100%.'], 422);
        }

        $promo = $this->promotions->create($validated);
        $this->audit->logCreated($request, $promo);

        return response()->json($promo, 201);
    }

    /** GET /api/admin/promotions/:id */
    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json($promotion->loadCount('uses as total_uses'));
    }

    /** PATCH /api/admin/promotions/:id */
    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validate([
            'description'      => ['nullable', 'string', 'max:255'],
            'discount_type'    => ['sometimes', 'in:percent,fixed_cents'],
            'discount_value'   => ['sometimes', 'integer', 'min:1'],
            'applicable_plans' => ['nullable', 'array'],
            'applicable_plans.*' => ['string'],
            'valid_from'       => ['nullable', 'date'],
            'valid_until'      => ['nullable', 'date'],
            'max_uses'         => ['nullable', 'integer', 'min:1'],
            'is_active'        => ['sometimes', 'boolean'],
        ]);

        if (
            ($validated['discount_type'] ?? $promotion->discount_type) === 'percent'
            && ($validated['discount_value'] ?? $promotion->discount_value) > 100
        ) {
            return response()->json(['message' => 'La remise en pourcentage ne peut pas dépasser 100%.'], 422);
        }

        $old   = $promotion->only(array_keys($validated));
        $fresh = $this->promotions->update($promotion, $validated);
        $this->audit->logUpdated($request, $fresh, $old);

        return response()->json($fresh);
    }

    /** DELETE /api/admin/promotions/:id */
    public function destroy(Request $request, Promotion $promotion): JsonResponse
    {
        if ($promotion->current_uses > 0) {
            // Soft deactivate rather than hard delete if already used
            $promotion->update(['is_active' => false]);
            $this->audit->logUpdated($request, $promotion, ['is_active' => true]);

            return response()->json(['message' => 'Promotion désactivée (déjà utilisée, suppression impossible).']);
        }

        $this->audit->logDeleted($request, $promotion);
        $promotion->delete();

        return response()->json(['message' => 'Promotion supprimée.']);
    }
}
