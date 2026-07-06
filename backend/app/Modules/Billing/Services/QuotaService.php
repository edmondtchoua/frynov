<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Tenants\Models\Tenant;
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
        // null OR 0 = unlimited. Seats are still mirrored on plans for backwards
        // compatibility, while localized prices expose included_users per market.
        $limit = $this->limit($plan, 'max_users');
        if (empty($limit)) {
            return;
        }

        $current = User::where('tenant_id', $tenant->id)->withTrashed(false)->count();
        if ($current >= $limit) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$limit} utilisateurs. "
                .'Mettez à niveau votre abonnement pour ajouter davantage de membres.',
            );
        }
    }

    /** Check: can this tenant add another warehouse/branch? */
    public function assertCanAddWarehouse(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        $limit = $this->limit($plan, 'max_warehouses');
        if (empty($limit)) {
            return;
        }  // null/0 = unlimited

        $current = Warehouse::where('tenant_id', $tenant->id)->count();
        if ($current >= $limit) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$limit} entrepôt(s). "
                ."Mettez à niveau votre abonnement pour ajouter davantage d'entrepôts.",
            );
        }
    }

    /** Check: can this tenant add another agent-role user? */
    public function assertCanAddAgent(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        $limit = $this->limit($plan, 'max_agents');
        if (empty($limit)) {
            return;
        }  // null/0 = unlimited

        // Count users with agent-type roles (agent, cashier, commercial, delivery)
        $current = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agent', 'cashier', 'commercial', 'delivery']))
            ->count();

        if ($current >= $limit) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$limit} agent(s) terrain. "
                ."Mettez à niveau votre abonnement pour ajouter davantage d'agents.",
            );
        }
    }

    /** Check: monthly order count quota. */
    public function assertCanCreateOrder(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        $limit = $this->limit($plan, 'max_monthly_orders');
        if (empty($limit)) {
            return;
        }  // null/0 = unlimited

        $current = DB::table('orders')
            ->where('tenant_id', $tenant->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        if ($current >= $limit) {
            throw new \DomainException(
                "Limite mensuelle atteinte : votre plan {$plan->name} autorise {$limit} commandes par mois.",
            );
        }
    }

    /** Check: customer count quota. */
    public function assertCanAddCustomer(Tenant $tenant): void
    {
        $plan  = $this->plan($tenant);
        $limit = $this->limit($plan, 'max_customers');
        if (empty($limit)) {
            return;
        }  // null/0 = unlimited

        $current = DB::table('customers')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        if ($current >= $limit) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$limit} clients. "
                .'Mettez à niveau votre abonnement pour en enregistrer davantage.',
            );
        }
    }

    /** Check: product count quota. */
    public function assertCanAddProduct(Tenant $tenant): void
    {
        $plan = $this->plan($tenant);
        $limit = $this->limit($plan, 'max_products');
        if (empty($limit)) {
            return;
        }  // null/0 = unlimited

        $current = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->count();

        if ($current >= $limit) {
            throw new \DomainException(
                "Limite atteinte : votre plan {$plan->name} autorise au maximum {$limit} produits.",
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
            'users' => $this->assertCanAddUser($tenant),
            'products' => $this->assertCanAddProduct($tenant),
            'orders' => $this->assertCanCreateOrder($tenant),
            'warehouses' => $this->assertCanAddWarehouse($tenant),
            'agents' => $this->assertCanAddAgent($tenant),
            'customers' => $this->assertCanAddCustomer($tenant),
            default => null, // unknown resource — no-op
        };
    }

    /** Champ de limite (`plans`/`plan_limits`) porté par une ressource. */
    private const RESOURCE_FIELD = [
        'users'      => 'max_users',
        'products'   => 'max_products',
        'orders'     => 'max_monthly_orders',
        'warehouses' => 'max_warehouses',
        'customers'  => 'max_customers',
        'agents'     => 'max_agents',
    ];

    /**
     * P3 — usage courant d'une ressource pour un tenant (indépendant du plan). Best-effort : toute
     * erreur de schéma renvoie 0 (l'aperçu d'impact est indicatif, jamais bloquant).
     */
    public function usage(Tenant $tenant, string $resource): int
    {
        try {
            return match ($resource) {
                'users'      => User::where('tenant_id', $tenant->id)->count(),
                'agents'     => User::where('tenant_id', $tenant->id)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agent', 'cashier', 'commercial', 'delivery']))->count(),
                'products'   => DB::table('products')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count(),
                'customers'  => DB::table('customers')->where('tenant_id', $tenant->id)->count(),
                'warehouses' => Warehouse::where('tenant_id', $tenant->id)->count(),
                'orders'     => DB::table('orders')->where('tenant_id', $tenant->id)
                    ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                default      => 0,
            };
        } catch (\Throwable) {
            return 0;
        }
    }

    /** P3 — limite d'une ressource pour un plan donné (null/0 = illimité). */
    public function planLimit(Plan $plan, string $resource): ?int
    {
        $field = self::RESOURCE_FIELD[$resource] ?? null;

        return $field ? $this->limit($plan, $field) : null;
    }

    private function plan(Tenant $tenant): ?Plan
    {
        return Plan::with('limits')->where('code', $tenant->plan)->first();
    }

    private function limit(?Plan $plan, string $field): ?int
    {
        if (! $plan) {
            return null;
        }

        $fromLimits = $plan->limits?->{$field};
        if ($fromLimits !== null) {
            return $fromLimits;
        }

        return $plan->{$field};
    }
}
