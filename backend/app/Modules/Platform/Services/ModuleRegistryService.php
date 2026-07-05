<?php

namespace App\Modules\Platform\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\ErpModule;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Support\Collection;

class ModuleRegistryService
{
    /**
     * All visible ERP modules, with activation status for the given tenant.
     */
    public function listForTenant(Tenant $tenant): Collection
    {
        $active = TenantModule::where('tenant_id', $tenant->id)
            ->pluck('status', 'module_id');

        return ErpModule::where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (ErpModule $mod) use ($active) {
                $status = $active->get($mod->id);
                $mod->tenant_status  = $status;           // null = not activated
                $mod->tenant_active  = $status !== null && in_array($status, [TenantModule::STATUS_ACTIVE, TenantModule::STATUS_TRIAL]);
                return $mod;
            });
    }

    /**
     * Active module codes for a tenant (for quick middleware checks).
     */
    public function activeCodes(Tenant $tenant): array
    {
        // Core modules are always available
        $coreCodes = ErpModule::where('is_core', true)->pluck('code')->toArray();

        $activeCodes = ErpModule::join('tenant_modules', 'erp_modules.id', '=', 'tenant_modules.module_id')
            ->where('tenant_modules.tenant_id', $tenant->id)
            ->whereIn('tenant_modules.status', [TenantModule::STATUS_ACTIVE, TenantModule::STATUS_TRIAL])
            ->pluck('erp_modules.code')
            ->toArray();

        return array_unique(array_merge($coreCodes, $activeCodes));
    }

    /**
     * Check if a tenant has access to a specific module code.
     */
    public function tenantHasModule(Tenant $tenant, string $moduleCode): bool
    {
        $module = ErpModule::where('code', $moduleCode)->first();
        if (! $module) return false;
        if ($module->is_core) return true;

        return TenantModule::where('tenant_id', $tenant->id)
            ->where('module_id', $module->id)
            ->whereIn('status', [TenantModule::STATUS_ACTIVE, TenantModule::STATUS_TRIAL])
            ->exists();
    }

    /**
     * Sync a tenant's modules to exactly match a plan: activate every module the
     * plan includes AND deactivate any currently-active module the plan does NOT
     * include. Called when a subscription is created or its plan changes.
     *
     * Previously this only ACTIVATED (additive) — so a downgrade (e.g. Pro→Starter)
     * left the higher plan's exclusive modules active, granting access to features
     * the tenant no longer pays for.
     */
    public function activatePlanModules(Tenant $tenant, Plan $plan): void
    {
        // Get the plan's module IDs via loaded models (avoids table-name ambiguity)
        $planModuleIds = $plan->includedModules()->get()->pluck('id')->all();

        // 1. Activate (or create) every module included in the plan
        foreach ($planModuleIds as $moduleId) {
            TenantModule::updateOrCreate(
                ['tenant_id' => $tenant->id, 'module_id' => $moduleId],
                ['status' => TenantModule::STATUS_ACTIVE, 'activated_at' => now()]
            );
        }

        // 2. Deactivate any active/trial module NOT in the new plan (downgrade).
        //    tenant_modules has no deactivated_at column — status flip is enough.
        TenantModule::where('tenant_id', $tenant->id)
            ->whereNotIn('module_id', $planModuleIds)
            ->whereIn('status', [TenantModule::STATUS_ACTIVE, TenantModule::STATUS_TRIAL])
            ->update(['status' => TenantModule::STATUS_INACTIVE]);
    }

    /**
     * Activate a specific module for a tenant (manual activation).
     */
    public function activate(Tenant $tenant, string $moduleCode, ?string $activatedBy = null): TenantModule
    {
        $module = ErpModule::where('code', $moduleCode)->firstOrFail();

        // Sprint 11: log when a module is activated outside the tenant's plan (admin override)
        $plan      = Plan::where('code', $tenant->plan)->first();
        $isInPlan  = $plan && $plan->includedModules()->where('erp_modules.id', $module->id)->exists();

        if (! $isInPlan) {
            AuditLog::create([
                'tenant_id'    => $tenant->id,
                'user_id'      => auth()->id(),
                'action'       => 'module.activated_outside_plan',
                'subject_type' => 'ErpModule',
                'subject_id'   => $module->id,
                'old_values'   => ['plan_code' => $tenant->plan],
                'new_values'   => ['module_code' => $moduleCode, 'override' => true],
                'ip_address'   => request()->ip(),
                'user_agent'   => request()->userAgent(),
                'risk_level'   => 'medium',
            ]);
        }

        return TenantModule::updateOrCreate(
            ['tenant_id' => $tenant->id, 'module_id' => $module->id],
            [
                'status'       => TenantModule::STATUS_ACTIVE,
                'activated_at' => now(),
                'activated_by' => $activatedBy,
            ]
        );
    }

    /**
     * Deactivate a module for a tenant.
     */
    public function deactivate(Tenant $tenant, string $moduleCode): void
    {
        $module = ErpModule::where('code', $moduleCode)->first();
        if (! $module || $module->is_core) return;

        TenantModule::where('tenant_id', $tenant->id)
            ->where('module_id', $module->id)
            ->update(['status' => TenantModule::STATUS_INACTIVE]);
    }

    /**
     * All ErpModules (admin view).
     */
    public function all(): Collection
    {
        return ErpModule::orderBy('category')->orderBy('sort_order')->get();
    }
}
