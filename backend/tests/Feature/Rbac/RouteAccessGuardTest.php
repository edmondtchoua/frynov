<?php

namespace Tests\Feature\Rbac;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Audit RBAC P5 — GARDE-FOU CI de la matrice d'accès.
 *
 * Toute route d'ÉCRITURE (POST/PUT/PATCH/DELETE) gardée par `module:<code>` DOIT porter une garde de
 * permission/rôle (`role_or_permission` / `permission` / `role` / `can`). Ce test transforme les
 * écarts trouvés à l'audit (customers/suppliers/delivery) en invariant : une nouvelle route d'écriture
 * sans garde fera échouer la CI, empêchant toute régression.
 */
class RouteAccessGuardTest extends TestCase
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Exceptions LÉGITIMES (garde au niveau contrôleur, pas middleware) — à documenter ici. */
    private const ALLOWLIST = [
        // (aucune pour l'instant — les modules concernés gardent via le contrôleur et ne portent pas
        //  le middleware `module:`, donc ne sont pas capturés par ce test.)
    ];

    private static function hasPermissionGuard(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (str_starts_with($m, 'role_or_permission:') || str_starts_with($m, 'permission:')
                || str_starts_with($m, 'role:') || str_starts_with($m, 'can:')) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function every_module_write_route_is_permission_guarded(): void
    {
        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $isModuleGated = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'module:'));
            $writeMethods  = array_intersect($route->methods(), self::WRITE_METHODS);

            if (! $isModuleGated || $writeMethods === []) {
                continue;
            }

            $signature = implode('|', $writeMethods).' '.$route->uri();
            if (in_array($route->uri(), self::ALLOWLIST, true)) {
                continue;
            }

            if (! self::hasPermissionGuard($middleware)) {
                $unguarded[] = $signature;
            }
        }

        $this->assertSame([], $unguarded,
            "Écritures de module SANS garde de permission (ajouter role_or_permission ou allowlister) :\n - "
            .implode("\n - ", $unguarded));
    }
}
