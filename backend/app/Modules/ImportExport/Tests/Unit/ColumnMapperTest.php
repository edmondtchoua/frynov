<?php

namespace App\Modules\ImportExport\Tests\Unit;

use App\Modules\ImportExport\Parsers\ColumnMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColumnMapperTest extends TestCase
{
    #[Test]
    public function it_maps_french_product_headers(): void
    {
        $headers = ['SKU', 'Nom Produit', 'Prix', 'Catégorie', 'Fournisseur'];
        $mapping = ColumnMapper::autoMap($headers, 'products');

        $this->assertEquals('sku',      $mapping['SKU']);
        $this->assertEquals('name',     $mapping['Nom Produit']);
        $this->assertEquals('price',    $mapping['Prix']);
        $this->assertEquals('category', $mapping['Catégorie']);
        $this->assertEquals('supplier', $mapping['Fournisseur']);
    }

    #[Test]
    public function it_maps_english_product_headers(): void
    {
        $headers = ['product_name', 'selling_price', 'barcode'];
        $mapping = ColumnMapper::autoMap($headers, 'products');

        $this->assertEquals('name',    $mapping['product_name']);
        $this->assertEquals('price',   $mapping['selling_price']);
        $this->assertEquals('barcode', $mapping['barcode']);
    }

    #[Test]
    public function it_returns_null_for_unknown_headers(): void
    {
        $headers = ['foo_bar', 'xyz_unknown'];
        $mapping = ColumnMapper::autoMap($headers, 'products');

        $this->assertNull($mapping['foo_bar']);
        $this->assertNull($mapping['xyz_unknown']);
    }

    #[Test]
    public function it_maps_customer_headers(): void
    {
        $headers = ['Nom', 'Email', 'Téléphone', 'Adresse'];
        $mapping = ColumnMapper::autoMap($headers, 'customers');

        $this->assertEquals('name',    $mapping['Nom']);
        $this->assertEquals('email',   $mapping['Email']);
        $this->assertEquals('phone',   $mapping['Téléphone']);
        $this->assertEquals('address', $mapping['Adresse']);
    }

    #[Test]
    public function it_applies_mapping_to_a_raw_row(): void
    {
        $rawRow = ['SKU' => 'P001', 'Nom Produit' => 'T-Shirt', 'ignored_col' => 'x'];
        $mapping = ['SKU' => 'sku', 'Nom Produit' => 'name', 'ignored_col' => null];

        $result = ColumnMapper::applyMapping($rawRow, $mapping);

        $this->assertEquals(['sku' => 'P001', 'name' => 'T-Shirt'], $result);
    }

    #[Test]
    public function normalizer_strips_accents_and_spaces(): void
    {
        $this->assertEquals('categorie',  ColumnMapper::normalize('Catégorie'));
        $this->assertEquals('codebarre',  ColumnMapper::normalize('Code Barre'));
        $this->assertEquals('nomdufournisseur', ColumnMapper::normalize('Nom du Fournisseur'));
    }
}
