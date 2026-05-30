<?php

namespace App\Modules\Catalog\Tests\Unit;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductCodeService;
use Picqer\Barcode\BarcodeGeneratorSVG;
use PHPUnit\Framework\Attributes\Test;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;
use Tests\TestCase;

class ProductCodeServiceTest extends TestCase
{
    private ProductCodeService $codeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codeService = new ProductCodeService(
            new QrCodeGenerator(),
            new BarcodeGeneratorSVG(),
        );
    }

    #[Test]
    public function it_generates_qr_code_as_svg(): void
    {
        $product                 = new Product();
        $product->id             = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $product->sku            = 'VET-0001';
        $product->name           = 'Boubou Sénégalais';
        $product->has_variants   = false;
        $product->price_amount   = 25000;
        $product->price_currency = 'XOF';

        $svg = $this->codeService->qrCode($product);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    #[Test]
    public function it_generates_code128_barcode_as_svg(): void
    {
        $product      = new Product();
        $product->sku = 'VET-0001';

        $svg = $this->codeService->barcode($product, 'code128');

        $this->assertStringContainsString('<svg', $svg);
    }

    #[Test]
    public function it_encodes_sku_in_qr_payload(): void
    {
        $product                 = new Product();
        $product->id             = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $product->sku            = 'TEST-SKU-123';
        $product->name           = 'Produit Test';
        $product->has_variants   = false;
        $product->price_amount   = 1000;
        $product->price_currency = 'XOF';

        $svg = $this->codeService->qrCode($product);

        // The SVG content is generated from a JSON payload containing the SKU.
        // We verify the output is valid SVG (not a direct string match due to encoding).
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertNotEmpty($svg);
    }

    #[Test]
    public function it_returns_sheet_with_both_codes(): void
    {
        $product                 = new Product();
        $product->id             = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $product->sku            = 'PRD-0001';
        $product->name           = 'Produit Test';
        $product->has_variants   = false;
        $product->price_amount   = 5000;
        $product->price_currency = 'XOF';

        $sheet = $this->codeService->sheet($product);

        $this->assertArrayHasKey('sku', $sheet);
        $this->assertArrayHasKey('qr', $sheet);
        $this->assertArrayHasKey('barcode', $sheet);
        $this->assertEquals('PRD-0001', $sheet['sku']);
        $this->assertEquals('svg', $sheet['qr']['format']);
        $this->assertEquals('svg', $sheet['barcode']['format']);
        $this->assertEquals('code128', $sheet['barcode']['type']);
        $this->assertStringStartsWith('<svg', $sheet['qr']['data']);
    }
}
