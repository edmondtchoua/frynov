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

    // ── RC-20 (P-4) — doublons de SKU INTRA-FICHIER détectés dès l'analyse ────────────────────

    #[Test]
    public function a_duplicate_sku_within_the_same_file_is_skipped_at_analysis(): void
    {
        $parser = $this->parser('create_only');

        $row1 = $parser->parseRow(['sku' => 'DUP-1', 'name' => 'Premier', 'price' => '1000'], 1);
        $row2 = $parser->parseRow(['sku' => 'DUP-1', 'name' => 'Second', 'price' => '2000'], 2);
        $row3 = $parser->parseRow(['sku' => 'AUTRE', 'name' => 'Autre', 'price' => '500'], 3);

        // 1re occurrence : valide, à créer. Avant correctif, la 2e l'était AUSSI (échec à l'exécution).
        $this->assertEquals(ImportRow::STATUS_VALID, $row1['status']);
        $this->assertEquals(ImportRow::ACTION_CREATE, $row1['action']);

        // 2e occurrence : ignorée dès l'analyse, avertissement pointant la ligne d'origine.
        $this->assertEquals(ImportRow::ACTION_SKIP, $row2['action']);
        $this->assertEquals(ImportRow::STATUS_WARNING, $row2['status']);
        $this->assertStringContainsString('ligne 1', $row2['warnings'][0]['message']);

        // Un SKU différent reste valide.
        $this->assertEquals(ImportRow::STATUS_VALID, $row3['status']);
    }

    #[Test]
    public function an_error_row_does_not_reserve_its_sku_for_the_file(): void
    {
        $parser = $this->parser('create_only');

        // Ligne 1 en ERREUR (prix manquant) : elle n'écrira rien → ne réserve pas le SKU.
        $row1 = $parser->parseRow(['sku' => 'ERR-1', 'name' => 'Sans prix'], 1);
        $row2 = $parser->parseRow(['sku' => 'ERR-1', 'name' => 'Avec prix', 'price' => '1000'], 2);

        $this->assertEquals(ImportRow::STATUS_ERROR, $row1['status']);
        $this->assertEquals(ImportRow::STATUS_VALID, $row2['status']);   // la ligne corrigée passe
        $this->assertEquals(ImportRow::ACTION_CREATE, $row2['action']);
    }
}
