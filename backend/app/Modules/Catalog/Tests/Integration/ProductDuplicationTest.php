<?php

namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeValue;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Duplication assistée sécurisée (P1) — preuve end-to-end de la politique §5bis :
 * régénération des identifiants uniques, non-copie du stock/des identifiants,
 * copie des attributs/variantes, nœud-seul pour les catégories, RBAC, isolation tenant.
 */
class ProductDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $adminToken;
    private string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'manager', 'member'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Boutique', 'slug' => 'dup-shop', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF']]);

        $admin = User::create(['name' => 'A', 'email' => 'a@dup-shop.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $admin->assignTenantRole('admin');
        $this->adminToken = $admin->createToken('api')->plainTextToken;

        $member = User::create(['name' => 'M', 'email' => 'm@dup-shop.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $member->assignTenantRole('member');
        $this->memberToken = $member->createToken('api')->plainTextToken;
    }

    /** Crée un produit source complet : catégorie, attribut+valeurs, variante (pivot), codes uniques. */
    private function sourceProduct(): Product
    {
        $category = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Téléphones', 'slug' => 'tel']);

        $product = Product::create([
            'tenant_id'        => $this->tenant->id,
            'category_id'      => $category->id,
            'sku'              => 'SRC-0001',
            'name'             => 'Téléphone X',
            'description'      => 'Un smartphone',
            'price_amount'     => 50000,
            'price_currency'   => 'XOF',
            'cost_amount'      => 30000,
            'status'           => 'active',
            'product_type'     => 'variable',
            'has_variants'     => true,
            'barcode'          => '1234567890123',
            'internal_barcode' => 'INT-SRC-0001',
            'gtin'             => '00012345678905',
        ]);

        $attr   = ProductAttribute::create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'name' => 'Couleur', 'code' => 'color', 'type' => 'select', 'position' => 0]);
        $red    = ProductAttributeValue::create(['attribute_id' => $attr->id, 'label' => 'Rouge', 'value' => 'red', 'position' => 0]);
        $blue   = ProductAttributeValue::create(['attribute_id' => $attr->id, 'label' => 'Bleu',  'value' => 'blue', 'position' => 1]);

        $vRed = ProductVariant::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'sku' => 'SRC-0001-V1',
            'label' => 'Rouge', 'attributes' => ['color' => 'Rouge'], 'price_amount' => 50000, 'price_currency' => 'XOF',
            'barcode' => '9990000000001', 'sort_order' => 0, 'is_active' => true,
        ]);
        $vRed->attributeValues()->sync([$red->id]);

        $vBlue = ProductVariant::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'sku' => 'SRC-0001-V2',
            'label' => 'Bleu', 'attributes' => ['color' => 'Bleu'], 'price_amount' => 50000, 'price_currency' => 'XOF',
            'barcode' => '9990000000002', 'sort_order' => 1, 'is_active' => true,
        ]);
        $vBlue->attributeValues()->sync([$blue->id]);

        return $product;
    }

    // ── Preview ────────────────────────────────────────────────────────────────

    #[Test]
    public function preview_describes_the_duplication_without_persisting(): void
    {
        $source = $this->sourceProduct();
        $before = Product::withoutTenantScope()->count();

        $res = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$source->id}/duplicate-preview")
            ->assertOk();

        $res->assertJsonPath('data.result.name', 'Téléphone X (copie)')
            ->assertJsonPath('data.result.status', 'draft')
            ->assertJsonPath('data.result.variants_count', 2);
        $this->assertContains('sku', $res->json('data.regenerated'));
        $this->assertContains('barcode', $res->json('data.cleared'));
        $this->assertContains('stock', $res->json('data.excluded'));

        // Rien n'a été créé.
        $this->assertSame($before, Product::withoutTenantScope()->count());
    }

    // ── Produit ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_regenerates_identifiers_clears_codes_and_forces_draft(): void
    {
        $source = $this->sourceProduct();

        $res = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$source->id}/duplicate")
            ->assertCreated();

        $newId = $res->json('data.id');
        $new   = Product::withoutTenantScope()->findOrFail($newId);

        $this->assertSame('Téléphone X (copie)', $new->name);
        $this->assertSame('draft', $new->status);              // jamais publié
        $this->assertNotSame($source->sku, $new->sku);          // SKU régénéré
        $this->assertNotEmpty($new->sku);
        $this->assertNotSame($source->internal_barcode, $new->internal_barcode); // régénéré
        $this->assertNull($new->barcode);                       // vidé
        $this->assertNull($new->gtin);                          // vidé
        // Config catalogue copiée
        $this->assertSame($source->price_amount, $new->price_amount);
        $this->assertSame($source->cost_amount, $new->cost_amount);
        $this->assertSame($source->category_id, $new->category_id);
    }

    #[Test]
    public function it_copies_variants_with_regenerated_skus_and_cleared_barcodes(): void
    {
        $source = $this->sourceProduct();

        $newId = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$source->id}/duplicate")
            ->assertCreated()->json('data.id');

        $variants = ProductVariant::withoutTenantScope()->where('product_id', $newId)->get();
        $this->assertCount(2, $variants);

        $sourceSkus = ['SRC-0001-V1', 'SRC-0001-V2'];
        foreach ($variants as $v) {
            $this->assertNotContains($v->sku, $sourceSkus, 'le SKU variante doit être régénéré');
            $this->assertNull($v->barcode, 'le barcode variante doit être vidé');
        }
    }

    #[Test]
    public function it_copies_attributes_values_and_relinks_variant_pivot(): void
    {
        $source = $this->sourceProduct();

        $newId = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$source->id}/duplicate")
            ->assertCreated()->json('data.id');

        $attrs = ProductAttribute::withoutTenantScope()->where('product_id', $newId)->with('values')->get();
        $this->assertCount(1, $attrs);
        $this->assertSame('color', $attrs->first()->code);
        $this->assertCount(2, $attrs->first()->values);

        // Les valeurs sont de NOUVELLES lignes (ids différents de la source).
        $newValueIds    = $attrs->first()->values->pluck('id')->all();
        $sourceValueIds = ProductAttributeValue::whereIn('attribute_id',
            ProductAttribute::withoutTenantScope()->where('product_id', $source->id)->pluck('id'))->pluck('id')->all();
        $this->assertEmpty(array_intersect($newValueIds, $sourceValueIds));

        // Le pivot variante↔valeur est re-lié vers les nouvelles valeurs.
        $variant = ProductVariant::withoutTenantScope()->where('product_id', $newId)->with('attributeValues')->get()
            ->firstWhere(fn ($v) => $v->attributeValues->isNotEmpty());
        $this->assertNotNull($variant);
        $this->assertContains($variant->attributeValues->first()->id, $newValueIds);
    }

    #[Test]
    public function it_never_copies_stock_or_movements(): void
    {
        $source = $this->sourceProduct();
        // Stock réel sur la source (10 unités) via l'endpoint dédié.
        $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$source->id}/initial-stock", ['quantity' => 10])
            ->assertCreated();

        $newId = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$source->id}/duplicate")
            ->assertCreated()->json('data.id');

        $this->assertSame(0, \App\Modules\Inventory\Models\Stock::withoutTenantScope()->where('product_id', $newId)->count());
        $this->assertSame(0, \App\Modules\Inventory\Models\StockMovement::withoutTenantScope()->where('product_id', $newId)->count());
    }

    // ── Catégorie ────────────────────────────────────────────────────────────

    #[Test]
    public function it_duplicates_a_category_node_only_with_a_new_slug(): void
    {
        $parent = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Racine', 'slug' => 'racine']);
        $source = Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Électronique', 'slug' => 'electro', 'parent_id' => $parent->id]);
        // Un enfant + un produit rattachés à la source (ne doivent PAS être dupliqués).
        Category::create(['tenant_id' => $this->tenant->id, 'name' => 'Sous', 'slug' => 'sous', 'parent_id' => $source->id]);
        Product::create(['tenant_id' => $this->tenant->id, 'category_id' => $source->id, 'sku' => 'X-1', 'name' => 'P', 'price_amount' => 1, 'price_currency' => 'XOF', 'status' => 'active']);

        $newId = $this->withToken($this->adminToken)
            ->postJson("/api/catalog/categories/{$source->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.name', 'Électronique (copie)')
            ->assertJsonPath('data.parent_id', $parent->id)
            ->json('data.id');

        $new = Category::withoutTenantScope()->findOrFail($newId);
        $this->assertNotSame($source->slug, $new->slug);                                  // slug régénéré
        $this->assertSame(0, Category::withoutTenantScope()->where('parent_id', $newId)->count()); // pas de sous-catégorie
        $this->assertSame(0, Product::withoutTenantScope()->where('category_id', $newId)->count()); // pas de produit
    }

    // ── Garde RBAC + isolation tenant ──────────────────────────────────────────

    #[Test]
    public function a_member_without_write_permission_cannot_duplicate(): void
    {
        $source = $this->sourceProduct();

        $this->withToken($this->memberToken)
            ->postJson("/api/catalog/products/{$source->id}/duplicate")
            ->assertStatus(403);
    }

    #[Test]
    public function it_cannot_duplicate_another_tenants_product(): void
    {
        // Produit appartenant à un AUTRE tenant.
        $otherTenant = Tenant::create(['name' => 'Autre', 'slug' => 'autre-shop', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $foreign = Product::create(['tenant_id' => $otherTenant->id, 'sku' => 'OTH-1', 'name' => 'Étranger', 'price_amount' => 1, 'price_currency' => 'XOF', 'status' => 'active']);

        $this->withToken($this->adminToken)
            ->postJson("/api/catalog/products/{$foreign->id}/duplicate")
            ->assertStatus(404);
    }
}
