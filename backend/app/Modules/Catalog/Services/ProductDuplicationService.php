<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeValue;
use Illuminate\Support\Facades\DB;

/**
 * Duplication assistée sécurisée des produits et catégories (audit P1).
 *
 * Politique opposable — cf. docs/modules/catalog-duplication-audit.md §5bis :
 *  - le backend est SOURCE DE VÉRITÉ (jamais une copie du payload front) ;
 *  - identifiants uniques RÉGÉNÉRÉS : `sku`, `internal_barcode` (via CatalogService),
 *    `sku` des variantes, `slug` de catégorie ;
 *  - codes externes VIDÉS : `barcode`, `gtin` ;
 *  - `status` forcé à `draft` (jamais publier une copie par accident) ;
 *  - JAMAIS copié : stock, mouvements, lots, séries/IMEI/VIN, garanties, licences ;
 *  - tout est transactionnel (rollback total si une étape échoue).
 */
class ProductDuplicationService
{
    /** Champs descriptifs / configuration produit copiés tels quels. */
    private const COPIED_PRODUCT_FIELDS = [
        'category_id', 'supplier_id', 'description',
        'price_amount', 'price_currency', 'compare_at_price_amount', 'cost_amount',
        'product_type', 'has_variants', 'weight_kg', 'metadata',
    ];

    public function __construct(private readonly CatalogService $catalog) {}

    // ── Produit ──────────────────────────────────────────────────────────────

    /** Aperçu NON persisté de ce que produirait la duplication (pour le wizard). */
    public function previewProduct(Product $source): array
    {
        $source->loadMissing(['attributes.values', 'variants']);

        return [
            'source'      => ['id' => $source->id, 'sku' => $source->sku, 'name' => $source->name],
            'result'      => [
                'name'             => $this->copyName($source->name),
                'status'           => 'draft',
                'category_id'      => $source->category_id,
                'product_type'     => $source->product_type,
                'price_amount'     => $source->price_amount,
                'cost_amount'      => $source->cost_amount,
                'attributes_count' => $source->attributes->count(),
                'variants_count'   => $source->variants->count(),
            ],
            'regenerated' => ['sku', 'internal_barcode', 'variant_skus'],
            'cleared'     => ['barcode', 'gtin'],
            'excluded'    => ['stock', 'movements', 'batches', 'serials', 'warranties', 'licenses'],
        ];
    }

    /** Duplique le produit (+ attributs/valeurs + variantes) de façon transactionnelle. */
    public function duplicateProduct(Product $source): Product
    {
        $source->loadMissing(['attributes.values', 'variants.attributeValues']);

        return DB::transaction(function () use ($source) {
            // 1. Produit — champs copiés ; SKU + internal_barcode régénérés par createProduct
            //    (sku/internal_barcode/barcode/gtin OMIS) ; status draft ; nom suffixé « (copie) ».
            $data = [];
            foreach (self::COPIED_PRODUCT_FIELDS as $field) {
                $data[$field] = $source->{$field};
            }
            $data['name']   = $this->copyName($source->name);
            $data['status'] = 'draft';

            $new = $this->catalog->createProduct($source->tenant_id, $data);

            // 2. Attributs (axes) + valeurs — remap product_id ; table oldValueId → newValueId.
            $valueMap = [];
            foreach ($source->attributes as $attr) {
                $newAttr = ProductAttribute::create([
                    'tenant_id'  => $new->tenant_id,
                    'product_id' => $new->id,
                    'name'       => $attr->name,
                    'code'       => $attr->code,
                    'type'       => $attr->type,
                    'position'   => $attr->position,
                ]);

                foreach ($attr->values as $value) {
                    $newValue = ProductAttributeValue::create([
                        'attribute_id' => $newAttr->id,
                        'label'        => $value->label,
                        'value'        => $value->value,
                        'color_hex'    => $value->color_hex,
                        'image_url'    => $value->image_url,
                        'position'     => $value->position,
                    ]);
                    $valueMap[$value->id] = $newValue->id;
                }
            }

            // 3. Variantes — SKU régénéré (createVariant), barcode vidé, stock 0, pivot re-lié.
            foreach ($source->variants as $variant) {
                $newVariant = $this->catalog->createVariant($new, [
                    'name'           => $variant->name,
                    'label'          => $variant->label,
                    'attributes'     => $variant->attributes,
                    'price_amount'   => $variant->price_amount,
                    'price_currency' => $variant->price_currency,
                    'cost_amount'    => $variant->cost_amount,
                    'sort_order'     => $variant->sort_order,
                    'is_active'      => $variant->is_active,
                    // 'sku' omis → régénéré ; 'barcode' omis → null.
                ]);

                $newValueIds = $variant->attributeValues
                    ->map(fn ($v) => $valueMap[$v->id] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                if ($newValueIds !== []) {
                    $newVariant->attributeValues()->sync($newValueIds);
                }
            }

            $this->auditDuplication($new, $source);

            return $new->load(['category', 'variants', 'attributes.values']);
        });
    }

    // ── Catégorie ────────────────────────────────────────────────────────────

    public function previewCategory(Category $source): array
    {
        return [
            'source'      => ['id' => $source->id, 'name' => $source->name],
            'result'      => [
                'name'      => $this->copyName($source->name),
                'parent_id' => $source->parent_id,
            ],
            'regenerated' => ['slug'],
            'excluded'    => ['products', 'subcategories'],
        ];
    }

    /** Duplique le NŒUD catégorie seul (ni produits, ni sous-catégories). Slug régénéré. */
    public function duplicateCategory(Category $source): Category
    {
        return DB::transaction(fn () => $this->catalog->createCategory($source->tenant_id, [
            'name'        => $this->copyName($source->name),
            'parent_id'   => $source->parent_id,
            'description' => $source->description,
            'sort_order'  => $source->sort_order,
            'is_active'   => $source->is_active,
            // 'slug' omis → régénéré par createCategory (unicité tenant).
        ]));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function copyName(string $name): string
    {
        return trim($name) . ' (copie)';
    }

    private function auditDuplication(Product $new, Product $source): void
    {
        try {
            app(\App\Modules\Platform\Services\AuditService::class)->log(
                'product.duplicated',
                $new->tenant_id,
                auth()->id() ?? null,
                $new,
                [],
                ['sku' => $new->sku, 'name' => $new->name, 'source_id' => $source->id, 'source_sku' => $source->sku],
                null, null,
                request()?->ip(),
                request()?->userAgent(),
            );
        } catch (\Throwable) {
        }
    }
}
