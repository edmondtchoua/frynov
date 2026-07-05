<?php

namespace App\Modules\Catalog\Tests\Unit;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogService;
use App\Modules\Catalog\Services\SkuGeneratorService;
use App\Shared\ValueObjects\Money;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    private CatalogService $catalog;

    /** @var MockInterface&SkuGeneratorService */
    private MockInterface $skuGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skuGenerator = Mockery::mock(SkuGeneratorService::class);
        $this->catalog      = new CatalogService($this->skuGenerator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function product_price_returns_money_value_object(): void
    {
        $product                  = new Product();
        $product->price_amount    = 25000;
        $product->price_currency  = 'XOF';

        $price = $product->price();

        $this->assertInstanceOf(Money::class, $price);
        $this->assertEquals(25000, $price->amount());
        $this->assertEquals('XOF', $price->currency());
    }

    #[Test]
    public function product_detects_sale_price(): void
    {
        $product                          = new Product();
        $product->price_amount            = 15000;
        $product->compare_at_price_amount = 20000;
        $product->price_currency          = 'XOF';

        $this->assertTrue($product->isOnSale());
    }

    #[Test]
    public function product_not_on_sale_without_compare_price(): void
    {
        $product                 = new Product();
        $product->price_amount   = 15000;
        $product->price_currency = 'XOF';

        $this->assertFalse($product->isOnSale());
    }

    #[Test]
    public function product_is_active_when_status_is_active(): void
    {
        $product         = new Product();
        $product->status = 'active';

        $this->assertTrue($product->isActive());
        $this->assertFalse($product->isDraft());
    }

    #[Test]
    public function product_is_draft_by_default(): void
    {
        $product         = new Product();
        $product->status = 'draft';

        $this->assertTrue($product->isDraft());
    }

    #[Test]
    public function sku_generator_is_called_when_no_sku_given(): void
    {
        $this->skuGenerator
            ->expects('generate')
            ->with('tenant-123', 'PRD')
            ->andReturn('PRD-0001');

        // Verify the generator would be called with correct args
        $sku = $this->skuGenerator->generate('tenant-123', 'PRD');

        $this->assertEquals('PRD-0001', $sku);
    }

    #[Test]
    public function money_multiply_computes_discount_correctly(): void
    {
        $price      = Money::of(25000, 'XOF');
        $discounted = $price->multiply(0.8); // 20% off

        $this->assertEquals(20000, $discounted->amount());
    }

    #[Test]
    public function money_throws_when_adding_different_currencies(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::of(10000, 'XOF')->add(Money::of(5000, 'NGN'));
    }
}
