<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Exceptions\QuotaExceededException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenants\Models\Tenant;

class QuotaService
{
    public const RESOURCE_USERS    = 'users';
    public const RESOURCE_PRODUCTS = 'products';
    public const RESOURCE_ORDERS   = 'orders';

    /**
     * Assert that the tenant is within quota for $resource.
     *
     * @throws QuotaExceededException when the plan limit is reached.
     */
    public function check(Tenant $tenant, string $resource): void
    {
        $limit = $this->limitFor($tenant, $resource);

        if ($limit === null) {
            return; // unlimited
        }

        $usage = $this->usageFor($tenant, $resource);

        if ($usage >= $limit) {
            throw new QuotaExceededException($resource, $limit, $usage);
        }
    }

    /**
     * Returns true when the tenant has not yet exhausted the quota.
     */
    public function isWithinLimit(Tenant $tenant, string $resource): bool
    {
        try {
            $this->check($tenant, $resource);

            return true;
        } catch (QuotaExceededException) {
            return false;
        }
    }

    /**
     * Returns the plan-imposed limit for $resource, or null when unlimited.
     *
     * A plan field of 0 or null is treated as unlimited.
     */
    public function limitFor(Tenant $tenant, string $resource): ?int
    {
        $plan = $this->activePlan($tenant);

        if ($plan === null) {
            return null;
        }

        $raw = match ($resource) {
            self::RESOURCE_USERS    => $plan->max_users,
            self::RESOURCE_PRODUCTS => $plan->max_products,
            self::RESOURCE_ORDERS   => $plan->max_monthly_orders,
            default                 => null,
        };

        return ($raw !== null && $raw > 0) ? $raw : null;
    }

    /**
     * Returns the current resource count for the tenant.
     *
     * For orders, only the current calendar month is counted (rolling monthly quota).
     */
    public function usageFor(Tenant $tenant, string $resource): int
    {
        return match ($resource) {
            self::RESOURCE_USERS => User::where('tenant_id', $tenant->id)->count(),

            self::RESOURCE_PRODUCTS => Product::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->count(),

            self::RESOURCE_ORDERS => Order::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),

            default => 0,
        };
    }

    private function activePlan(Tenant $tenant): ?Plan
    {
        $sub = Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
            ])
            ->with('plan')
            ->latest()
            ->first();

        return $sub?->plan;
    }
}
