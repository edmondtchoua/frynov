<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Tenants\Models\Tenant;

/**
 * Orchestration des demandes de changement de plan (P1). Crée la demande à partir du devis autoritatif
 * (P0), fige le snapshot du plan cible, pilote la machine à états et synchronise la demande avec le
 * paiement manuel rattaché (approbation/refus admin).
 */
class SubscriptionChangeRequestService
{
    public function __construct(
        private readonly UpgradeQuoteService $quotes,
        private readonly SubscriptionService $subscriptions,
        private readonly SubscriptionNotifier $notifier,
    ) {}

    /**
     * Crée un BROUILLON de demande à partir d'un devis serveur-side (montants + snapshot figés).
     */
    public function createDraft(
        Tenant $tenant,
        Plan $toPlan,
        string $interval,
        ?string $promoCode,
        User $requestedBy,
        int $quantity = 1,
        ?string $notes = null,
        ?string $marketHint = null,
        string $effective = SubscriptionChangeRequest::EFFECTIVE_IMMEDIATE,
    ): SubscriptionChangeRequest {
        $effective = in_array($effective, [SubscriptionChangeRequest::EFFECTIVE_IMMEDIATE, SubscriptionChangeRequest::EFFECTIVE_NEXT_CYCLE], true)
            ? $effective
            : SubscriptionChangeRequest::EFFECTIVE_IMMEDIATE;
        $quote   = $this->quotes->quote($tenant, $toPlan, $interval, $quantity, $promoCode, $marketHint);
        $current = $this->subscriptions->current($tenant);
        $from    = $current?->plan;

        // Seule une promo VALIDÉE est figée sur la demande (une promo invalide n'entre pas au total).
        $validPromo = ($quote->promo['valid'] ?? false) === true;

        return SubscriptionChangeRequest::create([
            'tenant_id'              => $tenant->id,
            'from_plan_id'           => $from?->id,
            'to_plan_id'             => $toPlan->id,
            'interval'               => $quote->interval,
            'quantity'               => $quote->quantity,
            'market_code'            => $quote->market,
            'currency'               => $quote->currency,
            'change_type'            => $this->deriveChangeType($from, $toPlan, $quote->interval, $current?->interval),
            'effective'              => $effective,
            'status'                 => SubscriptionChangeRequest::STATUS_DRAFT,
            'base_gross_minor'       => $quote->baseGrossMinor,
            'promo_code'             => $validPromo ? $quote->promo['code'] : null,
            'promo_discount_minor'   => $validPromo ? ($quote->promo['discount_minor'] ?? 0) : 0,
            'tax_minor'              => $quote->taxMinor,
            'setup_fee_minor'        => $quote->setupFeeMinor,
            'proration_credit_minor' => $quote->appliedCreditMinor,
            'net_payable_minor'      => $quote->netPayableMinor,
            'plan_snapshot'          => $this->buildSnapshot($toPlan, $quote, $from),
            'metadata'               => $validPromo ? ['promo_source' => $quote->promo['source'] ?? null, 'promo_covered_periods' => $quote->promo['covered_periods'] ?? null] : null,
            'notes'                  => $notes,
            'requested_by'           => $requestedBy->id,
        ]);
    }

    /**
     * Chemin « paiement » : crée la demande DÉJÀ soumise (en attente de validation), prête à recevoir
     * la preuve de paiement. Renvoie la demande en `pending_validation`.
     */
    public function openForPayment(
        Tenant $tenant,
        Plan $toPlan,
        string $interval,
        ?string $promoCode,
        User $requestedBy,
        int $quantity = 1,
        ?string $notes = null,
        ?string $marketHint = null,
        string $effective = SubscriptionChangeRequest::EFFECTIVE_IMMEDIATE,
    ): SubscriptionChangeRequest {
        $request = $this->createDraft($tenant, $toPlan, $interval, $promoCode, $requestedBy, $quantity, $notes, $marketHint, $effective);
        $this->transition($request, SubscriptionChangeRequest::STATUS_SUBMITTED, $requestedBy->id);
        $this->transition($request, SubscriptionChangeRequest::STATUS_PENDING_VALIDATION, $requestedBy->id);

        // P2 — accusé de réception (tenant) + alerte admins internes. Best-effort.
        $this->notifier->submitted($request);

        return $request;
    }

