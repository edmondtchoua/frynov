<?php

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces tenant isolation for all authenticated app routes.
 *
 * Security properties:
 *   1. Tenant identity is ALWAYS anchored to the authenticated user's tenant_id
 *      — never from the request body, URL params, or untrusted headers.
 *   2. The resolved tenant_id is bound in the IoC container for TenantScope.
 *   3. IDOR attempts (URL tenant_id ≠ auth'd tenant_id) are logged and rejected.
 *   4. Super-admins bypass per-tenant checks but still get null scope binding.
 */
class EnsureUserBelongsToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        // ── Super admin: no tenant scoping needed ─────────────────────────
        if ($user->isSuperAdmin()) {
            // Bind null so TenantScope does not apply any tenant filter
            app()->instance('current.tenant.id', null);
            return $next($request);
        }

        // ── Resolve tenant strictly from the authenticated user ───────────
        // NEVER trust tenant_id from request body, headers, or URL segments
        $tenantId = $user->tenant_id;

        if (! $tenantId) {
            return response()->json(['message' => 'Tenant non identifié.'], 400);
        }

        // ── IDOR / BOLA detection ─────────────────────────────────────────
        // Detect if URL contains a tenant_id route param that differs from auth'd tenant
        $urlTenantId = $request->route('tenant_id');
        if ($urlTenantId && $urlTenantId !== $tenantId) {
            \Log::warning('SECURITY:IDOR_ATTEMPT', [
                'user_id'        => $user->id,
                'auth_tenant_id' => $tenantId,
                'url_tenant_id'  => $urlTenantId,
                'ip'             => $request->ip(),
                'method'         => $request->method(),
                'url'            => $request->fullUrl(),
                'user_agent'     => $request->userAgent(),
            ]);
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        // ── Bind tenant_id for TenantScope and services ───────────────────
        app()->instance('current.tenant.id', $tenantId);
        $request->attributes->set('tenant_id', $tenantId);

        // ── Scope Spatie permission checks to this tenant (teams=true) ────
        // This ensures hasRole()/hasAnyRole() checks use tenant_id correctly
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        // Also expose the tenant object for controllers that need it
        if (! $request->attributes->has('tenant')) {
            $request->attributes->set('tenant', $user->tenant);
        }

        return $next($request);
    }
}
