<?php
namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 12 — Runtime quota enforcement service.
 * Called before creating resources that have plan-level limits.
 * Throws \DomainException when a quota is exceeded.
 */
class QuotaService
{
    /** Check: can this tenant add another user? */
    public function assertCanAddUser(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        if ($plan?->max_users === null) return;

        $current = User::where('tenant_id', $tenant->id)->withTrashed(false)->count();
        if ($current >= $plan->max_users) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$plan->max_users} utilisateurs. "
                . "Mettez à niveau votre abonnement pour ajouter davantage de membres."
            );
        }
    }

    /** Check: can this tenant add another warehouse/branch? */
    public function assertCanAddWarehouse(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        if ($plan?->max_warehouses === null) return;

        $current = Warehouse::where('tenant_id', $tenant->id)->count();
        if ($current >= $plan->max_warehouses) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$plan->max_warehouses} entrepôt(s). "
                . "Mettez à niveau votre abonnement pour ajouter davantage d'entrepôts."
            );
        }
    }

    /** Check: can this tenant add another agent-role user? */
    public function assertCanAddAgent(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        if ($plan?->max_agents === null) return;

        // Count users with agent-type roles (agent, cashier, commercial, delivery)
        $current = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agent', 'cashier', 'commercial', 'delivery']))
            ->count();

        if ($current >= $plan->max_agents) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$plan->max_agents} agent(s) terrain. "
                . "Mettez à niveau votre abonnement pour ajouter davantage d'agents."
            );
        }
    }

    /** Check: monthly order count quota. */
    public function assertCanCreateOrder(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        if ($plan?->max_monthly_orders === null) return;

        $current = DB::table('orders')
            ->where('tenant_id', $tenant->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        if ($current >= $plan->max_monthly_orders) {
            throw new \DomainException(
                "Limite mensuelle atteinte : votre plan {$plan->name} autorise {$plan->max_monthly_orders} commandes par mois."
            );
        }
    }

    /** Check: product count quota. */
    public function assertCanAddProduct(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        if ($plan?->max_products === null) return;

        $current = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        if ($current >= $plan->max_products) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$plan->max_products} produits."
            );
        }
    }

    /**
     * Backwards-compatible check() method for EnforceQuota middleware.
     * Routes to the correct assert method based on resource name.
     * Resource names: 'users', 'products', 'orders', 'warehouses', 'agents'
     */
    public function check(Tenant $tenant, string $resource): void
    {
        match ($resource) {
            'users'      => $this->assertCanAddUser($tenant),
            'products'   => $this->assertCanAddProduct($tenant),
            'orders'     => $this->assertCanCreateOrder($tenant),
            'warehouses' => $this->assertCanAddWarehouse($tenant),
            'agents'     => $this->assertCanAddAgent($tenant),
            default      => null, // unknown resource — no-op
        };
    }

    private function plan(Tenant $tenant): ?Plan
    {
        return Plan::where('code', $tenant->plan)->first();
    }
}
