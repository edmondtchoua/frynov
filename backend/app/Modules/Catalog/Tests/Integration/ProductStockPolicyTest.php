<?php

namespace App\Modules\Catalog\Tests\Integration;

use App\Modules\Catalog\Models\Product;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-5A (socle produits spéciaux) : politique serveur stock_tracking / fulfillment_type dérivée du
 * type, et isStockable() faisant autorité (un service/digital n'est jamais stockable, même avec une
 * donnée héritée incohérente).
 */
class ProductStockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create([
            'name' => 'Policy Tenant', 'slug' => 'policy-tenant', 'plan' => 'starter', 'status' => 'active',
        ]);
    }

    private function make(array $attrs): Product
    {
        return Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'sku' => 'P-' . substr(md5(json_encode($attrs) . microtime()), 0, 8),
            'name' => 'Produit', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active',
        ], $attrs));
    }

    #[Test]
    public function a_simple_product_defaults_to_aggregate_delivery_and_is_stockable(): void
    {
        $p = $this->make(['product_type' => Product::TYPE_SIMPLE]);

        $this->assertSame(Product::STOCK_TRACKING_AGGREGATE, $p->stock_tracking);
        $this->assertSame(Product::FULFILLMENT_DELIVERY, $p->fulfillment_type);
        $this->assertTrue($p->isStockable());
        $this->assertFalse($p->isSerialized());
    }

    #[Test]
    public function a_service_is_non_stockable_with_manual_fulfillment(): void
    {
        $p = $this->make(['product_type' => Product::TYPE_SERVICE]);

        $this->assertSame(Product::STOCK_TRACKING_NONE, $p->stock_tracking);
        $this->assertSame(Product::FULFILLMENT_MANUAL, $p->fulfillment_type);
        $this->assertFalse($p->isStockable());
    }

    #[Test]
    public function a_digital_product_is_non_stockable_with_download_fulfillment(): void
    {
        $p = $this->make(['product_type' => Product::TYPE_DIGITAL]);

        $this->assertTrue($p->isDigital());
        $this->assertSame(Product::STOCK_TRACKING_NONE, $p->stock_tracking);
        $this->assertSame(Product::FULFILLMENT_DOWNLOAD, $p->fulfillment_type);
        $this->assertFalse($p->isStockable());
    }

    #[Test]
    public function an_explicit_serialized_policy_is_honored(): void
    {
        $p = $this->make([
            'product_type'   => Product::TYPE_SIMPLE,
            'stock_tracking' => Product::STOCK_TRACKING_SERIALIZED,
        ]);

        $this->assertSame(Product::STOCK_TRACKING_SERIALIZED, $p->stock_tracking);
        $this->assertTrue($p->isSerialized());
        $this->assertTrue($p->isStockable(), 'le sérialisé suit bien du stock');
    }

    #[Test]
    public function product_type_is_authoritative_over_legacy_stock_tracking(): void
    {
        // Simule une donnée héritée incohérente : un service qui porterait encore 'aggregate'
        // (avant le backfill). isStockable() doit rester false grâce à l'autorité du type.
        $p = $this->make(['product_type' => Product::TYPE_SERVICE]);
        DB::table('products')->where('id', $p->id)->update(['stock_tracking' => 'aggregate']);

        $this->assertFalse($p->fresh()->isStockable());
    }

    #[Test]
    public function explicit_none_on_a_simple_product_makes_it_non_stockable(): void
    {
        $p = $this->make([
            'product_type'   => Product::TYPE_SIMPLE,
            'stock_tracking' => Product::STOCK_TRACKING_NONE,
        ]);

        $this->assertFalse($p->isStockable());
    }
}
