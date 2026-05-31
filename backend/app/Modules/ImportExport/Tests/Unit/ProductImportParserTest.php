<?php

namespace App\Modules\ImportExport\Tests\Unit;

use App\Modules\ImportExport\Models\ImportRow;
use App\Modules\ImportExport\Parsers\ProductImportParser;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImportParserTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create([
            'name' => 'Test Shop', 'slug' => 'test-shop', 'plan' => 'starter', 'status' => 'active',
        ]);
        $this->tenantId = $tenant->id;
    }

    private function parser(string $mode = 'create_update'): ProductImportParser
    {
        return new ProductImportParser($this->tenantId, $mode);
    }

    #[Test]
    public function it_validates_a_complete_valid_row(): void
    {
        $parser = $this->parser();
        $result = $parser->parseRow([
            'sku'   => 'P-001',
            'name'  => 'T-Shirt',
            'price' => '15000',
        ], 1);

        $this->assertEquals(ImportRow::STATUS_VALID, $result['status']);
        $this->assertEquals(ImportRow::ACTION_CREATE, $result['action']);
        $this->assertEquals(1500000, $result['mapped_data']['price_amount']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_rejects_row_missing_sku(): void
    {
        $result = $this->parser()->parseRow([
            'name'  => 'T-Shirt',
            'price' => '5000',
        ], 1);

        $this->assertEquals(ImportRow::STATUS_ERROR, $result['status']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('sku', $result['errors'][0]['field']);
    }

    #[Test]
    public function it_rejects_row_missing_price(): void
    {
        $result = $this->parser()->parseRow([
            'sku'  => 'P-001',
            'name' => 'T-Shirt',
        ], 1);

        $this->assertEquals(ImportRow::STATUS_ERROR, $result['status']);
        $fields = array_column($result['errors'], 'field');
        $this->assertContains('price', $fields);
    }

    #[Test]
    public function it_converts_decimal_price_to_cents(): void
    {
        $result = $this->parser()->parseRow([
            'sku'   => 'P-001',
            'name'  => 'Widget',
            'price' => '19.99',
        ], 1);

        $this->assertEquals(1999, $result['mapped_data']['price_amount']);
    }

    #[Test]
    public function it_converts_french_decimal_price(): void
    {
        $result = $this->parser()->parseRow([
            'sku'   => 'P-001',
            'name'  => 'Widget',
            'price' => '1 234,50',
        ], 1);

        $this->assertEquals(123450, $result['mapped_data']['price_amount']);
    }

    #[Test]
    public function it_detects_duplicate_sku_and_sets_update_action(): void
    {
        // Create an existing product
        \App\Modules\Catalog\Models\Product::create([
            'tenant_id'      => $this->tenantId,
            'sku'            => 'P-DUP',
            'name'           => 'Existing',
            'price_amount'   => 5000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $result = $this->parser()->parseRow([
            'sku'   => 'P-DUP',
            'name'  => 'Updated Name',
            'price' => '6000',
        ], 1);

        $this->assertEquals(ImportRow::ACTION_UPDATE, $result['action']);
        $this->assertNotNull($result['entity_id']);
    }

    #[Test]
    public function create_only_mode_skips_existing_sku(): void
    {
        \App\Modules\Catalog\Models\Product::create([
            'tenant_id'      => $this->tenantId,
            'sku'            => 'P-DUP',
            'name'           => 'Existing',
            'price_amount'   => 5000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $result = $this->parser('create_only')->parseRow([
            'sku'   => 'P-DUP',
            'name'  => 'Updated',
            'price' => '6000',
        ], 1);

        $this->assertEquals(ImportRow::ACTION_SKIP, $result['action']);
        $this->assertEquals(ImportRow::STATUS_WARNING, $result['status']);
    }

    #[Test]
    public function update_only_mode_skips_new_sku(): void
    {
        $result = $this->parser('update_only')->parseRow([
            'sku'   => 'NEW-001',
            'name'  => 'New Product',
            'price' => '5000',
        ], 1);

        $this->assertEquals(ImportRow::ACTION_SKIP, $result['action']);
    }

    #[Test]
    public function it_adds_warning_for_unknown_category(): void
    {
        $result = $this->parser()->parseRow([
            'sku'      => 'P-001',
            'name'     => 'T-Shirt',
            'price'    => '5000',
            'category' => 'Catégorie Inconnue',
        ], 1);

        $this->assertEquals(ImportRow::STATUS_WARNING, $result['status']);
        $fields = array_column($result['warnings'], 'field');
        $this->assertContains('category', $fields);
    }
}
