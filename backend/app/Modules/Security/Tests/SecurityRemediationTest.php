<?php

namespace App\Modules\Security\Tests;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Category;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\ErpModulesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Executable acceptance suite for the security audit remediation.
 *
 * These tests intentionally encode the target security posture (fail-closed
 * modules, server-side permissions, tenant isolation, private uploads, and
 * verifiable audit logs). Implementations/refactors are valid only when these
 * tests pass in addition to the existing suite.
 */
class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    // This suite asserts the fail-closed / partial-provisioning posture itself.
    protected bool $autoProvisionModules = false;

    private Tenant $tenant;
    private User $admin;
    private User $viewer;
    private User $cashier;
    private User $manager;
    private string $adminToken;
    private string $viewerToken;
    private string $cashierToken;
    private string $managerToken;
    private ModuleRegistryService $modules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ErpModulesSeeder::class);

        Plan::firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'price_monthly_cents' => 0,
                'price_yearly_cents' => 0,
                'currency' => 'XOF',
                'trial_days' => 14,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 1,
            ],
        );

        $this->tenant = Tenant::create([
            'name' => 'Security Acceptance Tenant',
            'slug' => 'security-acceptance',
            'plan' => 'starter',
            'status' => 'active',
            'settings' => ['currency' => 'XOF'],
        ]);

        $this->admin = $this->makeTenantUser('Admin', 'admin@security.test', 'admin');
        $this->viewer = $this->makeTenantUser('Viewer', 'viewer@security.test', 'viewer');
        $this->cashier = $this->makeTenantUser('Cashier', 'cashier@security.test', 'cashier');
        $this->manager = $this->makeTenantUser('Manager', 'manager@security.test', 'manager');

        $this->adminToken = $this->admin->createToken('api')->plainTextToken;
        $this->viewerToken = $this->viewer->createToken('api')->plainTextToken;
        $this->cashierToken = $this->cashier->createToken('api')->plainTextToken;
        $this->managerToken = $this->manager->createToken('api')->plainTextToken;

        $this->modules = app(ModuleRegistryService::class);
    }

    private function makeTenantUser(string $name, string $email, string $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
        ]);
        $user->assignTenantRole($role);

        return $user;
    }

    /**
     * Every billable/optional backend module must be enforced server-side. Hidden
     * menus or disabled front-end tabs are not a security control.
     */
    #[Test]
    #[DataProvider('moduleRouteProvider')]
    public function inactive_modules_are_denied_for_all_sensitive_module_routes(string $inactiveModule, string $method, string $uri): void
    {
        $this->provisionTenantWithAnyOtherModuleThan($inactiveModule);

        $response = $this->withToken($this->adminToken)->json($method, $uri);

        $response->assertStatus(403)
            ->assertJsonPath('module', $inactiveModule);
    }

    public static function moduleRouteProvider(): array
    {
        return [
            'catalog products' => ['catalog', 'GET', '/api/catalog/products'],
            'inventory stock' => ['inventory', 'GET', '/api/inventory/stock'],
            'orders index' => ['orders', 'GET', '/api/orders'],
            'customers index' => ['customers', 'GET', '/api/customers'],
            'payments index' => ['payments', 'GET', '/api/payments'],
            'delivery index' => ['delivery', 'GET', '/api/deliveries'],
            'suppliers index' => ['suppliers', 'GET', '/api/suppliers'],
            'import history' => ['import_export', 'GET', '/api/import/history'],
            'reports sales' => ['reports', 'GET', '/api/reports/sales'],
        ];
    }

    #[Test]
    public function tenants_without_any_module_configuration_are_fail_closed_instead_of_fail_open(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/reports/sales')
            ->assertStatus(403)
            ->assertJsonPath('module', 'reports');
    }

    private function provisionTenantWithAnyOtherModuleThan(string $inactiveModule): void
    {
        $fallback = $inactiveModule === 'catalog' ? 'reports' : 'catalog';
        $this->modules->activate($this->tenant, $fallback, $this->admin->id);
    }

    #[Test]
    public function viewer_cannot_create_customer_payment_or_order_even_if_modules_are_active(): void
    {
        foreach (['customers', 'payments', 'orders'] as $module) {
            $this->modules->activate($this->tenant, $module, $this->admin->id);
        }

        $this->withToken($this->viewerToken)
            ->postJson('/api/customers', ['name' => 'Forbidden Customer'])
            ->assertStatus(403);

        $this->withToken($this->viewerToken)
            ->postJson('/api/payments', ['amount_cents' => 1000, 'currency' => 'XOF', 'method' => 'cash'])
            ->assertStatus(403);

        $this->withToken($this->viewerToken)
            ->postJson('/api/orders', [])
            ->assertStatus(403);
    }

    #[Test]
    public function cashier_can_create_payment_but_cannot_void_payment(): void
    {
        $this->modules->activate($this->tenant, 'payments', $this->admin->id);

        $paymentId = $this->withToken($this->cashierToken)
            ->postJson('/api/payments', ['amount_cents' => 1000, 'currency' => 'XOF', 'method' => 'cash'])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($this->cashierToken)
            ->deleteJson("/api/payments/{$paymentId}")
            ->assertStatus(403);
    }

    #[Test]
    public function manager_cannot_invite_or_temporarily_grant_another_manager(): void
    {
        $this->modules->activate($this->tenant, 'dashboard', $this->admin->id);

        $this->withToken($this->managerToken)
            ->postJson('/api/workspace/users', [
                'name' => 'Escalated Manager',
                'email' => 'escalated-manager@security.test',
                'role' => 'manager',
            ])
            ->assertStatus(403);

        $member = $this->makeTenantUser('Member', 'member@security.test', 'member');

        $this->withToken($this->managerToken)
            ->postJson("/api/workspace/users/{$member->id}/temporary-access", [
                'role' => 'manager',
                'expires_at' => now()->addDay()->toISOString(),
                'note' => 'attempted lateral escalation',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function category_parent_cannot_be_changed_to_a_category_from_another_tenant(): void
    {
        $this->modules->activate($this->tenant, 'catalog', $this->admin->id);

        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-security-tenant',
            'plan' => 'starter',
            'status' => 'active',
            'settings' => [],
        ]);

        $category = Category::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant Category',
            'slug' => 'tenant-category',
            'is_active' => true,
        ]);

        $foreignParent = Category::withoutTenantScope()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Parent',
            'slug' => 'foreign-parent',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/catalog/categories/{$category->id}", ['parent_id' => $foreignParent->id]);

        $this->assertContains($response->getStatusCode(), [403, 404, 422]);
        $this->assertNull($category->fresh()->parent_id, 'Cross-tenant parent assignment must not persist.');
    }

    #[Test]
    public function manual_payment_proofs_are_private_and_not_exposed_as_public_storage_urls(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $plan = Plan::firstOrCreate(
            ['code' => 'growth'],
            [
                'name' => 'Growth',
                'price_monthly_cents' => 25000,
                'price_yearly_cents' => 250000,
                'currency' => 'XOF',
                'trial_days' => 0,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 2,
            ],
        );

        $this->withToken($this->adminToken)
            ->post('/api/me/manual-payments', [
                'plan_code' => $plan->code,
                'amount_cents' => 25000,
                'currency' => 'XOF',
                'payment_method' => 'bank_transfer',
                'proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertCreated();

        $payment = ManualPayment::withoutTenantScope()->latest()->firstOrFail();

        $this->assertNotNull($payment->proof_path, 'The uploaded proof path must be stored for controlled retrieval.');
        Storage::disk('public')->assertMissing($payment->proof_path);

        $this->withToken($this->adminToken)
            ->getJson('/api/me/manual-payments')
            ->assertOk()
            ->assertJsonMissingPath('data.0.proof_url')
            ->assertJsonMissing(['proof_url' => Storage::disk('public')->url($payment->proof_path)]);
    }

    #[Test]
    public function audit_log_integrity_chain_verifies_clean_entries(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@security.test',
            'password' => bcrypt('password'),
            'tenant_id' => null,
        ]);
        $superAdmin->promoteToSuperAdmin();
        $token = $superAdmin->createToken('api')->plainTextToken;

        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'action' => 'security.acceptance.first',
            'subject_type' => 'test',
            'subject_id' => 'first',
            'old_values' => [],
            'new_values' => ['ok' => true],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'risk_level' => 'low',
        ]);

        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'action' => 'security.acceptance.second',
            'subject_type' => 'test',
            'subject_id' => 'second',
            'old_values' => ['ok' => true],
            'new_values' => ['ok' => true],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'risk_level' => 'low',
        ]);

        $this->withToken($token)
            ->postJson('/api/admin/audit-logs/verify-chain?limit=10')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('first_broken_id', null);
    }
}
