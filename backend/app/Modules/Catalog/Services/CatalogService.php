<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Events\ProductArchived;
use App\Modules\Catalog\Events\ProductCreated;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CatalogService
{
    public function __construct(
        private readonly SkuGeneratorService $skuGenerator,
    ) {}

    // ── Products ──────────────────────────────────────────────────────────

    public function listProducts(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        return Product::where('tenant_id', $tenantId)
            ->with('category')
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when(
                isset($filters['category_id']),
                fn ($q) => $q->where('category_id', $filters['category_id']),
            )
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($inner) use ($filters) {
                    $inner->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('sku', 'like', "%{$filters['search']}%");
                }),
            )
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function findProduct(string $tenantId, string $id): ?Product
    {
        return Product::where('tenant_id', $tenantId)
            ->with(['category', 'variants'])
            ->find($id);
    }

    public function findProductBySku(string $tenantId, string $sku): ?Product
    {
        return Product::where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->with(['category', 'variants'])
            ->first();
    }

    public function createProduct(string $tenantId, array $data): Product
    {
        if (empty($data['sku'])) {
            $data['sku'] = $this->skuGenerator->generate($tenantId, $data['sku_prefix'] ?? 'PRD');
        }

        $product = Product::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'status'    => $data['status'] ?? 'draft',
        ]));

        event(new ProductCreated($product));

        return $product->load('category');
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);
        event(new ProductUpdated($product));

        return $product->fresh(['category', 'variants']);
    }

    public function archiveProduct(Product $product): Product
    {
        $product->update(['status' => 'archived']);
        event(new ProductArchived($product));

        return $product;
    }

    public function activateProduct(Product $product): Product
    {
        $product->update(['status' => 'active']);

        return $product;
    }

    // ── Variants ──────────────────────────────────────────────────────────

    public function createVariant(Product $product, array $data): ProductVariant
    {
        if (empty($data['sku'])) {
            $index       = $product->variants()->count() + 1;
            $data['sku'] = $this->skuGenerator->generateVariant($product->sku, $index);
        }

        $variant = ProductVariant::create(array_merge($data, [
            'product_id' => $product->id,
            'tenant_id'  => $product->tenant_id,
        ]));

        if (! $product->has_variants) {
            $product->update(['has_variants' => true]);
        }

        return $variant;
    }

    // ── Categories ────────────────────────────────────────────────────────

    public function listCategories(string $tenantId): Collection
    {
        return Category::where('tenant_id', $tenantId)
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    public function createCategory(string $tenantId, array $data): Category
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateCategorySlug($tenantId, $data['name']);
        }

        return Category::create(array_merge($data, ['tenant_id' => $tenantId]));
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh('children');
    }

    private function generateCategorySlug(string $tenantId, string $name): string
    {
        $base  = str($name)->slug()->value();
        $slug  = $base;
        $count = 1;

        while (Category::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }
}
