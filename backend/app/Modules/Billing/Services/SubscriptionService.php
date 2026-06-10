<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Markets;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Carbon\CarbonInterface;

class SubscriptionService
{
    public function __construct(
        private readonly ModuleRegistryService $moduleRegistry,
        private readonly ProrationCalculator $proration,
    ) {}

    /**
     * Calcule (LECTURE SEULE, aucune mutation) le reliquat de proration si le tenant passait au
     * `$newPlan`/`$interval` maintenant : crédit du temps non consommé, net à payer, avoir reporté.
     * Le marché/devise est résolu sur l'abonnement courant (repli devise canonique, puis `global`).
     */
    public function previewProration(
        Tenant $tenant,
        Plan $newPlan,
        string $interval = Subscription::INTERVAL_MONTHLY,
        ?CarbonInterface $asOf = null,
    ): ProrationResult {
        $interval = in_array($interval, [Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY], true)
            ? $interval
            : Subscription::INTERVAL_MONTHLY;
        $asOf    = $asOf ?? now();
        $current = $this->current($tenant);

        // Marché : market_code du courant, sinon marché canonique de sa devise, sinon 'global'.
        $market = $current?->market_code
            ?: ($current?->currency ? Markets::canonicalForCurrency($current->currency) : null)
            ?: 'global';

        $newPrice    = $newPlan->priceForMarket($market, $interval);
        $newGross    = (int) ($newPrice?->base_amount_minor ?? 0);
        $newCurrency = $newPrice?->currency ?? ($current?->currency ?? 'USD');
        $currentCur  = $current?->currency ?: $newCurrency;

        $metadata = $current?->metadata ?? [];

        return $this->proration->compute(
            (int) ($current?->amount_paid_minor ?? 0),
            (int) ($metadata['overpaid_minor'] ?? 0),
            $current?->current_period_start,
            $current?->current_period_end,
            $current?->status ?? '',
            $currentCur,
            $newGross,
            $newCurrency,
            $asOf,
            (int) ($metadata['credit_minor'] ?? 0),
        );
    }

    /**
     * Create an initial trialing subscription for a newly provisioned tenant.
     */
    public function createStarter(Tenant $tenant): Subscription
    {
        $plan = Plan::where('code', Plan::CODE_STARTER)->firstOrFail();

        $subscription = Subscription::create([
            'tenant_id'             => $tenant->id,
            'plan_id'               => $plan->id,
            'status'                => Subscription::STATUS_TRIALING,
            'trial_ends_at'         => now()->addDays($plan->trial_days),
            'current_period_start'  => now(),
            'current_period_end'    => now()->addDays($plan->trial_days),
        ]);

        $tenant->update([
            'plan'                 => $plan->code,
            'subscription_status'  => $subscription->status,
        ]);

        // Activate all modules included in the starter plan
        $this->moduleRegistry->activatePlanModules($tenant, $plan);

        return $subscription;
    }

