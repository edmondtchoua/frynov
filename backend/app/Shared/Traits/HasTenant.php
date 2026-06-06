<?php

namespace App\Shared\Traits;

use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Apply to every tenant-owned Eloquent model to get:
 *
 *   1. Automatic WHERE tenant_id = :current on every SELECT (via TenantScope)
 *   2. Automatic injection of tenant_id on INSERT (prevents missing tenant)
 *   3. withoutTenantScope() helper for admin / seeding contexts
 *
 * Usage: add `use HasTenant;` to Product, Order, Customer, Stock, etc.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasTenant
{
    public static function bootHasTenant(): void
    {
        // ── Automatic WHERE tenant_id = :current on every SELECT ──────────
        static::addGlobalScope(new TenantScope());

        // ── Auto-inject tenant_id on INSERT if omitted ────────────────────
        // Prevents silent cross-tenant inserts from code that forgets tenant_id
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = app()->has('current.tenant.id')
                    ? app('current.tenant.id')
                    : (auth()->check() && ! auth()->user()->isSuperAdmin()
                        ? auth()->user()->tenant_id
                        : null);

                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    /**
     * Query builder without the tenant scope — for super-admin or admin operations.
     *
     * Example: Product::withoutTenantScope()->where('sku', $sku)->first()
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
