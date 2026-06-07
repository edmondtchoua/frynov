<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Services\ModuleRegistryService;
use Closure;
use Illuminate\Http\Request;
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
 * FAIL-CLOSED (security audit): a tenant without the module active — including a tenant
 * with zero `tenant_modules` rows — is denied. Hidden menus are never a security control;
 * the backend route is the authority. Core modules (e.g. dashboard) remain always-on via
 * ModuleRegistryService::tenantHasModule(). Tests that exercise gated routes must provision
 * the required modules (see Tests\TestCase::activateAllModules()).
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

        if (! $this->registry->tenantHasModule($tenant, $moduleCode)) {
            return response()->json([
                'message' => 'Ce module n’est pas activé pour votre espace de travail.',
                'module'  => $moduleCode,
            ], 403);
        }

        return $next($request);
    }
}
