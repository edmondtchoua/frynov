<?php

namespace App\Modules\Catalog\Tests\Unit;

use App\Modules\Catalog\Services\SkuGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the refactored SkuGeneratorService.
 *
 * The old implementation used MAX(sku)+1 with a race condition window.
 * The new implementation uses sku_sequences table with SELECT ... FOR UPDATE.
 */
class SkuSequenceTest extends TestCase
{
    use RefreshDatabase;

    private SkuGeneratorService $svc;
    private string $tenantId = 'tenant-uuid-0001-0001-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SkuGeneratorService();
    }

    #[Test]
    public function generates_sequential_skus_starting_from_1(): void
    {
        $sku1 = $this->svc->generate($this->tenantId, 'PRD');
        $sku2 = $this->svc->generate($this->tenantId, 'PRD');
        $sku3 = $this->svc->generate($this->tenantId, 'PRD');

        $this->assertSame('PRD-0001', $sku1);
        $this->assertSame('PRD-0002', $sku2);
        $this->assertSame('PRD-0003', $sku3);
    }

    #[Test]
    public function different_prefixes_have_independent_sequences(): void
    {
        $sku1 = $this->svc->generate($this->tenantId, 'VET');
        $sku2 = $this->svc->generate($this->tenantId, 'PRD');
        $sku3 = $this->svc->generate($this->tenantId, 'VET');

        $this->assertSame('VET-0001', $sku1);
        $this->assertSame('PRD-0001', $sku2);
        $this->assertSame('VET-0002', $sku3);
    }

    #[Test]
    public function different_tenants_have_independent_sequences(): void
    {
        $tenant2 = 'tenant-uuid-0002-0002-000000000002';

        $sku1 = $this->svc->generate($this->tenantId, 'PRD');
        $sku2 = $this->svc->generate($tenant2,        'PRD');
        $sku3 = $this->svc->generate($this->tenantId, 'PRD');

        $this->assertSame('PRD-0001', $sku1);
        $this->assertSame('PRD-0001', $sku2); // independent
        $this->assertSame('PRD-0002', $sku3);
    }

    #[Test]
    public function sequence_persists_in_sku_sequences_table(): void
    {
        $this->svc->generate($this->tenantId, 'PRD');
        $this->svc->generate($this->tenantId, 'PRD');

        $row = DB::table('sku_sequences')
            ->where('tenant_id', $this->tenantId)
            ->where('prefix', 'PRD')
            ->first();

        $this->assertSame(2, (int) $row->last_seq);
    }

    #[Test]
    public function prefix_is_sanitized_and_uppercased(): void
    {
        $sku = $this->svc->generate($this->tenantId, 'vêt-ement');

        // Non-alphanumeric removed, uppercased, max 5 chars
        $this->assertStringStartsWith('VTEME', $sku);
    }

    #[Test]
    public function generates_variant_sku_from_parent(): void
    {
        $this->assertSame('PRD-0001-V1', $this->svc->generateVariant('PRD-0001', 1));
        $this->assertSame('PRD-0001-V2', $this->svc->generateVariant('PRD-0001', 2));
    }

    #[Test]
    public function next_sequence_is_called_inside_transaction(): void
    {
        // nextSequence() requires an active transaction to use lockForUpdate()
        // Calling outside transaction should still work (auto-transaction on lockForUpdate)
        $seq = DB::transaction(fn () => $this->svc->nextSequence($this->tenantId, 'TEST'));
        $this->assertSame(1, $seq);
    }
}
