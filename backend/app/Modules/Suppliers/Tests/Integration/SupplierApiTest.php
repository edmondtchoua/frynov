<?php

namespace App\Modules\Suppliers\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer',  'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->tenant = Tenant::create([
            'name' => 'Test', 'slug' => 'test', 'plan' => 'starter', 'status' => 'active', 'settings' => [],
        ]);
        $this->user = User::create([
            'name'      => 'User',
            'email'     => 'user@test.com',
            'password'  => Hash::make('pass'),
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user->assignTenantRole('admin');
    }

    #[Test]
    public function it_requires_auth(): void
    {
        $this->getJson('/api/suppliers')->assertStatus(401);
    }

    #[Test]
    public function it_lists_suppliers_for_tenant(): void
    {
        Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'S1', 'status' => 'active']);
        Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'S2', 'status' => 'active']);

        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);
        Supplier::create(['tenant_id' => $otherTenant->id, 'name' => 'Other S', 'status' => 'active']);

        $response = $this->actingAs($this->user)->getJson('/api/suppliers');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_creates_a_supplier(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/suppliers', [
                'name'  => 'TextilePro',
                'email' => 'contact@textilepro.com',
                'phone' => '+225 07 00 00 00',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'TextilePro');
    }

    #[Test]
    public function it_shows_a_supplier(): void
    {
        $supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Show Me', 'status' => 'active']);

        $this->actingAs($this->user)
            ->getJson("/api/suppliers/{$supplier->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Show Me');
    }

    #[Test]
    public function it_updates_a_supplier(): void
    {
        $supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Old', 'status' => 'active']);

        $this->actingAs($this->user)
            ->putJson("/api/suppliers/{$supplier->id}", ['name' => 'New', 'phone' => '+1 000'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New');
    }

    #[Test]
    public function it_deletes_a_supplier(): void
    {
        $supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Del', 'status' => 'active']);

        $this->actingAs($this->user)
            ->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    #[Test]
    public function it_searches_suppliers(): void
    {
        Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'TextilePro', 'status' => 'active']);
        Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Agro Industries', 'status' => 'active']);

        $resp = $this->actingAs($this->user)
            ->getJson('/api/suppliers/search?q=textile')
            ->assertStatus(200);

        $this->assertCount(1, $resp->json('data'));
    }

    #[Test]
    public function it_cannot_access_other_tenant_supplier(): void
    {
        $other   = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);
        $supplier = Supplier::create(['tenant_id' => $other->id, 'name' => 'Private', 'status' => 'active']);

        $this->actingAs($this->user)
            ->getJson("/api/suppliers/{$supplier->id}")
            ->assertStatus(404);
    }
}
