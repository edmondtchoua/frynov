<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;

class SkuGeneratorService
{
    /**
     * Generate a unique SKU for a tenant.
     * Format: {PREFIX}-{NNNN} — e.g. PRD-0001, VET-0042
     */
    public function generate(string $tenantId, string $prefix = 'PRD'): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix), 0, 5));

        $next = $this->nextSequence($tenantId, $prefix);

        return sprintf('%s-%04d', $prefix, $next);
    }

    /**
     * Generate a variant SKU from the parent product SKU.
     * Format: {PARENT_SKU}-V{N} — e.g. VET-0001-V1, VET-0001-V2
     */
    public function generateVariant(string $parentSku, int $variantIndex): string
    {
        return "{$parentSku}-V{$variantIndex}";
    }

    private function nextSequence(string $tenantId, string $prefix): int
    {
        $pattern = $prefix . '-%';

        $lastProduct = Product::where('tenant_id', $tenantId)
            ->where('sku', 'like', $pattern)
            ->orderByRaw('CAST(SUBSTR(sku, ?) AS INTEGER) DESC', [strlen($prefix) + 2])
            ->value('sku');

        $lastVariant = ProductVariant::where('tenant_id', $tenantId)
            ->where('sku', 'like', $pattern)
            ->orderByRaw('CAST(SUBSTR(sku, ?) AS INTEGER) DESC', [strlen($prefix) + 2])
            ->value('sku');

        $lastProduct = $lastProduct ? (int) substr($lastProduct, strlen($prefix) + 1) : 0;
        $lastVariant = $lastVariant ? (int) substr($lastVariant, strlen($prefix) + 1) : 0;

        return max($lastProduct, $lastVariant) + 1;
    }
}
