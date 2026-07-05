<?php

namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sprint 16 — Tests for multi-axis variant generation (cartesian product).
 */
class VariantGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Product $product;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer',  'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true,
            'is_public' => true, 'sort_order' => 1,
        ]);
        $this->tenant = Tenant::create([
            'name' => 'T', 'slug' => 'var-gen', 'plan' => 'starter',
            'status' => 'active', 'settings' => [],
        ]);
        $this->admin  = User::create([
            'name' => 'A', 'email' => 'a@var-gen.sn',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->assignTenantRole('admin');
        $this->adminToken = $this->admin->createToken('api')->plainTextToken;

        $this->product = Product::withoutTenantScope()->create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PROD-001',
            'name'           => 'Boubou Bazin',
            'price_amount'   => 25000,
            'price_currency' => 'XOF',
            'status'         => 'active',
            'has_variants'   => true,
        ]);
    }

    #[Test]
    public function generates_single_axis_variants(): void
    {
        $resp = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$this->product->id}/variants/generate", [
                'axes'          => [['name' => 'Taille', 'values' => ['S', 'M', 'L']]],
                'base_price'    => 25000,
                'base_currency' => 'XOF',
            ])->assertOk();

        $this->assertSame(3, $resp->json('created'));
        $this->assertSame(0, $resp->json('skipped'));
        $this->assertDatabaseCount('product_variants', 3);

        // Check labels
        $this->assertDatabaseHas('product_variants', ['label' => 'S', 'product_id' => $this->product->id]);
        $this->assertDatabaseHas('product_variants', ['label' => 'M', 'product_id' => $this->product->id]);
        $this->assertDatabaseHas('product_variants', ['label' => 'L', 'product_id' => $this->product->id]);
    }

    #[Test]
    public function generates_multi_axis_cartesian_product(): void
    {
        $resp = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$this->product->id}/variants/generate", [
                'axes' => [
                    ['name' => 'Taille',  'values' => ['S', 'M', 'L']],
                    ['name' => 'Couleur', 'values' => ['Rouge', 'Bleu']],
                ],
                'base_price' => 25000, 'base_currency' => 'XOF',
            ])->assertOk();

        // 3 tailles × 2 couleurs = 6 combinaisons
        $this->assertSame(6, $resp->json('created'));
        $this->assertSame(6, $resp->json('total_combinations'));
        $this->assertDatabaseCount('product_variants', 6);

        $this->assertDatabaseHas('product_variants', ['label' => 'S / Rouge']);
        $this->assertDatabaseHas('product_variants', ['label' => 'L / Bleu']);
    }

    #[Test]
    public function skips_already_existing_variants(): void
    {
        // Pre-create variant with label 'S'
        ProductVariant::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'label' => 'S', 'sku' => 'PROD-001-V1',
            'price_amount' => 25000, 'price_currency' => 'XOF',
        ]);

        $resp = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$this->product->id}/variants/generate", [
                'axes' => [['name' => 'Taille', 'values' => ['S', 'M', 'L']]],
                'base_price' => 25000, 'base_currency' => 'XOF',
            ])->assertOk();

        $this->assertSame(2, $resp->json('created')); // M and L only
        $this->assertSame(1, $resp->json('skipped')); // S skipped
        $this->assertDatabaseCount('product_variants', 3); // 1 pre-existing + 2 new
    }

    #[Test]
    public function viewer_cannot_generate_variants(): void
    {
        $viewer = User::create([
            'name' => 'V', 'email' => 'v@var-gen.sn',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id,
        ]);
        $viewer->assignTenantRole('viewer');
        $viewerToken = $viewer->createToken('api')->plainTextToken;

        $this->withToken($viewerToken)
            ->postJson("/api/catalog/products/{$this->product->id}/variants/generate", [
                'axes' => [['name' => 'Taille', 'values' => ['S']]],
            ])->assertStatus(403);
    }
}
