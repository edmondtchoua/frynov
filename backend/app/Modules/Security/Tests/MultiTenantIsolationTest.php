<?php

namespace App\Modules\Security\Tests;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Security tests: Cross-Tenant Data Isolation (OWASP API4:2023 — BOLA/IDOR).
 *
 * Verifies that Tenant A CANNOT access, modify, or enumerate resources
 * belonging to Tenant B — even when the resource ID is known.
 */
class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User   $userA;
    private User   $userB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'member',  'guard_name' => 'web']);

        $settings = ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr'];

        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'plan' => 'starter', 'status' => 'active', 'settings' => $settings]);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'plan' => 'starter', 'status' => 'active', 'settings' => $settings]);

        $this->userA = User::create(['name' => 'User A', 'email' => 'user@a.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenantA->id]);
        $this->userB = User::create(['name' => 'User B', 'email' => 'user@b.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenantB->id]);
        $this->userA->assignTenantRole('admin');
        $this->userB->assignTenantRole('admin');

        $this->tokenA = $this->userA->createToken('api')->plainTextToken;
        $this->tokenB = $this->userB->createToken('api')->plainTextToken;
    }

    // ── IDOR: Read another tenant's resource ──────────────────────────────────

    #[Test]
    public function tenant_a_gets_404_when_reading_product_of_tenant_b(): void
    {
        // Bypass TenantScope to create the product in Tenant B's context
        $productB = Product::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantB->id,
            'sku'            => 'PRD-B-001',
            'name'           => 'Secret Product B',
            'price_amount'   => 99999,
            'price_currency' => 'XOF',
            'status'         => 'active',
            'has_variants'   => false,
        ]);

        // Tenant A tries to access Tenant B's product by guessing its ID
        $this->withToken($this->tokenA)
            ->getJson("/api/catalog/products/{$productB->id}")
            ->assertNotFound(); // 404 — not 403 (avoids confirming resource existence)
    }

    #[Test]
    public function tenant_a_cannot_update_product_of_tenant_b(): void
    {
        $productB = Product::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id, 'sku' => 'PRD-B-002',
            'name' => 'Product B', 'price_amount' => 10000,
            'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false,
        ]);

        $this->withToken($this->tokenA)
            ->patchJson("/api/catalog/products/{$productB->id}", ['price_amount' => 1])
            ->assertNotFound();

        // Price must be unchanged
        $this->assertSame(10000, $productB->fresh()->price_amount);
    }

    #[Test]
    public function tenant_a_cannot_read_order_of_tenant_b(): void
    {
        $orderB = Order::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'number'       => 'ORD-B-00001',
            'status'       => 'confirmed',
            'total_amount' => 50000,
            'currency'     => 'XOF',
        ]);

        $this->withToken($this->tokenA)
            ->getJson("/api/orders/{$orderB->id}")
            ->assertNotFound();
    }

    // ── Listing leaks: ensure lists are scoped ─────────────────────────────────

    #[Test]
    public function product_list_returns_only_own_tenant_products(): void
    {
        // 3 products for Tenant A
        for ($i = 1; $i <= 3; $i++) {
            Product::withoutTenantScope()->create([
                'tenant_id' => $this->tenantA->id, 'sku' => "PRD-A-00{$i}",
                'name' => "Product A{$i}", 'price_amount' => 1000,
                'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false,
            ]);
        }
        // 5 products for Tenant B — must NEVER appear in A's response
        for ($i = 1; $i <= 5; $i++) {
            Product::withoutTenantScope()->create([
                'tenant_id' => $this->tenantB->id, 'sku' => "PRD-B-{$i}00",
                'name' => "Product B{$i}", 'price_amount' => 2000,
                'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false,
            ]);
        }

        $response = $this->withToken($this->tokenA)->getJson('/api/catalog/products');
        $response->assertOk();

        $ids     = collect($response->json('data'))->pluck('id')->toArray();
        $tenantIds = Product::withoutTenantScope()->whereIn('id', $ids)->pluck('tenant_id')->unique()->toArray();

        $this->assertCount(3, $ids, 'List must contain exactly 3 products (Tenant A only)');
        $this->assertSame([$this->tenantA->id], array_values($tenantIds), 'All returned products must belong to Tenant A');
    }

    #[Test]
    public function workspace_users_list_does_not_leak_other_tenant_users(): void
    {
        $response = $this->withToken($this->tokenA)->getJson('/api/workspace/users');

        $response->assertOk();
        $userIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertNotContains($this->userB->id, $userIds, 'User B must not appear in Tenant A user list');
    }

    // ── IDOR: Mutating another tenant's users ─────────────────────────────────

    #[Test]
    public function tenant_a_cannot_deactivate_user_of_tenant_b(): void
    {
        $this->withToken($this->tokenA)
            ->deleteJson("/api/workspace/users/{$this->userB->id}")
            ->assertNotFound();

        $this->assertNull($this->userB->fresh()->deleted_at, 'User B must not be deactivated');
    }

    #[Test]
    public function tenant_a_cannot_change_role_of_user_of_tenant_b(): void
    {
        $this->withToken($this->tokenA)
            ->patchJson("/api/workspace/users/{$this->userB->id}", ['role' => 'viewer'])
            ->assertNotFound();

        $this->assertTrue($this->userB->fresh()->hasRole('admin'), 'User B role must be unchanged');
    }

    // ── Mass Assignment: is_super_admin cannot be injected via API ────────────

    #[Test]
    public function is_super_admin_cannot_be_mass_assigned_via_profile_update(): void
    {
        $this->withToken($this->tokenA)
            ->patchJson('/api/me/profile', [
                'name'           => 'Hacker Name',
                'is_super_admin' => true,     // MUST be ignored
            ])
            ->assertOk();

        $this->assertFalse($this->userA->fresh()->is_super_admin, 'is_super_admin must NOT be mass-assignable');
    }

    // ── Privilege escalation: role hierarchy enforcement ──────────────────────

    #[Test]
    public function manager_cannot_promote_user_to_admin(): void
    {
        $manager = User::create(['name' => 'Manager', 'email' => 'mgr@a.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenantA->id]);
        $manager->assignTenantRole('manager');
        $managerToken = $manager->createToken('api')->plainTextToken;

        $member = User::create(['name' => 'Member', 'email' => 'mem@a.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenantA->id]);
        $member->assignTenantRole('member');

        $this->withToken($managerToken)
            ->patchJson("/api/workspace/users/{$member->id}", ['role' => 'admin'])
            ->assertUnprocessable(); // 422 — role hierarchy violation

        $this->assertFalse($member->fresh()->hasRole('admin'), 'Member must not be promoted to admin by manager');
    }

    #[Test]
    public function member_cannot_change_any_roles(): void
    {
        $member = User::create(['name' => 'Member', 'email' => 'mem2@a.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenantA->id]);
        $member->assignTenantRole('member');
        $memberToken = $member->createToken('api')->plainTextToken;

        $other = User::create(['name' => 'Other', 'email' => 'oth@a.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenantA->id]);
        $other->assignTenantRole('viewer');

        $this->withToken($memberToken)
            ->patchJson("/api/workspace/users/{$other->id}", ['role' => 'manager'])
            ->assertForbidden(); // 403 — not authorized (member has no role-change right)
    }

    // ── Price manipulation: client-supplied price must be ignored ─────────────

    #[Test]
    public function order_uses_database_price_not_client_supplied_price(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $product = Product::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantA->id,
            'sku'            => 'PRD-PRICE-001',
            'name'           => 'Expensive Product',
            'price_amount'   => 100_000_00, // 100 000 XOF
            'price_currency' => 'XOF',
            'status'         => 'active',
            'has_variants'   => false,
        ]);

        $response = $this->withToken($this->tokenA)->postJson('/api/orders', [
            'items' => [[
                'product_id'       => $product->id,
                'quantity'         => 1,
                'unit_price_cents' => 1, // ATTACK: try to buy at 1 centime
            ]],
        ]);

        $response->assertStatus(201);

        // The order must use the DB price (100 000 XOF), not the client-supplied 1 centime
        $order = Order::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->latest()->first();
        $this->assertSame(100_000_00, $order->total_amount, 'Price manipulation attack must be blocked');
    }
}
