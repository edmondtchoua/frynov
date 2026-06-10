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
     * @param string $interval  Périodicité de l'abonnement (`monthly`|`yearly`). Pilote la fin de
     *                          période : +1 mois (mensuel) ou +1 an (annuel). Valeur hors whitelist
     *                          → mensuel. La périodicité est persistée sur l'abonnement.
     */
    public function changePlan(
        Tenant $tenant,
        Plan $newPlan,
        ?User $approvedBy = null,
        string $interval = Subscription::INTERVAL_MONTHLY,
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

        $periodEnd = $interval === Subscription::INTERVAL_YEARLY
            ? now()->addYear()
            : now()->addMonth();

        $subscription = Subscription::create([
            'tenant_id'            => $tenant->id,
            'plan_id'              => $newPlan->id,
            'status'               => $approvedBy ? Subscription::STATUS_ACTIVE : Subscription::STATUS_PENDING_APPROVAL,
            'interval'             => $interval,
            'current_period_start' => now(),
            'current_period_end'   => $periodEnd,
            'approved_by'          => $approvedBy?->id,
            'approved_at'          => $approvedBy ? now() : null,
        ]);

        $tenant->update([
            'plan'                => $newPlan->code,
            'subscription_status' => $subscription->status,
        ]);

        if ($subscription->isActive()) {
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
