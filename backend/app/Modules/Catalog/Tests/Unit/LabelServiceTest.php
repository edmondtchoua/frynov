<?php

namespace App\Modules\Catalog\Tests\Unit;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\LabelService;
use App\Modules\Catalog\Services\ProductCodeService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LabelServiceTest extends TestCase
{
    private LabelService $labelService;

    /** @var MockInterface&ProductCodeService */
    private MockInterface $codeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codeService  = Mockery::mock(ProductCodeService::class);
        $this->labelService = new LabelService($this->codeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeProduct(string $sku = 'PRD-0001', int $price = 25000): Product
    {
        $product                  = new Product();
        $product->id              = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $product->sku             = $sku;
        $product->name            = 'Boubou Sénégalais';
        $product->price_amount    = $price;
        $product->price_currency  = 'XOF';
        $product->has_variants    = false;
        $product->compare_at_price_amount = null;

        return $product;
    }

    #[Test]
    public function it_generates_thermal_html_for_single_product(): void
    {
        $product = $this->makeProduct();

        $this->codeService->allows('qrCode')->andReturn('<svg id="qr"></svg>');
        $this->codeService->allows('barcode')->andReturn('<svg id="bar"></svg>');

        $html = $this->labelService->generateForProduct($product, copies: 1, format: 'thermal');

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('58mm', $html);
        $this->assertStringContainsString('Boubou Sénégalais', $html);
        $this->assertStringContainsString('PRD-0001', $html);
        $this->assertStringContainsString('250.00 XOF', $html);
    }

    #[Test]
    public function it_repeats_label_for_each_copy(): void
    {
        $product = $this->makeProduct();

        $this->codeService->allows('qrCode')->andReturn('<svg></svg>');
        $this->codeService->allows('barcode')->andReturn('<svg></svg>');

        $html = $this->labelService->generateForProduct($product, copies: 3, format: 'thermal');

        // 3 copies → 3 label wrapper divs with data-sku attribute
        $this->assertEquals(3, substr_count($html, 'data-sku="PRD-0001"'));
    }

    #[Test]
    public function it_generates_a4_format(): void
    {
        $product = $this->makeProduct();

        $this->codeService->allows('qrCode')->andReturn('<svg></svg>');
        $this->codeService->allows('barcode')->andReturn('<svg></svg>');

        $html = $this->labelService->generateForProduct($product, copies: 1, format: 'a4sheet');

        $this->assertStringContainsString('A4', $html);
        $this->assertStringContainsString('24/page', $html);
    }

    #[Test]
    public function it_batches_multiple_products(): void
    {
        $p1 = $this->makeProduct('PRD-0001', 10000);
        $p2 = $this->makeProduct('PRD-0002', 20000);

        $this->codeService->allows('qrCode')->andReturn('<svg></svg>');
        $this->codeService->allows('barcode')->andReturn('<svg></svg>');

        $html = $this->labelService->generate([
            ['product' => $p1, 'copies' => 2],
            ['product' => $p2, 'copies' => 3],
        ], 'thermal');

        // 5 labels total: 2 of PRD-0001, 3 of PRD-0002
        $this->assertEquals(2, substr_count($html, 'data-sku="PRD-0001"'));
        $this->assertEquals(3, substr_count($html, 'data-sku="PRD-0002"'));
    }

    #[Test]
    public function it_shows_variant_attributes(): void
    {
        $product = $this->makeProduct();

        $variant                 = new ProductVariant();
        $variant->sku            = 'PRD-0001-V1';
        $variant->name           = 'Rouge / L';
        $variant->attributes     = ['Couleur' => 'Rouge', 'Taille' => 'L'];
        $variant->price_amount   = null;
        $variant->price_currency = null;
        $variant->setRelation('product', $product);

        $this->codeService->allows('qrCode')->andReturn('<svg></svg>');
        $this->codeService->allows('barcode')->andReturn('<svg></svg>');

        $html = $this->labelService->generate(
            [['product' => $product, 'variant' => $variant, 'copies' => 1]],
            'thermal',
        );

        $this->assertStringContainsString('Couleur: Rouge', $html);
        $this->assertStringContainsString('Taille: L', $html);
        $this->assertStringContainsString('PRD-0001-V1', $html);
    }

    #[Test]
    public function it_hides_price_when_option_disabled(): void
    {
        $product = $this->makeProduct();

        $this->codeService->allows('qrCode')->andReturn('<svg></svg>');
        $this->codeService->allows('barcode')->andReturn('<svg></svg>');

        $html = $this->labelService->generateForProduct(
            $product,
            copies: 1,
            format: 'thermal',
            options: ['show_price' => false],
        );

        $this->assertStringNotContainsString('250.00 XOF', $html);
    }
}
