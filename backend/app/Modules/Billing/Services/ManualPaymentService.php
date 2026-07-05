<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Exceptions\InvalidPromoCodeException;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\MarketPaymentMethod;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\TenantCredit;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManualPaymentService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentPeriodResolver $resolver,
        private readonly PromotionService $promotions,
        private readonly TenantCreditService $credits,
    ) {}

    /**
     * Submit a new manual payment request from a tenant.
     *
     * La périodicité et le marché sont DÉTECTÉS dès le submit (cumul = 0) à partir du montant : c'est
     * un pré-affichage stocké (marché, cible, statut de résolution). Aucune activation ici.
     *
     * @param ?string $marketHint        marché suggéré par le moyen de paiement/UI (validé serveur-side)
     * @param ?string $declaredInterval  périodicité déclarée (`monthly`|`yearly`) — repli de routage d'acompte
     */
    public function submit(
        Tenant        $tenant,
        Plan          $plan,
        int           $amountCents,
        string        $currency,
        string        $paymentMethod,
        ?UploadedFile $proof,
        ?string       $notes,
        ?string       $promoCode = null,
        ?string       $marketHint = null,
        ?string       $declaredInterval = null,
    ): ManualPayment {
        $proofPath             = null;
        $proofOriginalFilename = null;

        if ($proof) {
            $proofOriginalFilename = $proof->getClientOriginalName();
            // Security: payment proofs are PRIVATE — stored on the local disk, never the
            // public disk. They are retrieved only via a short-lived signed URL (admin).
            $proofPath             = $proof->store("payment-proofs/{$tenant->id}", 'local');
        }

        $res = $this->resolver->resolve(
            $plan,
            $amountCents,
            $currency,
            $marketHint,
            $declaredInterval ?? 'monthly',
            0,
            $promoCode !== null,
        );

        return ManualPayment::create([
            'tenant_id'              => $tenant->id,
            'plan_id'                => $plan->id,
            'amount_cents'           => $amountCents,
            'currency'               => $currency,
            'market_code'            => $res->marketCode,
            'declared_interval'      => $declaredInterval,
            'detected_interval'      => $res->interval,
            'target_amount_minor'    => $res->targetMinor,
            'remaining_due_minor'    => $res->remainingDueMinor,
            'overpaid_minor'         => $res->overpaidMinor,
            'resolution_status'      => $res->resolutionStatus,
            'payment_method'         => $paymentMethod,
            'proof_path'             => $proofPath,
            'proof_original_filename' => $proofOriginalFilename,
            'notes'                  => $notes,
            'promo_code_used'        => $promoCode,
            'status'                 => ManualPayment::STATUS_PENDING,
        ]);
    }

    /**
     * Approve a pending manual payment: detect periodicity from the cumulative amount and either
     * ACTIVATE the subscription (full / overpaid / free), set it PAST_DUE (partial deposit), or leave
     * it untouched (unmatched currency / promo → needs_review, admin decides).
     */
    public function approve(ManualPayment $payment, User $admin): ManualPayment
    {
        return DB::transaction(function () use ($payment, $admin) {
            // ── Idempotence : ne traiter qu'un paiement EN ATTENTE (évite double changePlan) ──
            if (! $payment->isPending()) {
                return $payment->fresh(['tenant', 'plan', 'reviewer']);
            }

            // ── RC-6G (règle 4) — devise ↔ moyen de paiement : un moyen déclaré dans le référentiel
            //    pour une AUTRE devise → approuvé SANS activation (needs_review strict). ─────────────
            if (config('billing.rules.strict_currency_method')) {
                $declaredCurrency = MarketPaymentMethod::query()
                    ->where('method', $payment->payment_method)
                    ->value('currency');
                if ($declaredCurrency !== null && strtoupper($declaredCurrency) !== strtoupper($payment->currency)) {
                    $payment->update([
                        'status'            => ManualPayment::STATUS_APPROVED,
                        'reviewed_by'       => $admin->id,
                        'reviewed_at'       => now(),
                        'resolution_status' => ManualPayment::RESOLUTION_NEEDS_REVIEW,
                    ]);

                    return $payment->fresh(['tenant', 'plan', 'reviewer']);
                }
            }

            // ── RC-6G (règle 2) — promo VALIDÉE → cible nette automatique (sinon needs_review). ──
            $promo = null;
            if ($payment->promo_code_used !== null && config('billing.rules.promo_net_target')) {
                try {
                    $promo = $this->promotions->validate($payment->promo_code_used, $payment->tenant, $payment->plan->code);
                } catch (InvalidPromoCodeException) {
                    $promo = null; // promo invalide → comportement RC-1C (l'admin tranche)
                }
            }
            $netOfPromo      = $promo ? fn (int $base): int => $promo->applyDiscount($base) : null;
            $matchExtraUsers = (bool) config('billing.rules.extra_user_matching'); // RC-6G (règle 3)

            // ── Cumul des acomptes NON SOLDÉS de la même cible (clé stable tenant+plan+market) ──
            $alreadyPaid = (int) ManualPayment::withoutTenantScope()
                ->where('tenant_id', $payment->tenant_id)
                ->where('plan_id', $payment->plan_id)
                ->where('market_code', $payment->market_code)
                ->where('status', ManualPayment::STATUS_APPROVED)
                ->where('resolution_status', ManualPayment::RESOLUTION_PARTIAL)
                ->whereNotNull('applied_at')
                ->where('id', '!=', $payment->id)
                ->sum('amount_cents');

            $targetInterval = $payment->declared_interval ?? Subscription::INTERVAL_MONTHLY;

            // ── Proration (RC-2) : sur un vrai UPGRADE (changement de plan/périodicité depuis un
            //    courant actif payé), le reliquat du temps non consommé agit comme un ACOMPTE VIRTUEL :
            //    le client ne vire que le NET, le crédit comble le reste. Le crédit n'est consommé que
            //    si l'upgrade SOLDE réellement le tarif ; sinon il reste intact. ──────────────────────
            $current   = $this->subscriptions->current($payment->tenant);
            $proration = null;
            $virtualCredit = 0;

            $isPlanChange = $current
                && $current->status === Subscription::STATUS_ACTIVE
                && (int) $current->amount_paid_minor > 0
                && ($current->plan_id !== $payment->plan_id || $current->interval !== $targetInterval);

            if ($isPlanChange) {
                $candidate = $this->subscriptions->previewProration($payment->tenant, $payment->plan, $targetInterval, now());
                if ($candidate->appliedCreditMinor > 0) {
                    $withCredit = $this->resolver->resolve(
                        $payment->plan, (int) $payment->amount_cents, $payment->currency, $payment->market_code,
                        $targetInterval, $alreadyPaid + $candidate->appliedCreditMinor, $payment->promo_code_used !== null,
                        $netOfPromo, $matchExtraUsers,
                    );
                    if ($withCredit->isComplete) {
                        $proration     = $candidate;          // crédit consommé : l'upgrade solde
                        $virtualCredit = $candidate->appliedCreditMinor;
                    }
                }
            }

            $res = $this->resolver->resolve(
                $payment->plan,
                (int) $payment->amount_cents,
                $payment->currency,
                $payment->market_code,
                $targetInterval,
                $alreadyPaid + $virtualCredit,
                $payment->promo_code_used !== null,
                $netOfPromo,        // RC-6G — promo validée → cible nette
                $matchExtraUsers,   // RC-6G — sièges additionnels
            );

            // ── RC-17 (M-1) — avoirs du LEDGER (trop-perçus crédités) : le solde disponible agit
            //    comme acompte virtuel s'il permet de SOLDER la cible. Même règle que la proration :
            //    pas de consommation partielle en dépôt (le crédit reste intact si la cible n'est pas
            //    atteinte). Consommé plus bas UNIQUEMENT à l'activation effective. ────────────────────
            $ledgerApplied = 0;
            if (config('billing.rules.tenant_credits_table')
                && ! $res->isComplete
                && $res->remainingDueMinor > 0
                && ! in_array($res->resolutionStatus, [ManualPayment::RESOLUTION_UNMATCHED, ManualPayment::RESOLUTION_NEEDS_REVIEW], true)) {
                $balance = $this->credits->balance($payment->tenant_id, $payment->currency);
                $needed  = min($balance, $res->remainingDueMinor);
                if ($needed > 0) {
                    $withLedger = $this->resolver->resolve(
                        $payment->plan, (int) $payment->amount_cents, $payment->currency, $payment->market_code,
                        $targetInterval, $alreadyPaid + $virtualCredit + $needed, $payment->promo_code_used !== null,
                        $netOfPromo, $matchExtraUsers,
                    );
                    if ($withLedger->isComplete) {
                        $res           = $withLedger;
                        $ledgerApplied = $needed;
                    }
                }
            }

            // Cash RÉELLEMENT encaissé (le crédit n'est PAS du cash et ne gonfle pas amount_paid_minor).
            $realCash = $alreadyPaid + (int) $payment->amount_cents;

            $payment->update([
                'status'              => ManualPayment::STATUS_APPROVED,
                'reviewed_by'         => $admin->id,
                'reviewed_at'         => now(),
                'applied_at'          => now(),
                'market_code'         => $res->marketCode,
                'detected_interval'   => $res->interval,
                'target_amount_minor' => $res->targetMinor,
                'remaining_due_minor' => $res->remainingDueMinor,
                'overpaid_minor'      => $res->overpaidMinor,
                'resolution_status'   => $res->resolutionStatus,
            ]);

            // Devise hors référentiel ou promo : paiement approuvé MAIS pas d'activation auto.
            if (in_array($res->resolutionStatus, [ManualPayment::RESOLUTION_UNMATCHED, ManualPayment::RESOLUTION_NEEDS_REVIEW], true)) {
                return $payment->fresh(['tenant', 'plan', 'reviewer']);
            }

            if ($res->isComplete) {
                // Début de période = 1er acompte du cycle (calculé AVANT de clore les contributeurs).
                $periodStart = $this->cycleStart($payment);

                // Clôture du cycle : les acomptes contributeurs deviennent 'settled' (hors cumul futur).
                ManualPayment::withoutTenantScope()
                    ->where('tenant_id', $payment->tenant_id)
                    ->where('plan_id', $payment->plan_id)
                    ->where('market_code', $payment->market_code)
                    ->where('status', ManualPayment::STATUS_APPROVED)
                    ->where('resolution_status', ManualPayment::RESOLUTION_PARTIAL)
                    ->where('id', '!=', $payment->id)
                    ->update(['resolution_status' => ManualPayment::RESOLUTION_SETTLED, 'remaining_due_minor' => 0]);

                $sub = $this->subscriptions->changePlan(
                    $payment->tenant,
                    $payment->plan,
                    $admin,
                    $res->interval ?? Subscription::INTERVAL_MONTHLY,
                    settle: true,
                    periodStart: $periodStart,
                    proration: $proration,   // RC-2 : trace l'avoir appliqué/reporté (si upgrade soldé par crédit)
                );

                $meta = $sub->metadata ?? [];
                if ($res->overpaidMinor > 0) {
                    if (config('billing.rules.tenant_credits_table')) {
                        // RC-6G (règle 1) — avoir dans le LEDGER dédié (plus de metadata).
                        $this->credits->credit(
                            $payment->tenant_id, $payment->currency, $res->overpaidMinor,
                            TenantCredit::SOURCE_OVERPAID, $payment->id, $admin->id,
                        );
                    } else {
                        // Legacy : avoir cumulé en metadata — mergé pour ne pas écraser d'autres clés.
                        $meta['overpaid_minor'] = (int) ($meta['overpaid_minor'] ?? 0) + $res->overpaidMinor;
                    }
                }
                if ($res->extraUsers > 0) {
                    $meta['extra_users'] = $res->extraUsers; // RC-6G (règle 3) — sièges détectés
                }
                // RC-17 (M-1) — l'avoir ledger réellement appliqué est CONSOMMÉ (ligne négative,
                // référence = paiement) et tracé dans la metadata pour l'admin.
                if ($ledgerApplied > 0) {
                    $this->credits->consume(
                        $payment->tenant_id, $payment->currency, $ledgerApplied, $payment->id, $admin->id,
                    );
                    $meta['ledger_credit_applied_minor'] = $ledgerApplied;
                }
                $sub->update([
                    'currency'          => $payment->currency,
                    'market_code'       => $res->marketCode,
                    'amount_paid_minor' => $realCash,   // cash réel (hors crédit virtuel de proration)
                    'metadata'          => $meta,
                ]);

                // RC-6G (règle 2) — la promo a réellement servi à activer : consommer son usage.
                if ($promo) {
                    $this->promotions->recordUse($promo, $payment->tenant);
                }
            } elseif ($res->isPartial) {
                // RC-6G (règle 5) — abonder l'acompte EN PLACE : si un past_due du même plan à période
                // non démarrée existe, on le met à jour au lieu d'annuler/recréer une ligne par tranche.
                $existingDeposit = config('billing.rules.apply_deposit_in_place')
                    ? Subscription::withoutTenantScope()
                        ->where('tenant_id', $payment->tenant_id)
                        ->where('plan_id', $payment->plan_id)
                        // Recette QA — le cycle d'acompte est identifié par (tenant, plan, MARCHÉ) :
                        // sans ce filtre, un tenant multi-devises abonderait le mauvais dépôt.
                        ->where('market_code', $res->marketCode)
                        ->where('status', Subscription::STATUS_PAST_DUE)
                        ->whereNull('current_period_end')
                        ->latest()
                        ->first()
                    : null;

                $sub = $existingDeposit ?? $this->subscriptions->changePlan(
                    $payment->tenant,
                    $payment->plan,
                    $admin,
                    $res->interval ?? Subscription::INTERVAL_MONTHLY,
                    settle: false,
                );
                $sub->update([
                    'currency'          => $payment->currency,
                    'market_code'       => $res->marketCode,
                    'interval'          => $res->interval ?? $sub->interval,
                    'amount_paid_minor' => $realCash,
                ]);
            }

            return $payment->fresh(['tenant', 'plan', 'reviewer']);
        });
    }

    /**
     * Début du cycle d'acompte courant : le created_at du PLUS ANCIEN acompte non soldé de la même
     * cible, sinon celui du paiement courant. Préserve l'ancienneté de la période au solde (RC-1C).
     */
    private function cycleStart(ManualPayment $payment): \Carbon\CarbonInterface
    {
        $own      = $payment->created_at ?? now();
        $earliest = ManualPayment::withoutTenantScope()
            ->where('tenant_id', $payment->tenant_id)
            ->where('plan_id', $payment->plan_id)
            ->where('market_code', $payment->market_code)
            ->where('status', ManualPayment::STATUS_APPROVED)
            ->where('resolution_status', ManualPayment::RESOLUTION_PARTIAL)
            ->where('id', '!=', $payment->id)
            ->min('created_at');

        if ($earliest === null) {
            return $own;
        }

        $earliestC = $earliest instanceof \Carbon\CarbonInterface
            ? $earliest
            : \Illuminate\Support\Carbon::parse($earliest);

        return $earliestC->lessThan($own) ? $earliestC : $own;
    }

    /**
     * Reject a manual payment.
     *
     * RC-6G (règle 6, `deposit_reversal`) : un ACOMPTE PARTIAL déjà imputé (applied_at) sur un
     * abonnement `past_due` NON SOLDÉ peut être rétro-annulé — le cumul est décrémenté sur
     * l'abonnement, et le paiement rejeté sort du cumul futur (filtré sur status=approved).
     * Un paiement ayant contribué à un abonnement SOLDÉ/actif reste non rejetable.
     */
    public function reject(ManualPayment $payment, User $admin, string $reason): ManualPayment
    {
        if ($payment->isApplied()) {
            $reversible = config('billing.rules.deposit_reversal')
                && $payment->resolution_status === ManualPayment::RESOLUTION_PARTIAL;

            $deposit = $reversible
                ? Subscription::withoutTenantScope()
                    ->where('tenant_id', $payment->tenant_id)
                    ->where('plan_id', $payment->plan_id)
                    // Recette QA — cibler le dépôt du MÊME marché/devise que le paiement rejeté
                    // (un tenant multi-devises décrémenterait sinon le mauvais cycle).
                    ->where('market_code', $payment->market_code)
                    ->where('status', Subscription::STATUS_PAST_DUE)
                    ->whereNull('current_period_end')   // période jamais démarrée = acompte en cours
                    ->latest()
                    ->first()
                : null;

            if (! $deposit) {
                throw new \RuntimeException("Un paiement déjà imputé sur un cycle soldé ne peut être rejeté.");
            }

            // Rétro-action : le cash rejeté sort du cumul de l'acompte.
            $deposit->update([
                'amount_paid_minor' => max(0, (int) $deposit->amount_paid_minor - (int) $payment->amount_cents),
            ]);
        }

        $payment->update([
            'status'           => ManualPayment::STATUS_REJECTED,
            'reviewed_by'      => $admin->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $payment->fresh(['tenant', 'plan']);
    }

    /**
     * List payments with optional status filter, paginated.
     */
    public function list(?string $status = null, int $perPage = 25)
    {
        // withoutTenantScope: admin operations must see ALL tenants' payments
        $query = ManualPayment::withoutTenantScope()
            ->with(['tenant:id,name,slug', 'plan:id,code,name'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * List all payments for a specific tenant.
     */
    public function forTenant(Tenant $tenant)
    {
        return ManualPayment::with('plan:id,code,name')
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->get();
    }
}
