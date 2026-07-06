<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Billing\Services\Psp\PspGateway;
use App\Modules\Billing\Services\SubscriptionChangeRequestService;
use App\Modules\Billing\Services\UpgradeQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Option — paiement automatisé (PSP). `initiate` crée la demande + un paiement PSP puis renvoie l'URL
 * de checkout ; le `webhook` (authentifié par le driver) confirme et ACTIVE automatiquement (réutilise
 * exactement la même approbation que le flux manuel). Désactivé par défaut (`billing.psp.enabled`).
 */
class PspController extends Controller
{
    public function __construct(
        private readonly UpgradeQuoteService              $quotes,
        private readonly SubscriptionChangeRequestService $changeRequests,
        private readonly ManualPaymentService             $manualPayments,
        private readonly PspGateway                       $gateway,
    ) {}

    /** POST /api/me/subscription/psp/initiate */
    public function initiate(Request $request): JsonResponse
    {
        if (! config('billing.psp.enabled')) {
            return response()->json(['message' => 'Le paiement automatisé n\'est pas activé.'], 503);
        }

        $request->validate([
            'plan_code'   => ['required', 'string', 'exists:plans,code'],
            'interval'    => ['required', 'in:monthly,yearly'],
            'quantity'    => ['nullable', 'integer', 'min:1', 'max:12'],
            'promo_code'  => ['nullable', 'string', 'max:32'],
            'market_code' => ['nullable', 'string', 'max:32'],
            'consent'     => ['accepted'],
        ]);

        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return response()->json(['message' => 'Tenant non trouvé.'], 422);
        }

        $plan  = Plan::where('code', $request->input('plan_code'))->selectable()->firstOrFail();
        $quote = $this->quotes->quote($tenant, $plan, $request->input('interval'),
            (int) $request->input('quantity', 1), $request->input('promo_code'), $request->input('market_code'));

        $reference = 'PSP-'.strtoupper(Str::random(18));

        $payment = DB::transaction(function () use ($request, $tenant, $plan, $quote, $reference) {
            $cr = $this->changeRequests->openForPayment(
                $tenant, $plan, $quote->interval, $request->input('promo_code'),
                $request->user(), $quote->quantity, null, $quote->market,
            );
            $payment = $this->manualPayments->submit(
                tenant: $tenant, plan: $plan, amountCents: max(1, $quote->netPayableMinor),
                currency: $quote->currency, paymentMethod: 'psp', proof: null, notes: 'PSP',
                promoCode: $request->input('promo_code'), marketHint: $quote->market,
                declaredInterval: $quote->interval, changeRequestId: $cr->id,
            );
            $payment->update(['psp_reference' => $reference]);

            return $payment;
        });

        $init = $this->gateway->initiate($reference, (int) $payment->amount_cents, $quote->currency);

        return response()->json([
            'change_request_id' => $payment->change_request_id,
            'reference'         => $init['reference'],
            'checkout_url'      => $init['checkout_url'],
        ], 201);
    }

    /** POST /api/webhooks/psp — appelé par le PSP (server-to-server), authentifié par le driver. */
    public function webhook(Request $request): JsonResponse
    {
        $verified = $this->gateway->verify($request->all());
        if ($verified === null) {
            return response()->json(['message' => 'Webhook invalide.'], 400);
        }

        // Statut non-succès → acquitté sans action (échec/annulation laissés en attente).
        if ($verified['status'] !== 'success') {
            return response()->json(['message' => 'ok']);
        }

        $payment = ManualPayment::withoutTenantScope()
            ->where('psp_reference', $verified['reference'])
            ->first();

        if ($payment && $payment->isPending()) {
            // Approbation SYSTÈME : réutilise exactement la logique du flux manuel.
            $system = User::where('is_super_admin', true)->orderBy('created_at')->first();
            if ($system) {
                $this->manualPayments->approve($payment, $system);
            }
        }

        return response()->json(['message' => 'ok']);
    }
}