    /** Soumet un brouillon (draft → pending_validation), en passant par `submitted`. */
    public function submit(SubscriptionChangeRequest $request, User $by): SubscriptionChangeRequest
    {
        $this->transition($request, SubscriptionChangeRequest::STATUS_SUBMITTED, $by->id);
        $this->transition($request, SubscriptionChangeRequest::STATUS_PENDING_VALIDATION, $by->id);

        return $request;
    }

    /**
     * Demande de CORRECTION (admin) : le paiement reste en attente, la demande repasse en
     * `pending_payment` (le tenant doit agir), la consigne est tracée et notifiée. Non destructif.
     */
    public function requestCorrection(SubscriptionChangeRequest $request, string $byUserId, string $reason): void
    {
        $meta = $request->metadata ?? [];
        $meta['correction_note']         = $reason;
        $meta['correction_requested_at'] = now()->toIso8601String();
        $request->update(['metadata' => $meta]);

        $this->transition($request, SubscriptionChangeRequest::STATUS_PENDING_PAYMENT, $byUserId, $reason, throwIfInvalid: false);
        $this->notifier->correctionRequested($request, $reason);
    }

    /** Annulation par le tenant (tout état ouvert → cancelled). */
    public function cancel(SubscriptionChangeRequest $request, User $by, ?string $reason = null): SubscriptionChangeRequest
    {
        $this->transition($request, SubscriptionChangeRequest::STATUS_CANCELLED, $by->id, $reason);
        $request->update(['cancelled_at' => now()]);

        return $request;
    }

    /**
     * Synchronise la demande rattachée avec l'état du paiement manuel (appelé depuis approve/reject).
     * Tolérant : n'agit que si la transition est autorisée (idempotent, jamais d'exception ici).
     */
    public function syncFromPayment(ManualPayment $payment): void
    {
        $request = $payment->changeRequest;
        if (! $request || $request->isTerminal()) {
            return;
        }

        // P4 — un changement DIFFÉRÉ (prochain cycle) approuvé attend l'échéance (appliqué par le cron
        // de renouvellement) : le paiement ne doit pas l'activer immédiatement.
        if ($request->effective === SubscriptionChangeRequest::EFFECTIVE_NEXT_CYCLE
            && $request->status === SubscriptionChangeRequest::STATUS_APPROVED) {
            return;
        }

        $target = match (true) {
            $payment->status === ManualPayment::STATUS_REJECTED => SubscriptionChangeRequest::STATUS_REJECTED,
            $payment->status === ManualPayment::STATUS_APPROVED => match ($payment->resolution_status) {
                ManualPayment::RESOLUTION_MATCHED,
                ManualPayment::RESOLUTION_OVERPAID,
                ManualPayment::RESOLUTION_FREE,
                ManualPayment::RESOLUTION_SETTLED     => SubscriptionChangeRequest::STATUS_ACTIVATED,
                ManualPayment::RESOLUTION_PARTIAL     => SubscriptionChangeRequest::STATUS_PENDING_PAYMENT,
                default                                => SubscriptionChangeRequest::STATUS_PENDING_VALIDATION,
            },
            default => SubscriptionChangeRequest::STATUS_PENDING_VALIDATION,
        };

        if (! $request->canTransitionTo($target)) {
            return;
        }

        $this->transition($request, $target, $payment->reviewed_by, $payment->rejection_reason, throwIfInvalid: false);

        if ($target === SubscriptionChangeRequest::STATUS_ACTIVATED) {
            $request->update(['activated_at' => now(), 'reviewed_by' => $payment->reviewed_by, 'reviewed_at' => now()]);
            $this->notifier->activated($request->fresh());        // P2 — confirmation tenant
        } elseif ($target === SubscriptionChangeRequest::STATUS_REJECTED) {
            $request->update(['reviewed_by' => $payment->reviewed_by, 'reviewed_at' => now()]);
            $this->notifier->rejected($request->fresh());         // P2 — information tenant (motif)
        }
    }

