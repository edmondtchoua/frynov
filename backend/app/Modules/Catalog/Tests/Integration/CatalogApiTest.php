<?php

namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->token = $this->user->createToken('api')->plainTextToken;
    }

    // ── Products ──────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_product_with_auto_sku(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/catalog/products', [
            'name'           => 'Boubou Sénégalais',
            'price_amount'   => 25000,
            'price_currency' => 'XOF',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Boubou Sénégalais')
            ->assertJsonPath('data.price.amount', 25000)
            ->assertJsonPath('data.price.currency', 'XOF')
            ->assertJsonPath('data.status', 'draft');

        $this->assertMatchesRegularExpression('/^PRD-\d{4}$/', $response->json('data.sku'));
    }

    #[Test]
    public function it_creates_a_product_with_custom_sku(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/catalog/products', [
            'name'           => 'Tissu Bazin',
            'sku'            => 'TIS-001',
            'price_amount'   => 8000,
            'price_currency' => 'XOF',
        ]);

        $response->assertCreated()->assertJsonPath('data.sku', 'TIS-001');
    }

    #[Test]
    public function it_lists_products_for_tenant(): void
    {
        Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0001',
            'name'           => 'Produit A',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
        ]);

        Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0002',
            'name'           => 'Produit B',
            'price_amount'   => 20000,
            'price_currency' => 'XOF',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/catalog/products');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_products_by_status(): void
    {
        Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0001',
            'name'           => 'Actif',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0002',
            'name'           => 'Brouillon',
            'price_amount'   => 5000,
            'price_currency' => 'XOF',
            'status'         => 'draft',
        ]);

        $response = $this->withToken($this->token)->getJson('/api/catalog/products?status=active');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'active');
    }

    #[Test]
    public function it_archives_a_product(): void
    {
        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0001',
            'name'           => 'Produit Test',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->patchJson("/api/catalog/products/{$product->id}/archive");

        $response->assertOk();
        $this->assertEquals('archived', $product->fresh()->status);
    }

    #[Test]
    public function it_finds_product_by_sku(): void
    {
        Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'VET-0001',
            'name'           => 'Caftan',
            'price_amount'   => 35000,
            'price_currency' => 'XOF',
        ]);

        // Public endpoint — no auth needed (for POS scanner)
        $response = $this->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/catalog/products/sku/VET-0001');

        $response->assertOk()->assertJsonPath('data.sku', 'VET-0001');
    }

    // ── Product codes ─────────────────────────────────────────────────────

    #[Test]
    public function it_returns_qr_code_svg(): void
    {
        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0001',
            'name'           => 'Produit Test',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
        ]);

        $response = $this->withToken($this->token)
            ->get("/api/catalog/products/{$product->id}/qrcode");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertStringStartsWith('<svg', $response->getContent());
    }

    #[Test]
    public function it_returns_barcode_svg(): void
    {
        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0001',
            'name'           => 'Produit Test',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
        ]);

        $response = $this->withToken($this->token)
            ->get("/api/catalog/products/{$product->id}/barcode");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertStringContainsString('<svg', $response->getContent());
    }

    #[Test]
    public function it_returns_code_sheet_as_json(): void
    {
        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0001',
            'name'           => 'Produit Test',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/catalog/products/{$product->id}/codes");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['sku', 'qr' => ['format', 'data'], 'barcode' => ['format', 'type', 'data']]]);
    }

    // ── Categories ────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_category_with_auto_slug(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/catalog/categories', [
            'name' => 'Vêtements Africains',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Vêtements Africains')
            ->assertJsonPath('data.slug', 'vetements-africains');
    }

    #[Test]
    public function it_creates_product_variant(): void
    {
        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0001',
            'name'           => 'Boubou',
            'price_amount'   => 25000,
            'price_currency' => 'XOF',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/catalog/products/{$product->id}/variants", [
                'attributes' => ['color' => 'bleu', 'size' => 'L'],
                'name'       => 'Bleu / L',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.attributes.color', 'bleu')
            ->assertJsonPath('data.price.inherited', true); // inherited from product

        $this->assertMatchesRegularExpression('/^PRD-0001-V\d+$/', $response->json('data.sku'));
    }
}
