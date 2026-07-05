<?php

namespace App\Modules\ImportExport\Tests\Integration;

use App\Models\User;
use App\Modules\ImportExport\Models\ImportSession;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = Tenant::create([
            'name' => 'Test Shop', 'slug' => 'test-shop', 'plan' => 'starter', 'status' => 'active',
        ]);
        $this->user = User::create([
            'name'      => 'Admin',
            'email'     => 'admin@test.com',
            'password'  => Hash::make('pass'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function makeXlsxFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIdx => $row) {
            foreach ($row as $colIdx => $value) {
                $sheet->setCellValue([$colIdx + 1, $rowIdx + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'test_import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson('/api/import/upload')->assertStatus(401);
    }

    #[Test]
    public function it_uploads_and_analyzes_a_product_file(): void
    {
        $file = $this->makeXlsxFile([
            ['SKU', 'Nom Produit', 'Prix'],
            ['P-001', 'T-Shirt', '15000'],
            ['P-002', 'Pantalon', '25000'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/import/upload', [
                'file' => $file,
                'type' => 'products',
                'mode' => 'create_update',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data') ?? $response->json();

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('products', $data['type']);
        $this->assertContains($data['status'], [
            ImportSession::STATUS_ANALYZED,
            ImportSession::STATUS_AWAITING_APPROVAL,
            ImportSession::STATUS_ANALYZING,
        ]);
    }

    #[Test]
    public function it_analyzes_rows_with_more_cells_than_headers(): void
    {
        $file = $this->makeXlsxFile([
            ['SKU', 'Nom Produit', 'Prix'],
            ['P-001', 'T-Shirt', '15000', 'cellule sans en-tête'],
            ['P-002', 'Pantalon', '25000'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/import/upload', [
                'file' => $file,
                'type' => 'products',
                'mode' => 'create_update',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data') ?? $response->json();

        $this->assertNotSame(ImportSession::STATUS_FAILED, $data['status']);
        $this->assertNull($data['error_message']);
        $this->assertEquals(2, $data['total_rows']);
        $this->assertDatabaseCount('import_rows', 2);
    }

    #[Test]
    public function it_rejects_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $this->actingAs($this->user)
            ->postJson('/api/import/upload', [
                'file' => $file,
                'type' => 'products',
                'mode' => 'create_update',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function it_shows_session_status_with_rows(): void
    {
        $file = $this->makeXlsxFile([
            ['SKU', 'Nom Produit', 'Prix'],
            ['P-001', 'T-Shirt', '15000'],
        ]);

        $uploadResp = $this->actingAs($this->user)
            ->postJson('/api/import/upload', [
                'file' => $file,
                'type' => 'products',
                'mode' => 'create_update',
            ]);

        $sessionId = $uploadResp->json('id') ?? $uploadResp->json('data.id');

        $this->actingAs($this->user)
            ->getJson("/api/import/{$sessionId}")
            ->assertStatus(200)
            ->assertJsonStructure(['id', 'type', 'status', 'total_rows']);
    }

    #[Test]
    public function it_can_cancel_a_session(): void
    {
        $file = $this->makeXlsxFile([
            ['SKU', 'Nom Produit', 'Prix'],
            ['P-001', 'T-Shirt', '15000'],
        ]);

        $uploadResp = $this->actingAs($this->user)
            ->postJson('/api/import/upload', [
                'file' => $file,
                'type' => 'products',
                'mode' => 'create_update',
            ]);

        $sessionId = $uploadResp->json('id') ?? $uploadResp->json('data.id');

        $this->actingAs($this->user)
            ->deleteJson("/api/import/{$sessionId}")
            ->assertStatus(204);

        $this->assertDatabaseHas('import_sessions', [
            'id'     => $sessionId,
            'status' => ImportSession::STATUS_CANCELLED,
        ]);
    }

    #[Test]
    public function history_returns_paginated_sessions(): void
    {
        ImportSession::create([
            'tenant_id'         => $this->tenant->id,
            'performed_by'      => $this->user->id,
            'type'              => 'products',
            'status'            => ImportSession::STATUS_COMPLETED,
            'mode'              => 'create_update',
            'original_filename' => 'products.xlsx',
            'stored_path'       => 'imports/test.xlsx',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/import/history')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
    }

    #[Test]
    public function it_enforces_tenant_isolation_on_show(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);
        $session = ImportSession::create([
            'tenant_id'         => $otherTenant->id,
            'performed_by'      => $this->user->id,
            'type'              => 'products',
            'status'            => ImportSession::STATUS_DRAFT,
            'mode'              => 'create_update',
            'original_filename' => 'x.xlsx',
            'stored_path'       => 'imports/x.xlsx',
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/import/{$session->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function it_downloads_a_product_template(): void
    {
        $this->actingAs($this->user)
            ->get('/api/import/template/products')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function it_exports_products_as_xlsx(): void
    {
        \App\Modules\Catalog\Models\Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'E-001',
            'name'           => 'Export Test',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $this->actingAs($this->user)
            ->get('/api/export/products?format=xlsx')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
