<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;

class SubscriptionService
{
    public function __construct(
        private readonly ModuleRegistryService $moduleRegistry,
    ) {}

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
     */
    public function changePlan(
        Tenant $tenant,
        Plan $newPlan,
        ?User $approvedBy = null,
        string $interval = Subscription::INTERVAL_MONTHLY,
        bool $settle = true,
        ?\Carbon\CarbonInterface $periodStart = null,
    ): Subscription {
        $interval = in_array($interval, [Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY], true)
            ? $interval
            : Subscription::INTERVAL_MONTHLY;

        $current = $this->current($tenant);

        if ($current) {
            $current->update([
                'status'       => Subscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
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
