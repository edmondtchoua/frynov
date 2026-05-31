<?php

namespace App\Modules\Suppliers\Tests\Unit;

use App\Models\User;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Services\SupplierService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierServiceTest extends TestCase
{
    use RefreshDatabase;

    private SupplierService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(SupplierService::class);
        $this->tenant  = Tenant::create([
            'name' => 'Test', 'slug' => 'test', 'plan' => 'starter', 'status' => 'active',
        ]);
    }

    #[Test]
    public function it_creates_a_supplier_with_auto_code(): void
    {
        $supplier = $this->service->create([
            'name'  => 'TextilePro',
            'email' => 'contact@textilepro.com',
        ], $this->tenant->id);

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertEquals('TextilePro', $supplier->name);
        $this->assertStringStartsWith('SUP-', $supplier->code);
        $this->assertEquals('active', $supplier->status);
    }

    #[Test]
    public function it_increments_supplier_code(): void
    {
        $s1 = $this->service->create(['name' => 'Supplier A'], $this->tenant->id);
        $s2 = $this->service->create(['name' => 'Supplier B'], $this->tenant->id);

        $this->assertNotEquals($s1->code, $s2->code);
    }

    #[Test]
    public function it_updates_a_supplier(): void
    {
        $supplier = $this->service->create(['name' => 'Old Name'], $this->tenant->id);
        $updated  = $this->service->update($supplier, ['name' => 'New Name', 'phone' => '+225 07 00 00 00']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('+225 07 00 00 00', $updated->phone);
    }

    #[Test]
    public function it_soft_deletes_a_supplier(): void
    {
        $supplier = $this->service->create(['name' => 'To Delete'], $this->tenant->id);
        $this->service->delete($supplier);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    #[Test]
    public function findOrFail_throws_for_wrong_tenant(): void
    {
        $supplier = $this->service->create(['name' => 'S1'], $this->tenant->id);
        $other    = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->findOrFail($supplier->id, $other->id);
    }

    #[Test]
    public function findOrCreateByName_creates_when_absent(): void
    {
        $supplier = $this->service->findOrCreateByName('Brand New Supplier', $this->tenant->id);

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertEquals('Brand New Supplier', $supplier->name);
        $this->assertDatabaseHas('suppliers', ['name' => 'Brand New Supplier', 'tenant_id' => $this->tenant->id]);
    }

    #[Test]
    public function findOrCreateByName_returns_existing(): void
    {
        $s1 = $this->service->create(['name' => 'Existing Supplier'], $this->tenant->id);
        $s2 = $this->service->findOrCreateByName('Existing Supplier', $this->tenant->id);

        $this->assertEquals($s1->id, $s2->id);
    }
}
