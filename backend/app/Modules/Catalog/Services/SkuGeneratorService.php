<?php

namespace App\Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generates unique, sequential SKUs per (tenant, prefix) using a dedicated
 * sku_sequences table with SELECT ... FOR UPDATE to prevent race conditions.
 *
 * This replaces the old MAX(sku)+1 approach which had a race condition window
 * between the two SELECT queries in nextSequence().
 */
class SkuGeneratorService
{
    /**
     * Generate a unique SKU for a tenant.
     * Format: {PREFIX}-{NNNN} — e.g. PRD-0001, VET-0042
     */
    public function generate(string $tenantId, string $prefix = 'PRD'): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix), 0, 5));
        $next   = $this->nextSequence($tenantId, $prefix);

        return sprintf('%s-%04d', $prefix, $next);
    }

    /**
     * Generate a variant SKU from the parent product SKU.
     * Format: {PARENT_SKU}-V{N} — e.g. VET-0001-V1
     */
    public function generateVariant(string $parentSku, int $variantIndex): string
    {
        return "$parentSku-V$variantIndex";
    }

    /**
     * Atomically increment and return the next sequence number.
     *
     * Uses INSERT OR IGNORE + SELECT FOR UPDATE + UPDATE to guarantee
     * no two concurrent calls ever return the same sequence for the same
     * (tenant, prefix) pair — even under high concurrency.
     *
     * Must be called inside a DB::transaction() (which CatalogService already does).
     */
    public function nextSequence(string $tenantId, string $prefix): int
    {
        // Ensure the row exists without failing if concurrent insert races
        DB::table('sku_sequences')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'prefix'    => $prefix,
            'last_seq'  => 0,
        ]);

        // Exclusive row lock — blocks any other transaction reading this row
        $row = DB::table('sku_sequences')
            ->where('tenant_id', $tenantId)
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        $nextSeq = $row->last_seq + 1;

        DB::table('sku_sequences')
            ->where('tenant_id', $tenantId)
            ->where('prefix', $prefix)
            ->update(['last_seq' => $nextSeq]);

        return $nextSeq;
    }
}
