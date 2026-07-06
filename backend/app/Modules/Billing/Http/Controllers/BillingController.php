<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Exceptions\InvalidPromoCodeException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Billing\Services\ConsentService;
use App\Modules\Billing\Services\DowngradeImpactService;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Billing\Services\PromotionService;
use App\Modules\Billing\Services\SubscriptionChangeRequestService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Services\UpgradeQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function __construct(
        private readonly PromotionService                 $promotions,
        private readonly ManualPaymentService             $manualPayments,
        private readonly SubscriptionService              $subscriptions,
        private readonly UpgradeQuoteService              $quotes,
        private readonly SubscriptionChangeRequestService $changeRequests,
        private readonly ConsentService                   $consents,
        private readonly DowngradeImpactService           $impacts,
    ) {}

    /**
     * POST /api/me/subscription/downgrade-impact
     * Aperçu (LECTURE SEULE) de l'impact d'un passage vers `plan_code` : modules retirés + quotas
     * dépassés (utilisateurs/produits/clients/entrepôts/commandes). Aucune donnée n'est supprimée.
     */
    public function downgradeImpact(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => ['required', 'string', 'exists:plans,code'],
        ]);

        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return response()->json(['message' => 'Tenant non trouvé.'], 422);
        }

        $plan = Plan::where('code', $request->input('plan_code'))->selectable()->firstOrFail();

        return response()->json($this->impacts->forPlan($tenant, $plan));
    }

    /**
     * GET /api/me/subscription/consent-text
     * Texte + version du consentement au changement de plan (source de vérité serveur, affiché
     * verbatim dans la case obligatoire du formulaire).
     */
    public function consentText(): JsonResponse
    {
        return response()->json([
            'version' => ConsentService::PLAN_CHANGE_VERSION,
            'text'    => ConsentService::PLAN_CHANGE_TEXT,
        ]);
    }

    /**
     * POST /api/me/subscription/calculate-upgrade
     * Devis AUTORITATIF (LECTURE SEULE) d'un changement de plan : brut serveur, remise promo validée,
     * avoir de proration, net à payer — le tout calculé serveur-side. Le front verrouille son champ
     * « montant » sur `net_payable_minor` ; aucune valeur client n'entre dans ce total (critère #22).
     */
    public function calculateUpgrade(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code'   => ['required', 'string', 'exists:plans,code'],
            'interval'    => ['required', 'in:monthly,yearly'],
            'quantity'    => ['nullable', 'integer', 'min:1', 'max:12'],
            'promo_code'  => ['nullable', 'string', 'max:32'],
            'market_code' => ['nullable', 'string', 'max:32'],
        ]);

        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return response()->json(['message' => 'Tenant non trouvé.'], 422);
        }

        $plan  = Plan::where('code', $request->input('plan_code'))->selectable()->firstOrFail();
        $quote = $this->quotes->quote(
            $tenant,
            $plan,
            $request->input('interval'),
            (int) $request->input('quantity', 1),
            $request->input('promo_code'),
            $request->input('market_code'),
        );

        return response()->json($quote->toArray());
    }

    /**
     * POST /api/me/subscription/preview-upgrade
     * Aperçu (LECTURE SEULE) du reliquat de proration pour un changement de plan : crédit du temps
     * non consommé, net à payer, avoir reporté. Ne persiste rien — recalculé au commit (approbation).
     */
    public function previewUpgrade(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => ['required', 'string', 'exists:plans,code'],
            'interval'  => ['required', 'in:monthly,yearly'],
        ]);

        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return response()->json(['message' => 'Tenant non trouvé.'], 422);
        }

        $plan = Plan::where('code', $request->input('plan_code'))->selectable()->firstOrFail();
        $result = $this->subscriptions->previewProration($tenant, $plan, $request->input('interval'));

        return response()->json($result->toArray());
    }

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
     * Confirme un code promo pour l'UI (validation + rappel de la remise).
     *
     * RC-18 (M-5) — NE consomme PLUS l'usage : `recordUse` ici créait un `PromoUse` immédiat que
     * `ManualPaymentService::approve` voyait ensuite comme « déjà utilisé » → le paiement légitime
     * du même tenant partait en `needs_review`. L'usage n'est enregistré qu'à l'ACTIVATION (approve).
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
            // P0 — le montant devient OPTIONNEL : par défaut on impose le net autoritatif du devis
            // serveur (parcours standard, champ verrouillé). Une valeur reste tolérée pour l'acompte
            // mobile money, mais la CIBLE (prix du plan) reste toujours dérivée serveur-side.
            'amount_cents'   => ['nullable', 'integer', 'min:1'],
            'currency'       => ['sometimes', 'string', 'max:8'],
            'payment_method' => ['required', 'string', 'max:64'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'promo_code'     => ['nullable', 'string', 'max:32'],
            // RC-1C — hint marché (validé serveur-side vs devise) + périodicité déclarée (repli d'acompte)
            'market_code'    => ['nullable', 'string', 'max:32'],
            'interval'       => ['nullable', 'in:monthly,yearly'],
            'quantity'       => ['nullable', 'integer', 'min:1', 'max:12'], // P0.1 — durée multi-période
            'effective'      => ['nullable', 'in:immediate,next_cycle'],     // P4 — prise d'effet
            'proof'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'], // 5 MB
            // P2 — consentement OBLIGATOIRE (case cochée) : sans lui, pas de soumission.
            'consent'        => ['accepted'],
        ], [
            'consent.accepted' => 'Vous devez accepter les conditions du changement de plan.',
        ]);

        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant non trouvé.'], 422);
        }

        $plan = Plan::where('code', $request->input('plan_code'))->selectable()->firstOrFail();

        // P0 — montant AUTORITATIF : on recalcule le devis serveur-side et on IGNORE le montant client
        // dès qu'il est absent (parcours verrouillé). La devise suit le marché résolu, jamais le client.
        $quote        = $this->quotes->quote(
            $tenant,
            $plan,
            $request->input('interval', 'monthly'),
            (int) $request->input('quantity', 1),
            $request->input('promo_code'),
            $request->input('market_code'),
        );
        // Défaut = net autoritatif ; plancher à 1 unité mineure (le résolveur classe un plan gratuit en
        // « free » quelle que soit la valeur, et `amount_cents` reste > 0).
        $clientAmount = $request->input('amount_cents');
        $amountCents  = $clientAmount !== null ? (int) $clientAmount : max(1, $quote->netPayableMinor);

        // P1 — la demande de changement (objet de premier plan) et son paiement (pièce) sont créés
        // ATOMIQUEMENT : la demande naît en `pending_validation`, le paiement s'y rattache.
        $payment = DB::transaction(function () use ($request, $tenant, $plan, $quote, $amountCents) {
            $changeRequest = $this->changeRequests->openForPayment(
                tenant:      $tenant,
                toPlan:      $plan,
                interval:    $quote->interval,
                promoCode:   $request->input('promo_code'),
                requestedBy: $request->user(),
                quantity:    $quote->quantity,
                notes:       $request->input('notes'),
                marketHint:  $quote->market,
                effective:   $request->input('effective', 'immediate'),
            );

            // P2 — trace le consentement (case cochée), lié à la demande, avec IP/user-agent/version.
            $this->consents->recordPlatform($request, $tenant, $request->user(), $changeRequest);

            return $this->manualPayments->submit(
                tenant:           $tenant,
                plan:             $plan,
                amountCents:      $amountCents,
                currency:         $quote->currency,
                paymentMethod:    $request->input('payment_method'),
                proof:            $request->file('proof'),
                notes:            $request->input('notes'),
                promoCode:        $request->input('promo_code'),
                marketHint:       $quote->market,
                declaredInterval: $quote->interval,
                changeRequestId:  $changeRequest->id,
            );
        });

        return response()->json([
            'message'           => 'Demande soumise. Un administrateur va valider votre paiement.',
            'id'                => $payment->id,
            'change_request_id' => $payment->change_request_id,
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

    // ── In-app notifications (P2b) ─────────────────────────────────────────────

    /**
     * GET /api/me/subscription/notifications
     * Fil in-app d'abonnement du tenant (scope tenant automatique), plus récent d'abord.
     */
    public function listNotifications(Request $request): JsonResponse
    {
        $items = \App\Modules\Billing\Models\SubscriptionNotification::latest()->limit(50)->get();

        return response()->json([
            'data'   => $items->map(fn ($n) => $n->toApiArray())->all(),
            'unread' => $items->where('is_read', false)->count(),
        ]);
    }

    /**
     * POST /api/me/subscription/notifications/{id}/read — marque une notification comme lue (anti-IDOR
     * via TenantScope : 404 pour un autre tenant).
     */
    public function readNotification(Request $request, string $id): JsonResponse
    {
        $notification = \App\Modules\Billing\Models\SubscriptionNotification::findOrFail($id);
        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'ok']);
    }

    // ── Change requests (P1) ───────────────────────────────────────────────────

    /**
     * GET /api/me/subscription/change-requests
     * Historique des demandes de changement de plan du tenant courant (scope tenant automatique).
     */
    public function listChangeRequests(Request $request): JsonResponse
    {
        $requests = SubscriptionChangeRequest::latest()->get();

        return response()->json([
            'data' => $requests->map(fn ($r) => $r->toApiArray())->all(),
        ]);
    }

    /**
     * GET /api/me/subscription/change-requests/{id}
     * Le TenantScope (fail-closed) renvoie 404 pour une demande d'un autre tenant (anti-IDOR).
     */
    public function showChangeRequest(Request $request, string $id): JsonResponse
    {
        $changeRequest = SubscriptionChangeRequest::findOrFail($id);

        return response()->json($changeRequest->toApiArray());
    }

    /**
     * POST /api/me/subscription/change-requests
     * Crée un BROUILLON de demande (futur assistant multi-étapes). Montants + snapshot figés serveur.
     */
    public function createChangeRequest(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code'   => ['required', 'string', 'exists:plans,code'],
            'interval'    => ['required', 'in:monthly,yearly'],
            'quantity'    => ['nullable', 'integer', 'min:1', 'max:12'],
            'promo_code'  => ['nullable', 'string', 'max:32'],
            'market_code' => ['nullable', 'string', 'max:32'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'effective'   => ['nullable', 'in:immediate,next_cycle'],
        ]);

        $tenant = $request->user()->tenant;
        if (! $tenant) {
            return response()->json(['message' => 'Tenant non trouvé.'], 422);
        }

        $plan          = Plan::where('code', $request->input('plan_code'))->selectable()->firstOrFail();
        $changeRequest = $this->changeRequests->createDraft(
            tenant:      $tenant,
            toPlan:      $plan,
            interval:    $request->input('interval'),
            promoCode:   $request->input('promo_code'),
            requestedBy: $request->user(),
            quantity:    (int) $request->input('quantity', 1),
            notes:       $request->input('notes'),
            marketHint:  $request->input('market_code'),
            effective:   $request->input('effective', 'immediate'),
        );

        return response()->json($changeRequest->toApiArray(), 201);
    }

    /**
     * POST /api/me/subscription/change-requests/{id}/submit
     * Soumet un brouillon (draft → pending_validation).
     */
    public function submitChangeRequest(Request $request, string $id): JsonResponse
    {
        $changeRequest = SubscriptionChangeRequest::findOrFail($id);

        try {
            $this->changeRequests->submit($changeRequest, $request->user());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($changeRequest->fresh()->toApiArray());
    }

    /**
     * POST /api/me/subscription/change-requests/{id}/cancel
     * Annule une demande encore ouverte (une demande validée/activée ne peut plus être annulée ici).
     */
    public function cancelChangeRequest(Request $request, string $id): JsonResponse
    {
        $changeRequest = SubscriptionChangeRequest::findOrFail($id);

        try {
            $this->changeRequests->cancel($changeRequest, $request->user(), $request->input('reason'));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($changeRequest->fresh()->toApiArray());
    }
}
