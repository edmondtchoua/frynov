<?php
namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private User $adminA;
    private User $viewerA;
    private string $adminToken;
    private string $viewerToken;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'manager', 'member', 'viewer'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->tenantA = Tenant::create(['name' => 'A', 'slug' => 'cat-sec-a', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->adminA  = User::create(['name' => 'A', 'email' => 'a@cat-sec.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenantA->id]);
        $this->adminA->assignTenantRole('admin');
        $this->adminToken  = $this->adminA->createToken('api')->plainTextToken;
        $this->viewerA = User::create(['name' => 'V', 'email' => 'v@cat-sec.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenantA->id]);
        $this->viewerA->assignTenantRole('viewer');
        $this->viewerToken = $this->viewerA->createToken('api')->plainTextToken;
        // Ensure Spatie team context is set for each test (prevents team-context leakage between tests)
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->tenantA->id);
    }

    #[Test]
    public function sku_lookup_requires_authentication(): void
    {
        $this->getJson('/api/catalog/products/sku/NO-SKU')
            ->assertStatus(401);
    }

    #[Test]
    public function viewer_cannot_create_product(): void
    {
        $this->withToken($this->viewerToken)
            ->postJson('/api/catalog/products', ['name' => 'Test', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active'])
            ->assertStatus(403);
    }

    #[Test]
    public function admin_can_create_product(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/catalog/products', ['name' => 'Prod test', 'price_amount' => 5000, 'price_currency' => 'XOF', 'status' => 'draft'])
            ->assertStatus(201);
    }

    #[Test]
    public function viewer_cannot_create_category(): void
    {
        $this->withToken($this->viewerToken)
            ->postJson('/api/catalog/categories', ['name' => 'Cat'])
            ->assertStatus(403);
    }

    #[Test]
    public function viewer_cannot_create_category_or_delete(): void
    {
        // viewer cannot POST (create)
        $this->withToken($this->viewerToken)
            ->postJson('/api/catalog/categories', ['name' => 'Attempt'])
            ->assertStatus(403);

        // viewer cannot PUT (update) - test with a fake ID
        $this->withToken($this->viewerToken)
            ->putJson('/api/catalog/categories/00000000-0000-0000-0000-000000000000', ['name' => 'Updated'])
            ->assertStatus(403);
    }

    #[Test]
    public function viewer_can_list_products(): void
    {
        $this->withToken($this->viewerToken)
            ->getJson('/api/catalog/products')
            ->assertOk();
    }

    #[Test]
    public function viewer_can_list_categories(): void
    {
        $this->withToken($this->viewerToken)
            ->getJson('/api/catalog/categories')
            ->assertOk();
    }
}
