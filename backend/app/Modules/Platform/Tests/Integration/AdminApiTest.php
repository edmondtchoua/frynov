<?php

namespace App\Modules\Platform\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Platform\Models\ErpModule;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $regularUser;
    private string $adminToken;
    private string $userToken;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Client Corp',
            'slug'   => 'client-corp',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        // is_super_admin is NOT fillable (security: prevent mass-assignment).
        // Use forceFill() or promoteToSuperAdmin() for internal test setup.
        $this->superAdmin = User::create([
            'name'      => 'Super Admin',
            'email'     => 'superadmin@nexora.com',
            'password'  => Hash::make('Admin123!'),
            'tenant_id' => null,
        ]);
        $this->superAdmin->promoteToSuperAdmin();

        $this->regularUser = User::create([
            'name'      => 'Regular User',
            'email'     => 'user@client-corp.sn',
            'password'  => Hash::make('User123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->adminToken = $this->superAdmin->createToken('api')->plainTextToken;
        $this->userToken  = $this->regularUser->createToken('api')->plainTextToken;
    }

    private function adminAuth(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    private function userAuth(): array
    {
        return ['Authorization' => "Bearer {$this->userToken}"];
    }

    // ── Access control ────────────────────────────────────────────────────────

    #[Test]
    public function regular_user_cannot_access_admin_dashboard(): void
    {
        $this->getJson('/api/admin/dashboard', $this->userAuth())
            ->assertStatus(403);
    }

    #[Test]
    public function guest_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/admin/dashboard')
            ->assertStatus(401);
    }

    #[Test]
    public function super_admin_can_access_admin_dashboard(): void
    {
        $this->getJson('/api/admin/dashboard', $this->adminAuth())
            ->assertStatus(200)
            ->assertJsonStructure(['overview', 'subscriptions', 'recent_tenants', 'recent_logs']);
    }

    // ── Tenant management ──────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_list_tenants(): void
    {
        $this->getJson('/api/admin/tenants', $this->adminAuth())
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    #[Test]
    public function super_admin_can_view_tenant_details(): void
    {
        $this->getJson("/api/admin/tenants/{$this->tenant->id}", $this->adminAuth())
            ->assertStatus(200)
            ->assertJsonPath('tenant.id', $this->tenant->id);
    }

    #[Test]
    public function super_admin_can_suspend_a_tenant(): void
    {
        Plan::create([
            'code'                => Plan::CODE_STARTER,
            'name'                => 'Starter',
            'price_monthly_cents' => 0,
            'price_yearly_cents'  => 0,
            'currency'            => 'XOF',
            'trial_days'          => 14,
            'is_active'           => true,
            'is_public'           => true,
            'sort_order'          => 1,
        ]);

        $plan = Plan::where('code', Plan::CODE_STARTER)->first();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => $plan->id,
            'status'    => Subscription::STATUS_ACTIVE,
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);

        $this->postJson("/api/admin/tenants/{$this->tenant->id}/suspend", [
            'reason' => 'Non-paiement',
        ], $this->adminAuth())
            ->assertStatus(200);

        $this->tenant->refresh();
        $this->assertSame('suspended', $this->tenant->status);
    }

    #[Test]
    public function super_admin_can_search_tenants(): void
    {
        Tenant::create(['name' => 'Alpha Corp', 'slug' => 'alpha-corp', 'plan' => 'starter', 'status' => 'active']);
        Tenant::create(['name' => 'Beta Ltd',   'slug' => 'beta-ltd',   'plan' => 'starter', 'status' => 'active']);

        $response = $this->getJson('/api/admin/tenants?search=Alpha', $this->adminAuth());

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Alpha Corp', $data[0]['name']);
    }

    // ── Module management ──────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_list_modules(): void
    {
        ErpModule::create([
            'code'       => 'catalog',
            'name'       => 'Catalogue',
            'category'   => ErpModule::CATEGORY_OPERATIONS,
            'status'     => ErpModule::STATUS_ACTIVE,
            'is_core'    => false,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/admin/modules', $this->adminAuth())
            ->assertStatus(200)
            ->assertJsonCount(1);
    }

    #[Test]
    public function super_admin_can_update_module_visibility(): void
    {
        $module = ErpModule::create([
            'code'       => 'catalog',
            'name'       => 'Catalogue',
            'category'   => ErpModule::CATEGORY_OPERATIONS,
            'status'     => ErpModule::STATUS_ACTIVE,
            'is_core'    => false,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->patchJson("/api/admin/modules/{$module->id}", [
            'is_visible' => false,
        ], $this->adminAuth())
            ->assertStatus(200)
            ->assertJsonPath('is_visible', false);
    }

    // ── Plans ──────────────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_list_plans(): void
    {
        Plan::create([
            'code'                => Plan::CODE_STARTER,
            'name'                => 'Starter',
            'price_monthly_cents' => 0,
            'price_yearly_cents'  => 0,
            'currency'            => 'XOF',
            'trial_days'          => 14,
            'is_active'           => true,
            'is_public'           => true,
            'sort_order'          => 1,
        ]);

        $this->getJson('/api/admin/plans', $this->adminAuth())
            ->assertStatus(200);
    }

    #[Test]
    public function super_admin_can_update_plan_limits(): void
    {
        $plan = Plan::create([
            'code'                => Plan::CODE_STARTER,
            'name'                => 'Starter',
            'price_monthly_cents' => 0,
            'price_yearly_cents'  => 0,
            'currency'            => 'XOF',
            'trial_days'          => 14,
            'is_active'           => true,
            'is_public'           => true,
            'sort_order'          => 1,
        ]);

        $this->patchJson("/api/admin/plans/{$plan->id}", [
            'limits' => [
                'max_products'   => 250,
                'max_warehouses' => 2,
                'storage_mb'     => 500,
            ],
        ], $this->adminAuth())
            ->assertStatus(200)
            ->assertJsonPath('limits.max_products', 250);

        $this->assertDatabaseHas('plan_limits', [
            'plan_id'        => $plan->id,
            'max_products'   => 250,
            'max_warehouses' => 2,
            'storage_mb'     => 500,
        ]);
    }

    #[Test]
    public function updating_a_legacy_quota_field_mirrors_into_plan_limits(): void
    {
        $plan = Plan::create([
            'code'                => Plan::CODE_STARTER,
            'name'                => 'Starter',
            'price_monthly_cents' => 0,
            'price_yearly_cents'  => 0,
            'currency'            => 'XOF',
            'trial_days'          => 14,
            'is_active'           => true,
            'is_public'           => true,
            'sort_order'          => 1,
        ]);
        // Pre-existing canonical row with a stale ceiling.
        $plan->limits()->create(['max_products' => 10]);

        $this->patchJson("/api/admin/plans/{$plan->id}", [
            'max_products' => 999,
        ], $this->adminAuth())->assertStatus(200);

        // The legacy edit must propagate to plan_limits (the row QuotaService reads first),
        // otherwise the admin's change would be silently ignored at enforcement time.
        $this->assertDatabaseHas('plan_limits', ['plan_id' => $plan->id, 'max_products' => 999]);
    }

    // ── Audit log ─────────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_access_audit_logs(): void
    {
        $this->getJson('/api/admin/audit-logs', $this->adminAuth())
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    // ── Module activation for tenant ──────────────────────────────────────────

    #[Test]
    public function super_admin_can_activate_module_for_tenant(): void
    {
        ErpModule::create([
            'code'       => 'catalog',
            'name'       => 'Catalogue',
            'category'   => ErpModule::CATEGORY_OPERATIONS,
            'status'     => ErpModule::STATUS_ACTIVE,
            'is_core'    => false,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->postJson(
            "/api/admin/tenants/{$this->tenant->id}/modules/catalog/activate",
            [],
            $this->adminAuth()
        )->assertStatus(200);

        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
