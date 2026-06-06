<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Services\ModuleRegistryService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route group behind a tenant module — usage: `module:reports`.
 *
 * When a super-admin removes a module from a tenant, EVERY user of that tenant
 * (admins included) is denied at the route level: the access is *neutralised* at
 * runtime (no role/permission row is mutated). Fully data-driven — adding or
 * enriching a module requires no change here, only the `module:<code>` declaration
 * on the new route group + the module row in `erp_modules`.
 *
 * Fail-open for UNPROVISIONED tenants (zero `tenant_modules` rows): the gate is a
 * no-op until a tenant is provisioned. Real tenants are provisioned at registration
 * (plan modules activated), so the gate is active in production; bare test tenants
 * (no provisioning) are unaffected — which keeps the existing suite green.
 */
class EnsureTenantHasModule
{
    public function __construct(private readonly ModuleRegistryService $registry) {}

    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $user = $request->user();

        // Super-admins operate on /admin, never on tenant module routes — not gated.
        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;
        if (! $tenant) {
            return $next($request);
        }

        // Unprovisioned tenant (no module configuration at all) → gate inactive.
        if (! DB::table('tenant_modules')->where('tenant_id', $tenant->id)->exists()) {
            return $next($request);
        }

        if (! $this->registry->tenantHasModule($tenant, $moduleCode)) {
            return response()->json([
                'message' => 'Ce module n’est pas activé pour votre espace de travail.',
                'module'  => $moduleCode,
            ], 403);
        }

        return $next($request);
    }
}
