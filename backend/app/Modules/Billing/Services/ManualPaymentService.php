<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManualPaymentService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentPeriodResolver $resolver,
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

            $res = $this->resolver->resolve(
                $payment->plan,
                (int) $payment->amount_cents,
                $payment->currency,
                $payment->market_code,
                $payment->declared_interval ?? 'monthly',
                $alreadyPaid,
                $payment->promo_code_used !== null,
            );

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
                );

                $meta = $sub->metadata ?? [];
                if ($res->overpaidMinor > 0) {
                    // Avoir cumulé — toujours mergé pour ne pas écraser d'autres clés metadata.
                    $meta['overpaid_minor'] = (int) ($meta['overpaid_minor'] ?? 0) + $res->overpaidMinor;
                }
                $sub->update([
                    'currency'          => $payment->currency,
                    'market_code'       => $res->marketCode,
                    'amount_paid_minor' => $res->paidCumulativeMinor,
                    'metadata'          => $meta,
                ]);
            } elseif ($res->isPartial) {
                $sub = $this->subscriptions->changePlan(
                    $payment->tenant,
                    $payment->plan,
                    $admin,
                    $res->interval ?? Subscription::INTERVAL_MONTHLY,
                    settle: false,
                );
                $sub->update([
                    'currency'          => $payment->currency,
                    'market_code'       => $res->marketCode,
                    'amount_paid_minor' => $res->paidCumulativeMinor,
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
     * Reject a pending manual payment. Un acompte déjà imputé (applied_at) ne se rétro-annule pas
     * en RC-1C (le cumul n'est pas décrémenté) → le rejet d'un paiement imputé est refusé.
     */
    public function reject(ManualPayment $payment, User $admin, string $reason): ManualPayment
    {
        if ($payment->isApplied()) {
            throw new \RuntimeException("Un paiement déjà imputé ne peut être rejeté (rétro-action d'acompte hors périmètre RC-1C).");
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
