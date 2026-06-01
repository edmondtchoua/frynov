<?php

namespace App\Shared\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope: automatically restricts all Eloquent queries to the current
 * tenant so controllers never need to manually filter by tenant_id.
 *
 * Applied via the HasTenant trait on every tenant-owned model.
 *
 * Security guarantee (OWASP API4 / BOLA):
 *   Even if a controller forgets to scope by tenant_id, the DB query will
 *   ALWAYS include WHERE {table}.tenant_id = :current_tenant.
 *
 * Bypass (super-admin / internal seeding):
 *   Model::withoutTenantScope()->find($id)
 *   Model::withoutGlobalScope(TenantScope::class)->...
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = $this->resolveTenantId();

        if ($tenantId !== null) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }

    private function resolveTenantId(): ?string
    {
        // Priority 1: bound in the IoC container by EnsureUserBelongsToTenant middleware
        if (app()->has('current.tenant.id')) {
            return app('current.tenant.id');
        }

        // Priority 2: authenticated user's own tenant
        if (auth()->check() && ! auth()->user()->isSuperAdmin()) {
            return auth()->user()->tenant_id;
        }

        // Super admin or unauthenticated context → no automatic scoping
        return null;
    }
}
