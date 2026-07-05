<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Models\ErpModule;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Platform\Services\AuditService;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminModuleController extends Controller
{
    public function __construct(
        private readonly ModuleRegistryService $registry,
        private readonly AuditService $audit,
    ) {}

    /** All ERP modules (admin view with full stats). */
    public function index(): JsonResponse
    {
        $modules = ErpModule::orderBy('category')->orderBy('sort_order')
            ->withCount('tenantModules as total_activations')
            ->get();

        return response()->json($modules);
    }

    /** Update module metadata (status, visibility, etc.). */
    public function update(Request $request, ErpModule $module): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status'      => ['sometimes', 'in:active,beta,coming_soon,maintenance,disabled'],
            'is_visible'  => ['sometimes', 'boolean'],
            'sort_order'  => ['sometimes', 'integer'],
        ]);

        $old = $module->only(array_keys($validated));
        $module->update($validated);
        $this->audit->logUpdated($request, $module, $old);

        return response()->json($module->fresh());
    }

    /** All modules for a specific tenant + activation status. */
    public function forTenant(Tenant $tenant): JsonResponse
    {
        $modules = $this->registry->listForTenant($tenant);

        return response()->json($modules);
    }

    /** Activate a module for a tenant (admin override). */
    public function activateForTenant(Request $request, Tenant $tenant, string $moduleCode): JsonResponse
    {
        $tenantModule = $this->registry->activate($tenant, $moduleCode, $request->user()?->id);
        $this->audit->logModuleActivated($request, $moduleCode, $tenant->id);

        return response()->json($tenantModule);
    }

    /** Deactivate a module for a tenant. */
    public function deactivateForTenant(Request $request, Tenant $tenant, string $moduleCode): JsonResponse
    {
        $this->registry->deactivate($tenant, $moduleCode);
        $this->audit->logModuleDeactivated($request, $moduleCode, $tenant->id);

        return response()->json(['message' => 'Module désactivé.']);
    }
}
