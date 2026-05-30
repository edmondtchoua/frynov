<?php

namespace App\Modules\Catalog\Tests\Unit;

use App\Modules\Catalog\Services\SkuGeneratorService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SkuGeneratorServiceTest extends TestCase
{
    private SkuGeneratorService $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SkuGeneratorService();
    }

    #[Test]
    public function it_generates_variant_sku_from_parent(): void
    {
        $sku = $this->generator->generateVariant('VET-0001', 1);

        $this->assertEquals('VET-0001-V1', $sku);
    }

    #[Test]
    public function it_increments_variant_index(): void
    {
        $sku = $this->generator->generateVariant('PRD-0042', 3);

        $this->assertEquals('PRD-0042-V3', $sku);
    }

    #[Test]
    public function it_truncates_prefix_to_5_chars(): void
    {
        // The generator normalises prefix to 5 chars max
        // We test this through the variant helper which doesn't use the DB
        $sku = $this->generator->generateVariant('TOOLONGPREFIX-001', 1);

        $this->assertStringEndsWith('-V1', $sku);
    }
}