    /**
     * Get the active subscription for a tenant (most recent non-cancelled).
     */
    public function current(Tenant $tenant): ?Subscription
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->whereNotIn('status', [Subscription::STATUS_CANCELLED])
            ->with('plan')
            ->latest()
            ->first();
    }

    /**
     * Upgrade or change the plan for a tenant.
     *
     * @param string             $interval     Périodicité (`monthly`|`yearly`, hors whitelist → mensuel).
     *                                          Pilote la fin de période : +1 mois / +1 an. Persistée.
     * @param bool               $settle       SOLDÉ (true) → abonnement actif, période qui court ;
     *                                          NON soldé (false, acompte) → `past_due`, période non
     *                                          démarrée (current_period_end = null), modules NON activés.
     * @param \Carbon\CarbonInterface|null $periodStart  Début de période (repris du 1er acompte au
     *                                          solde d'un échelonnement) ; défaut = maintenant.
     * @param ProrationResult|null $proration  Reliquat de proration (RC-2). Quand fourni ET qu'un
     *                                          abonnement courant a réellement été annulé dans CET appel,
     *                                          l'avoir résiduel et l'avoir appliqué sont tracés dans la
     *                                          metadata du NOUVEL abonnement (jamais écrasés).
     */
    public function changePlan(
        Tenant $tenant,
        Plan $newPlan,
        ?User $approvedBy = null,
        string $interval = Subscription::INTERVAL_MONTHLY,
        bool $settle = true,
        ?\Carbon\CarbonInterface $periodStart = null,
        ?ProrationResult $proration = null,
    ): Subscription {
        $interval = in_array($interval, [Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY], true)
            ? $interval
            : Subscription::INTERVAL_MONTHLY;

        $current   = $this->current($tenant);
        $didCancel = false;

        if ($current) {
            $current->update([
                'status'       => Subscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
            $didCancel = true;
        }

        // Statut : approbateur + soldé → actif ; approbateur + acompte → past_due ; sinon en attente.
        $status = $approvedBy
            ? ($settle ? Subscription::STATUS_ACTIVE : Subscription::STATUS_PAST_DUE)
            : Subscription::STATUS_PENDING_APPROVAL;

        $start = $periodStart ?? now();
        // La période ne court QU'une fois soldée : un acompte (past_due) n'a pas de fin de période.
        $periodEnd = $settle
            ? ($interval === Subscription::INTERVAL_YEARLY ? (clone $start)->addYear() : (clone $start)->addMonth())
            : null;

        $subscription = Subscription::create([
            'tenant_id'            => $tenant->id,
            'plan_id'              => $newPlan->id,
            'status'               => $status,
            'interval'             => $interval,
            'current_period_start' => $start,
            'current_period_end'   => $periodEnd,
            'approved_by'          => $approvedBy?->id,
            'approved_at'          => $approvedBy ? now() : null,
        ]);

        // Proration (RC-2) — émise UNE seule fois, au moment où le courant est réellement annulé
        // (garde d'idempotence). Persistée dès qu'il y a un avoir appliqué OU reporté (PAS sur eligible,
        // sinon un avoir reporté antérieur sur un cas non éligible serait perdu).
        if ($proration !== null && $didCancel
            && ($proration->appliedCreditMinor > 0 || $proration->carryCreditMinor > 0)) {
            $meta = $subscription->metadata ?? [];
            if ($proration->carryCreditMinor > 0) {
                $meta['credit_minor'] = (int) ($meta['credit_minor'] ?? 0) + $proration->carryCreditMinor;
            }
            if ($proration->appliedCreditMinor > 0) {
                $meta['credit_applied_minor'] = $proration->appliedCreditMinor;
            }
            $meta['proration'] = [
                'from_plan'     => $current?->plan?->code,
                'fraction'      => $proration->fraction,
                'credit_minor'  => $proration->creditMinor,
                'applied_minor' => $proration->appliedCreditMinor,
                'currency'      => $proration->currency,
                'reason'        => $proration->reason,
            ];
            $subscription->update(['metadata' => $meta]);
        }

        $tenant->update([
            'plan'                => $newPlan->code,
            'subscription_status' => $subscription->status,
        ]);

        // Les modules ne sont activés qu'une fois la période SOLDÉE (un past_due n'ouvre pas l'accès).
        if ($settle && $subscription->isActive()) {
            $this->moduleRegistry->activatePlanModules($tenant, $newPlan);
        }

        return $subscription;
    }

    /**
     * Suspend a tenant's subscription (e.g. non-payment).
     */
    public function suspend(Tenant $tenant, ?string $reason = null): void
    {
        $sub = $this->current($tenant);
        $sub?->update(['status' => Subscription::STATUS_SUSPENDED, 'suspension_reason' => $reason]);
        $tenant->update(['subscription_status' => Subscription::STATUS_SUSPENDED, 'status' => 'suspended']);
    }

    /**
     * Reactivate a suspended subscription.
     */
    public function reactivate(Tenant $tenant, User $byUser): void
    {
        $sub = Subscription::where('tenant_id', $tenant->id)
            ->where('status', Subscription::STATUS_SUSPENDED)
            ->latest()
            ->first();

        if ($sub) {
            $sub->update([
                'status'       => Subscription::STATUS_ACTIVE,
                'approved_by'  => $byUser->id,
                'approved_at'  => now(),
            ]);
        }

        $tenant->update(['subscription_status' => Subscription::STATUS_ACTIVE, 'status' => 'active']);
    }
}
