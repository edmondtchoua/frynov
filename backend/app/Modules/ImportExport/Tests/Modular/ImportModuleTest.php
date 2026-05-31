<?php

namespace App\Modules\ImportExport\Tests\Modular;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\ImportExport\Models\ImportRow;
use App\Modules\ImportExport\Models\ImportSession;
use App\Modules\ImportExport\Services\ImportService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportModuleTest extends TestCase
{
    use RefreshDatabase;

    private ImportService $service;
    private Tenant $tenant;
    private User   $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->service = $this->app->make(ImportService::class);

        $this->tenant = Tenant::create([
            'name' => 'Nexora Demo', 'slug' => 'nexora-demo', 'plan' => 'pro', 'status' => 'active',
        ]);
        $this->user = User::create([
            'name'      => 'Manager',
            'email'     => 'mgr@nexora.com',
            'password'  => Hash::make('pass'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function makeXlsxUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIdx => $row) {
            foreach ($row as $colIdx => $value) {
                $sheet->setCellValue([$colIdx + 1, $rowIdx + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    #[Test]
    public function full_product_import_creates_products(): void
    {
        $file = $this->makeXlsxUpload([
            ['SKU', 'Nom Produit', 'Prix'],
            ['PROD-A', 'T-Shirt Coton', '15000'],
            ['PROD-B', 'Pantalon Jean', '25000'],
        ]);

        $session = $this->service->upload($file, 'products', 'create_update', $this->tenant->id, $this->user->id);
        $session = $this->service->analyze($session);

        // Move to awaiting_approval if analyzed without errors
        if ($session->status === ImportSession::STATUS_ANALYZED) {
            $session->update(['status' => ImportSession::STATUS_AWAITING_APPROVAL]);
            $session->refresh();
        }

        $this->assertContains($session->status, [
            ImportSession::STATUS_AWAITING_APPROVAL,
            ImportSession::STATUS_ANALYZED,
        ]);
        $this->assertEquals(2, $session->total_rows);
        $this->assertEquals(0, $session->error_rows);

        $session->update(['status' => ImportSession::STATUS_AWAITING_APPROVAL]);
        $session = $this->service->execute($session->fresh());

        $this->assertContains($session->status, [
            ImportSession::STATUS_COMPLETED,
            ImportSession::STATUS_PARTIAL,
        ]);
        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenant->id,
            'sku'       => 'PROD-A',
        ]);
        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenant->id,
            'sku'       => 'PROD-B',
        ]);
    }

    #[Test]
    public function simulate_mode_does_not_create_entities(): void
    {
        $file = $this->makeXlsxUpload([
            ['SKU', 'Nom Produit', 'Prix'],
            ['SIM-001', 'Simulated Product', '10000'],
        ]);

        $session = $this->service->upload($file, 'products', 'simulate', $this->tenant->id, $this->user->id);
        $session = $this->service->analyze($session);
        $session->update(['status' => ImportSession::STATUS_AWAITING_APPROVAL]);
        $session = $this->service->execute($session->fresh());

        $this->assertContains($session->status, [ImportSession::STATUS_COMPLETED, ImportSession::STATUS_PARTIAL]);
        $this->assertDatabaseMissing('products', ['sku' => 'SIM-001']);
    }

    #[Test]
    public function row_with_invalid_price_gets_error_status(): void
    {
        $file = $this->makeXlsxUpload([
            ['SKU', 'Nom Produit', 'Prix'],
            ['ERR-001', 'Bad Price Product', 'not_a_price'],
        ]);

        $session = $this->service->upload($file, 'products', 'create_update', $this->tenant->id, $this->user->id);
        $session = $this->service->analyze($session);

        $this->assertEquals(ImportSession::STATUS_ANALYZED, $session->status);
        $this->assertEquals(1, $session->error_rows);

        $errorRow = ImportRow::where('session_id', $session->id)
            ->where('status', ImportRow::STATUS_ERROR)
            ->first();

        $this->assertNotNull($errorRow);
    }

    #[Test]
    public function duplicate_product_is_updated_not_created(): void
    {
        // Pre-create product
        Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'DUP-001',
            'name'           => 'Original Name',
            'price_amount'   => 5000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $file = $this->makeXlsxUpload([
            ['SKU', 'Nom Produit', 'Prix'],
            ['DUP-001', 'Updated Name', '6000'],
        ]);

        $session = $this->service->upload($file, 'products', 'create_update', $this->tenant->id, $this->user->id);
        $session = $this->service->analyze($session);
        $session->update(['status' => ImportSession::STATUS_AWAITING_APPROVAL]);
        $this->service->execute($session->fresh());

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenant->id,
            'sku'       => 'DUP-001',
            'name'      => 'Updated Name',
        ]);

        // Only one product with this SKU
        $this->assertEquals(1, Product::where('tenant_id', $this->tenant->id)->where('sku', 'DUP-001')->count());
    }

    #[Test]
    public function customer_import_detects_duplicate_email(): void
    {
        Customer::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Existing Customer',
            'email'     => 'dup@test.com',
        ]);

        $file = $this->makeXlsxUpload([
            ['Nom', 'Email', 'Téléphone'],
            ['New Name', 'dup@test.com', '+225 07 00 00 01'],
        ]);

        $session = $this->service->upload($file, 'customers', 'create_only', $this->tenant->id, $this->user->id);
        $session = $this->service->analyze($session);

        $row = ImportRow::where('session_id', $session->id)->first();
        $this->assertEquals(ImportRow::ACTION_SKIP, $row->action);
    }

    #[Test]
    public function cancel_deletes_file_and_marks_session(): void
    {
        $file = $this->makeXlsxUpload([
            ['SKU', 'Nom Produit', 'Prix'],
            ['CAN-001', 'Cancel Me', '5000'],
        ]);

        $session = $this->service->upload($file, 'products', 'create_update', $this->tenant->id, $this->user->id);

        // Reset to draft so cancel works
        $session->update(['status' => ImportSession::STATUS_DRAFT]);
        $session = $this->service->cancel($session->fresh());

        $this->assertEquals(ImportSession::STATUS_CANCELLED, $session->status);
    }

    #[Test]
    public function cannot_cancel_a_completed_session(): void
    {
        $session = ImportSession::create([
            'tenant_id'         => $this->tenant->id,
            'performed_by'      => $this->user->id,
            'type'              => 'products',
            'status'            => ImportSession::STATUS_COMPLETED,
            'mode'              => 'create_update',
            'original_filename' => 'x.xlsx',
            'stored_path'       => 'imports/x.xlsx',
        ]);

        $this->expectException(\DomainException::class);
        $this->service->cancel($session);
    }

    #[Test]
    public function history_returns_only_tenant_sessions(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);

        ImportSession::create([
            'tenant_id' => $this->tenant->id, 'performed_by' => $this->user->id,
            'type' => 'products', 'status' => 'completed', 'mode' => 'create_update',
            'original_filename' => 'a.xlsx', 'stored_path' => 'x',
        ]);
        ImportSession::create([
            'tenant_id' => $otherTenant->id, 'performed_by' => $this->user->id,
            'type' => 'products', 'status' => 'completed', 'mode' => 'create_update',
            'original_filename' => 'b.xlsx', 'stored_path' => 'y',
        ]);

        $result = $this->service->history($this->tenant->id);
        $this->assertEquals(1, $result->total());
    }
}
