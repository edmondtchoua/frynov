<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Exceptions\InvalidPromoCodeException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Billing\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BillingController extends Controller
{
    public function __construct(
        private readonly PromotionService      $promotions,
        private readonly ManualPaymentService  $manualPayments,
    ) {}

    /**
     * POST /api/me/promo/validate
     * Validate a promo code without applying it yet (used for real-time feedback in UI).
     */
    public function validatePromo(Request $request): JsonResponse
    {
        $request->validate([
            'code'      => ['required', 'string'],
            'plan_code' => ['nullable', 'string'],
        ]);

        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json(['valid' => false, 'message' => 'Tenant non trouvé.'], 422);
        }

        try {
            $promo = $this->promotions->validate(
                $request->input('code'),
                $tenant,
                $request->input('plan_code'),
            );

            return response()->json([
                'valid'          => true,
                'code'           => $promo->code,
                'discount_type'  => $promo->discount_type,
                'discount_value' => $promo->discount_value,
                'description'    => $promo->description,
            ]);
        } catch (InvalidPromoCodeException $e) {
            return response()->json(['valid' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/me/promo/apply
     * Apply a validated promo code (records usage).
     */
    public function applyPromo(Request $request): JsonResponse
    {
        $request->validate([
            'code'      => ['required', 'string'],
            'plan_code' => ['nullable', 'string'],
        ]);

        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant non trouvé.'], 422);
        }

        try {
            $promo = $this->promotions->validate(
                $request->input('code'),
                $tenant,
                $request->input('plan_code'),
            );

            $this->promotions->recordUse($promo, $tenant);

            return response()->json([
                'message'        => 'Code promotionnel appliqué avec succès.',
                'discount_type'  => $promo->discount_type,
                'discount_value' => $promo->discount_value,
            ]);
        } catch (InvalidPromoCodeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Manual payment proof ───────────────────────────────────────────────────

    /**
     * POST /api/me/manual-payments
     * Submit a payment proof for a plan upgrade request.
     */
    public function submitPayment(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code'      => ['required', 'string', 'exists:plans,code'],
            'amount_cents'   => ['required', 'integer', 'min:1'],
            'currency'       => ['sometimes', 'string', 'max:8'],
            'payment_method' => ['required', 'string', 'max:64'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'promo_code'     => ['nullable', 'string', 'max:32'],
            // RC-1C — hint marché (validé serveur-side vs devise) + périodicité déclarée (repli d'acompte)
            'market_code'    => ['nullable', 'string', 'max:32'],
            'interval'       => ['nullable', 'in:monthly,yearly'],
            'proof'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'], // 5 MB
        ]);

        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant non trouvé.'], 422);
        }

        $plan = Plan::where('code', $request->input('plan_code'))->firstOrFail();

        $payment = $this->manualPayments->submit(
            tenant:           $tenant,
            plan:             $plan,
            amountCents:      $request->input('amount_cents'),
            currency:         $request->input('currency', 'XOF'),
            paymentMethod:    $request->input('payment_method'),
            proof:            $request->file('proof'),
            notes:            $request->input('notes'),
            promoCode:        $request->input('promo_code'),
            marketHint:       $request->input('market_code'),
            declaredInterval: $request->input('interval'),
        );

        return response()->json([
            'message' => 'Demande soumise. Un administrateur va valider votre paiement.',
            'id'      => $payment->id,
        ], 201);
    }

    /**
     * GET /api/me/manual-payments
     * List the current tenant's payment requests.
     */
    public function listPayments(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json(['data' => []]);
        }

        $payments = $this->manualPayments->forTenant($tenant);

        return response()->json([
            'data' => $payments->map(fn ($p) => $p->toApiArray())->all(),
        ]);
    }
}
