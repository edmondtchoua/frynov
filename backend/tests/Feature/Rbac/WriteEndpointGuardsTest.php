<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Audit RBAC P0 — les écritures customers/suppliers/delivery sont désormais gardées par permission :
 * un rôle `viewer` (lecture seule) reçoit 403, un `admin` passe la garde.
 */
class WriteEndpointGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Tenant $tenant, string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole($role);

        return $user;
    }

    private function setUpTenant(): Tenant
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => 'starter', 'status' => 'active', 'subscription_status' => 'trialing',
        ]);
    }

    /** @return array<int,array{0:string,1:string}> [method, uri] */
    public static function guardedWrites(): array
    {
        return [
            'suppliers create' => ['post', '/api/suppliers'],
            'suppliers update' => ['put', '/api/suppliers/00000000-0000-0000-0000-000000000001'],
            'customers update' => ['put', '/api/customers/00000000-0000-0000-0000-000000000001'],
            'delivery create'  => ['post', '/api/deliveries'],
        ];
    }

    #[Test]
    public function a_viewer_is_forbidden_on_every_guarded_write(): void
    {
        $tenant = $this->setUpTenant();
        Sanctum::actingAs($this->userWithRole($tenant, 'viewer'));

        foreach (self::guardedWrites() as [$method, $uri]) {
            $this->json(strtoupper($method), $uri, [])
                ->assertStatus(403); // la garde de permission rejette AVANT le contrôleur
        }
    }

    #[Test]
    public function an_admin_passes_the_permission_guard(): void
    {
        $tenant = $this->setUpTenant();
        Sanctum::actingAs($this->userWithRole($tenant, 'admin'));

        foreach (self::guardedWrites() as [$method, $uri]) {
            // L'admin peut ne pas fournir un corps valide (422) ou viser un id inexistant (404),
            // mais il ne doit JAMAIS être bloqué par la permission (403).
            $status = $this->json(strtoupper($method), $uri, [])->status();
            $this->assertNotSame(403, $status, "L'admin ne doit pas être 403 sur {$method} {$uri}");
        }
    }
}
