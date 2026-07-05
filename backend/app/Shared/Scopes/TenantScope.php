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
    // Tenant impossible : utilisé pour FERMER le scope quand un principal authentifié n'a pas de
    // tenant résoluble (aucune ligne HasTenant n'a jamais ce tenant_id → zéro résultat).
    private const NO_TENANT_SENTINEL = '00000000-0000-0000-0000-000000000000';

    public function apply(Builder $builder, Model $model): void
    {
        // Priorité 1 : tenant lié par EnsureUserBelongsToTenant.
        if (app()->has('current.tenant.id')) {
            $builder->where($model->getTable() . '.tenant_id', app('current.tenant.id'));

            return;
        }

        // Super-admin ou contexte non authentifié (routes publiques, seeding, jobs) : global assumé.
        // Ces chemins se désengagent explicitement via withoutTenantScope() quand ils veulent un tenant.
        if (! auth()->check() || auth()->user()->isSuperAdmin()) {
            return;
        }

        // Principal authentifié non super-admin → TOUJOURS scoper. Si son tenant_id est absent
        // (ex. un token de compte portail atteignant une route sans middleware `tenant`), on FERME
        // le scope (sentinelle impossible) au lieu de fuiter en cross-tenant. (Recette QA — SEC-2)
        $builder->where(
            $model->getTable() . '.tenant_id',
            auth()->user()->tenant_id ?? self::NO_TENANT_SENTINEL,
        );
    }
}
