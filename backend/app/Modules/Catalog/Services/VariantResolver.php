<?php
namespace App\Modules\Catalog\Services;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves a product variant from a combination of attribute values.
 * Uses a deterministic hash for O(1) cache lookup.
 */
class VariantResolver
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * @param string $productId
     * @param array  $values ['color' => 'red', 'ram' => '8gb']
     */
    public function resolve(string $productId, array $values): ?ProductVariant
    {
        ksort($values); // deterministic key order
        $signature  = md5(json_encode($values));
        $cacheKey   = "variant_resolve:{$productId}:{$signature}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($productId, $values) {
            $valueCount = count($values);
            if ($valueCount === 0) return null;

            // Find variant with EXACTLY these attribute values
            return ProductVariant::where('product_id', $productId)
                ->where('is_active', true)
                ->whereHas('attributeValues', function ($q) use ($values) {
                    $q->whereHas('attribute', fn ($a) => $a->whereIn('code', array_keys($values)))
                      ->whereIn('value', array_values($values));
                }, '=', $valueCount)
                ->with(['product:id,name,sku,price_amount,price_currency', 'attributeValues.attribute'])
                ->first();
        });
    }

    /** Invalidate cache when a variant is updated */
    public function invalidate(string $productId): void
    {
        Cache::flush(); // TODO: use cache tags for targeted invalidation
    }
}