    /**
     * Transition gardée : refuse (par défaut) toute transition hors {@see SubscriptionChangeRequest::TRANSITIONS}
     * et journalise l'historique dans `metadata['transitions']`.
     *
     * @throws \DomainException si la transition est interdite et $throwIfInvalid est vrai.
     */
    public function transition(
        SubscriptionChangeRequest $request,
        string $to,
        ?string $actorId = null,
        ?string $note = null,
        bool $throwIfInvalid = true,
    ): void {
        if ($request->status === $to) {
            return;
        }

        if (! $request->canTransitionTo($to)) {
            if ($throwIfInvalid) {
                throw new \DomainException("Transition {$request->status} → {$to} non autorisée.");
            }

            return;
        }

        $history   = $request->metadata['transitions'] ?? [];
        $history[] = [
            'from'  => $request->status,
            'to'    => $to,
            'at'    => now()->toIso8601String(),
            'by'    => $actorId,
            'note'  => $note,
        ];

        $attrs = [
            'status'   => $to,
            'metadata' => array_merge($request->metadata ?? [], ['transitions' => $history]),
        ];
        if ($to === SubscriptionChangeRequest::STATUS_SUBMITTED && $request->submitted_at === null) {
            $attrs['submitted_at'] = now();
        }
        if ($to === SubscriptionChangeRequest::STATUS_REJECTED && $note !== null) {
            $attrs['rejection_reason'] = $note;
        }

        $request->update($attrs);
    }

    /**
     * Snapshot IMMUABLE des conditions du plan cible au moment de la demande. Une modification
     * ultérieure du plan (prix, quotas, modules) n'altère JAMAIS une demande déjà créée.
     *
     * @return array<string,mixed>
     */
    private function buildSnapshot(Plan $plan, UpgradeQuote $quote, ?Plan $from): array
    {
        $limits = $plan->limits;

        return [
            'captured_at'       => now()->toIso8601String(),
            'from_plan_code'    => $from?->code,
            'plan_code'         => $plan->code,
            'plan_name'         => $plan->name,
            'description'       => $plan->description,
            'interval'          => $quote->interval,
            'market_code'       => $quote->market,
            'currency'          => $quote->currency,
            'exponent'          => $quote->exponent,
            'base_gross_minor'  => $quote->baseGrossMinor,
            'net_payable_minor' => $quote->netPayableMinor,
            'trial_days'        => $plan->trial_days,
            'features'          => $plan->features ?? [],
            'limits'            => $limits ? [
                'max_products'            => $limits->max_products,
                'max_monthly_orders'      => $limits->max_monthly_orders,
                'max_customers'           => $limits->max_customers,
                'max_branches'            => $limits->max_branches,
                'max_warehouses'          => $limits->max_warehouses,
                'max_imports_per_month'   => $limits->max_imports_per_month,
                'max_api_calls_per_month' => $limits->max_api_calls_per_month,
                'storage_mb'              => $limits->storage_mb,
            ] : null,
            'modules'           => $plan->includedModules()->pluck('code')->all(),
        ];
    }

    /**
     * Type de changement, dérivé du rang (`sort_order`) des plans et de la périodicité.
     */
    private function deriveChangeType(?Plan $from, Plan $to, string $interval, ?string $currentInterval): string
    {
        if ($from === null) {
            return SubscriptionChangeRequest::TYPE_UPGRADE;
        }

        if ($from->id === $to->id) {
            return ($currentInterval !== null && $currentInterval !== $interval)
                ? SubscriptionChangeRequest::TYPE_PERIODICITY
                : SubscriptionChangeRequest::TYPE_CROSSGRADE;
        }

        return match (true) {
            (int) $to->sort_order > (int) $from->sort_order => SubscriptionChangeRequest::TYPE_UPGRADE,
            (int) $to->sort_order < (int) $from->sort_order => SubscriptionChangeRequest::TYPE_DOWNGRADE,
            default                                          => SubscriptionChangeRequest::TYPE_CROSSGRADE,
        };
    }
}
