<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Events\ProductArchived;
use App\Modules\Catalog\Events\ProductCreated;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\ProductIdentifierService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

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
            ->withCount('variants')
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

    /**
     * Load full product detail for the show page:
     * category + supplier + variants + attributes (axes + values).
     */
    public function findProductDetail(string $tenantId, string $id): ?Product
    {
        return Product::where('tenant_id', $tenantId)
            ->with([
                'category',
                'supplier:id,name,code,email,phone',
                'variants' => fn ($q) => $q->withTrashed(false)->orderBy('sort_order'),
                'attributes.values',
            ])
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
        /** @var ProductIdentifierService $identifierService */
        $identifierService = app(ProductIdentifierService::class);

        // ── SKU ──────────────────────────────────────────────────────────────
        if (empty($data['sku'])) {
            $categoryPrefix = null;
            if (! empty($data['category_id'])) {
                $categoryPrefix = Category::find($data['category_id'])?->name;
            }
            $data['sku'] = $identifierService->generateSku($tenantId, $categoryPrefix);
        }

        // ── Internal barcode ─────────────────────────────────────────────────
        if (empty($data['internal_barcode'])) {
            $data['internal_barcode']       = $identifierService->generateInternalBarcode($tenantId);
            $data['barcode_auto_generated'] = true;
            $data['barcode_source']         = 'AUTO';
        } else {
            $data['barcode_auto_generated'] = false;
            $data['barcode_source']         = 'MANUAL';
        }

        // ── GTIN validation ──────────────────────────────────────────────────
        if (! empty($data['gtin'])) {
            try {
                $identifierService->validateGtin($data['gtin']);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'gtin' => $e->getMessage(),
                ]);
            }
        }

        $product = Product::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'status'    => $data['status'] ?? 'draft',
        ]));

        event(new ProductCreated($product));

        try {
            app(\App\Modules\Platform\Services\AuditService::class)->log(
                auth()->id() ?? null, "product.created", "Product", $product->id,
                [],
                ["sku" => $product->sku, "name" => $product->name],
                request()?->ip(), request()?->userAgent(), "low"
            );
        } catch (\Throwable) {}

        return $product->load('category');
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);
        event(new ProductUpdated($product));

        try {
            app(\App\Modules\Platform\Services\AuditService::class)->log(
                "product.updated",
                $product->tenant_id,
                auth()->id() ?? null,
                $product,
                array_keys($data),
                ["name" => $product->name, "sku" => $product->sku, "status" => $product->status],
                null, null,
                request()?->ip(),
                request()?->userAgent(),
            );
        } catch (\Throwable) {}

        return $product->fresh(['category', 'variants']);
    }

    public function archiveProduct(Product $product): Product
    {
        $product->update(['status' => 'archived']);
        event(new ProductArchived($product));

        try {
            app(\App\Modules\Platform\Services\AuditService::class)->log(
                auth()->id() ?? null, 'product.archived', 'Product', $product->id,
                ['status' => 'active'],
                ['status' => 'archived', 'sku' => $product->sku, 'name' => $product->name],
                request()?->ip(), request()?->userAgent(), 'medium',
            );
        } catch (\Throwable) {}

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
        // Return ALL categories flat (roots + children).
        // Previously used whereNull('parent_id') which caused child categories
        // to be invisible in the list and in product form dropdowns.
        // The frontend handles tree rendering by grouping on parent_id.
        return Category::where('tenant_id', $tenantId)
            ->orderBy('parent_id')        // roots first (NULL < UUID alphabetically on MySQL)
            ->orderBy('sort_order')
            ->orderBy('name')
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
