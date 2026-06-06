<?php

namespace App\Modules\ImportExport\Tests\Unit;

use App\Modules\Catalog\Models\Category;
use App\Modules\ImportExport\Services\TemplateService;
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

    #[Test]
    public function products_template_embeds_a_category_dropdown_from_the_tenant_categories(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 'tpl-t', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        Category::create(['tenant_id' => $tenant->id, 'name' => 'Vêtements',   'slug' => 'vetements']);
        Category::create(['tenant_id' => $tenant->id, 'name' => 'Accessoires', 'slug' => 'accessoires']);

        $ss = $this->loadXlsx(app(TemplateService::class)->download('products', $tenant->id));

        // Hidden "Listes" sheet holds the tenant's categories (the dropdown source).
        $list = $ss->getSheetByName('Listes');
        $this->assertNotNull($list, 'A hidden Listes sheet must carry the dropdown values.');
        $this->assertEqualsCanonicalizing(
            ['Accessoires', 'Vêtements'],
            [$list->getCell('A1')->getValue(), $list->getCell('A2')->getValue()],
        );

        // The Import sheet's Catégorie column carries a data-validation dropdown.
        $import = $ss->getSheetByName('Import');
        $this->assertNotEmpty($import->getDataValidationCollection());
    }

    #[Test]
    public function products_template_still_generates_when_the_tenant_has_no_categories(): void
    {
        $tenant = Tenant::create(['name' => 'T2', 'slug' => 'tpl-t2', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);

        $response = app(TemplateService::class)->download('products', $tenant->id);

        $this->assertSame(200, $response->getStatusCode());
        // No hidden list sheet when there's nothing to offer.
        $ss = $this->loadXlsx($response);
        $this->assertNull($ss->getSheetByName('Listes'));
    }
}
