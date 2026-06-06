<?php

namespace App\Modules\ImportExport\Tests\Unit;

use App\Modules\Catalog\Models\Category;
use App\Modules\ImportExport\Services\TemplateService;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function loadXlsx(\Symfony\Component\HttpFoundation\StreamedResponse $response): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $tmp = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        file_put_contents($tmp, $content);
        $ss = IOFactory::load($tmp);
        @unlink($tmp);

        return $ss;
    }

    /** @return list<string> every non-empty value on the hidden "Listes" sheet */
    private function listesValues(\PhpOffice\PhpSpreadsheet\Spreadsheet $ss): array
    {
        $sheet = $ss->getSheetByName('Listes');
        if ($sheet === null) {
            return [];
        }
        $values = [];
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $v = $cell->getValue();
                if ($v !== null && $v !== '') {
                    $values[] = $v;
                }
            }
        }

        return $values;
    }

    #[Test]
    public function products_template_embeds_dropdowns_from_the_tenant_categories_suppliers_and_status(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 'tpl-t', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        Category::create(['tenant_id' => $tenant->id, 'name' => 'Vêtements',   'slug' => 'vetements']);
        Category::create(['tenant_id' => $tenant->id, 'name' => 'Accessoires', 'slug' => 'accessoires']);
        Supplier::create(['tenant_id' => $tenant->id, 'code' => 'SUP-1', 'name' => 'Import Douala SARL', 'status' => 'active']);

        $ss = $this->loadXlsx(app(TemplateService::class)->download('products', $tenant->id));

        // Every dropdown source value lands on the hidden "Listes" sheet:
        // the tenant's categories, its suppliers, and the fixed status enum.
        $values = $this->listesValues($ss);
        $this->assertContains('Vêtements', $values,          'category dropdown');
        $this->assertContains('Import Douala SARL', $values, 'supplier dropdown');
        $this->assertContains('active', $values,             'status enum dropdown');

        // Catégorie, Fournisseur and Statut columns each carry a validation.
        $import = $ss->getSheetByName('Import');
        $this->assertGreaterThanOrEqual(3, count($import->getDataValidationCollection()));
    }

    #[Test]
    public function products_template_falls_back_to_static_dropdowns_when_the_tenant_has_no_data(): void
    {
        $tenant = Tenant::create(['name' => 'T2', 'slug' => 'tpl-t2', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);

        $response = app(TemplateService::class)->download('products', $tenant->id);
        $this->assertSame(200, $response->getStatusCode());

        // Only the fixed Statut enum remains — no category/supplier values.
        $values = $this->listesValues($this->loadXlsx($response));
        $this->assertContains('active', $values);
        $this->assertNotContains('Vêtements', $values);
    }

    #[Test]
    public function suppliers_template_offers_the_status_enum_as_a_dropdown(): void
    {
        $tenant = Tenant::create(['name' => 'T3', 'slug' => 'tpl-t3', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);

        $ss = $this->loadXlsx(app(TemplateService::class)->download('suppliers', $tenant->id));

        $values = $this->listesValues($ss);
        $this->assertContains('active', $values);
        $this->assertContains('inactive', $values);
        $this->assertNotEmpty($ss->getSheetByName('Import')->getDataValidationCollection());
    }
}
