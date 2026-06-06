<?php

namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LabelApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer',  'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create([
            'name'     => 'Boutique Dakar',
            'slug'     => 'boutique-dakar',
            'plan'     => 'starter',
            'status'   => 'active',
            'settings' => ['currency' => 'XOF'],
        ]);

        $this->user = User::create([
            'name'      => 'Propriétaire',
            'email'     => 'owner@boutique-dakar.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->user->assignTenantRole('admin');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'VET-0001',
            'name'           => 'Boubou Sénégalais',
            'price_amount'   => 25000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);
    }

    #[Test]
    public function it_returns_thermal_label_html(): void
    {
        $response = $this->withToken($this->token)
            ->get("/api/catalog/products/{$this->product->id}/label?format=thermal&copies=1");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $html = $response->getContent();
        $this->assertStringContainsString('Boubou Sénégalais', $html);
        $this->assertStringContainsString('VET-0001', $html);
        $this->assertStringContainsString('58mm', $html);
        $this->assertStringContainsString('<svg', $html); // barcode SVG
    }

    #[Test]
    public function it_returns_a4_sheet_html(): void
    {
        $response = $this->withToken($this->token)
            ->get("/api/catalog/products/{$this->product->id}/label?format=a4sheet&copies=24");

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('A4', $html);
        // 24 copies → 24 label divs with data-sku attribute
        $this->assertEquals(24, substr_count($html, 'data-sku="VET-0001"'));
    }

    #[Test]
    public function it_repeats_label_for_delivery_quantity(): void
    {
        // Scenario: received 10 units → print 10 labels
        $response = $this->withToken($this->token)
            ->get("/api/catalog/products/{$this->product->id}/label?copies=10");

        $response->assertOk();
        $this->assertEquals(10, substr_count($response->getContent(), 'data-sku="VET-0001"'));
    }

    #[Test]
    public function it_generates_batch_labels_for_delivery(): void
    {
        $product2 = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'CHN-0001',
            'name'           => 'Tissu Bazin',
            'price_amount'   => 8000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $response = $this->withToken($this->token)->postJson('/api/catalog/products/labels/batch', [
            'format' => 'thermal',
            'items'  => [
                ['product_id' => $this->product->id, 'copies' => 30],
                ['product_id' => $product2->id,       'copies' => 15],
            ],
        ]);

        $response->assertOk();
        $html = $response->getContent();

        $this->assertEquals(30, substr_count($html, 'data-sku="VET-0001"'));
        $this->assertEquals(15, substr_count($html, 'data-sku="CHN-0001"'));
        $this->assertEquals(45, substr_count($html, 'data-sku="')); // 45 label divs total
    }

    #[Test]
    public function it_hides_price_when_requested(): void
    {
        $response = $this->withToken($this->token)
            ->get("/api/catalog/products/{$this->product->id}/label?price=0");

        $response->assertOk();
        // Price not shown — '250.00 XOF' should not appear
        $this->assertStringNotContainsString('250.00 XOF', $response->getContent());
    }

    #[Test]
    public function it_shows_sale_badge_when_product_is_on_sale(): void
    {
        $this->product->update(['compare_at_price_amount' => 35000]); // was 350 XOF

        $response = $this->withToken($this->token)
            ->get("/api/catalog/products/{$this->product->id}/label");

        $response->assertOk();
        $this->assertStringContainsString('PROMO', $response->getContent());
    }

    #[Test]
    public function it_rejects_batch_exceeding_max_labels(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/catalog/products/labels/batch', [
            'format' => 'thermal',
            'items'  => [
                ['product_id' => $this->product->id, 'copies' => 5001],
            ],
        ]);

        $response->assertUnprocessable(); // 422 — copies max 500 per item
    }
}
