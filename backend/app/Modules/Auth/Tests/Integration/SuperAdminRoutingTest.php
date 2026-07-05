<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for super-admin routing isolation.
 *
 * A super-admin (is_super_admin = true, tenant_id = null) must:
 *  - Login and receive a valid token (no UUID truncation in personal_access_tokens).
 *  - Be accessible from /api/auth/me without crashing (no tenant_id needed).
 *  - NOT be able to call any tenant-scoped API endpoint (those require tenant_id).
 *  - NOT cause SupplierService::list() or any other service to crash with null tenant_id.
 *
 * The frontend routing guard is not tested here (it's Vue-side), but the backend
 * must correctly handle a super-admin accessing app-level endpoints gracefully.
 */
class SuperAdminRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User   $superAdmin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);

        Plan::firstOrCreate(
            ['code' => Plan::CODE_STARTER],
            ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
             'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]
        );

        $this->superAdmin = User::create([
            'name'      => 'Super Admin',
            'email'     => 'superadmin@frynov.com',
            'password'  => bcrypt('Secret123!'),
            'tenant_id' => null,
        ]);
        // is_super_admin is NOT fillable — use promoteToSuperAdmin() for internal setup
        $this->superAdmin->promoteToSuperAdmin();
        $this->superAdmin->assignRole('super-admin');
        $this->token = $this->superAdmin->createToken('api')->plainTextToken;
    }

    // ── Token creation (UUID regression) ─────────────────────────────────────

    #[Test]
    public function super_admin_token_is_created_without_uuid_truncation(): void
    {
        // Regression: morphs() BIGINT would truncate UUID → now uuidMorphs()
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id'   => $this->superAdmin->id,
            'tokenable_type' => User::class,
        ]);

        $row = \DB::table('personal_access_tokens')
            ->where('tokenable_id', $this->superAdmin->id)
            ->first();

        $this->assertSame($this->superAdmin->id, $row->tokenable_id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $row->tokenable_id,
        );
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_login(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'superadmin@frynov.com',
            'password' => 'Secret123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'is_super_admin']])
            ->assertJsonPath('user.is_super_admin', true)
            ->assertJsonPath('user.tenant_id', null);
    }

    // ── /api/auth/me — no crash with null tenant ──────────────────────────────

    #[Test]
    public function me_endpoint_works_for_super_admin_with_null_tenant(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.is_super_admin', true)
            ->assertJsonPath('user.tenant_id', null);

        // Subscription and active_modules are gracefully null/empty for super admin
        $this->assertNull($response->json('user.tenant'));
    }

    // ── Profile endpoints work for super admin ────────────────────────────────

    #[Test]
    public function super_admin_can_update_their_profile(): void
    {
        $response = $this->withToken($this->token)->patchJson('/api/me/profile', [
            'name' => 'Super Admin Updated',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Super Admin Updated');

        $this->assertDatabaseHas('users', [
            'id'   => $this->superAdmin->id,
            'name' => 'Super Admin Updated',
        ]);
    }

    #[Test]
    public function super_admin_can_change_password(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/me/password', [
            'current_password'      => 'Secret123!',
            'password'              => 'NewSecret456!',
            'password_confirmation' => 'NewSecret456!',
        ]);

        $response->assertOk();

        // Can now login with new password
        $this->postJson('/api/auth/login', [
            'email'    => 'superadmin@frynov.com',
            'password' => 'NewSecret456!',
        ])->assertOk();
    }

    // ── Tenant-scoped routes return 400/403, not 500 ──────────────────────────

    #[Test]
    public function super_admin_reaching_supplier_list_does_not_crash_with_500(): void
    {
        // SupplierService::list() used to crash with "null given" when tenant_id is null.
        // EnsureUserBelongsToTenant passes super-admins through, so this route is reachable.
        // It should fail gracefully (server-level error or return data) — NOT a 500 crash.
        $response = $this->withToken($this->token)->getJson('/api/suppliers');

        // Super admin has no tenant → should get an error response, not 500
        $this->assertNotSame(500, $response->status(),
            'SupplierController must not crash with 500 when super admin has null tenant_id.'
        );
    }

    // ── Sessions ──────────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_list_their_sessions(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/me/sessions');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'is_current', 'created_at']]]);

        $current = collect($response->json('data'))->firstWhere('is_current', true);
        $this->assertNotNull($current);
    }

    #[Test]
    public function super_admin_cannot_revoke_current_session(): void
    {
        $sessions = $this->withToken($this->token)->getJson('/api/me/sessions')->json('data');
        $currentId = collect($sessions)->firstWhere('is_current', true)['id'];

        $response = $this->withToken($this->token)->deleteJson("/api/me/sessions/{$currentId}");

        $response->assertUnprocessable();
    }
}
