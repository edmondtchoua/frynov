<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeValue;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ProductVariantController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * Sprint 16 — Generate variants from multiple attribute axes (cartesian product).
     * POST /api/catalog/products/{id}/variants/generate
     * Payload: { axes: [{name, values[]}], base_price?, base_currency? }
     * Creates all combinations, skips existing ones by label.
     */
    public function generate(Request $request, string $productId): JsonResponse
    {
        $data = $request->validate([
            // N-dimensional axes — no artificial limit on axis count
            // (practical limit: combinatorial explosion managed by client)
            'axes'              => ['required', 'array', 'min:1'],
            'axes.*.name'       => ['required', 'string', 'max:100'],
            'axes.*.values'     => ['required', 'array', 'min:1'],
            'axes.*.values.*'   => ['required', 'string', 'max:100'],
            'base_price'        => ['nullable', 'integer', 'min:0'],
            'base_currency'     => ['nullable', 'string', 'size:3'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        // Build cartesian product of all axes
        $combinations = [[]];
        foreach ($data['axes'] as $axis) {
            $next = [];
            foreach ($combinations as $combo) {
                foreach ($axis['values'] as $value) {
                    $next[] = array_merge($combo, [$axis['name'] => $value]);
                }
            }
            $combinations = $next;
        }

        // Count ALL variants ever created (incl. soft-deleted) to avoid SKU collision.
        // Using count() without withTrashed() would reuse SKUs of deleted variants
        // → UniqueConstraintViolationException on (tenant_id, sku).
        $existingTotal = ProductVariant::withTrashed()
            ->where('product_id', $productId)
            ->count();

        $created = 0;
        $skipped = 0;

        foreach ($combinations as $attrs) {
            $label = implode(' / ', array_values($attrs));

            // Skip if label already exists (including soft-deleted — same label is semantically duplicate)
            $labelExists = ProductVariant::withTrashed()
                ->where('product_id', $productId)
                ->where('label', $label)
                ->exists();

            if ($labelExists) {
                $skipped++;
                continue;
            }

            // Generate a unique SKU — always higher than any ever used for this product
            $candidateSku = $product->sku . '-V' . ($existingTotal + $created + 1);

            // Extra safety: if by any chance this SKU exists, keep incrementing
            $offset = 0;
            while (ProductVariant::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('sku', $candidateSku)
                ->exists()) {
                $offset++;
                $candidateSku = $product->sku . '-V' . ($existingTotal + $created + 1 + $offset);
            }

            ProductVariant::create([
                'tenant_id'      => $tenantId,
                'product_id'     => $productId,
                'label'          => $label,
                'sku'            => $candidateSku,
                'price_amount'   => $data['base_price']    ?? $product->price_amount,
                'price_currency' => $data['base_currency'] ?? $product->price_currency,
                'attributes'     => $attrs,
            ]);
            $created++;
        }

        // ── Sync axes to normalized product_attributes tables ───────────────────
        // This makes the axes visible in AttributesView and ProductShowPage
        // and enables structured querying + label printing with attribute names.
        if ($created > 0 || $skipped > 0) {
            $this->syncAxesToNormalizedAttributes($data['axes'], $product->id, $tenantId);
        }

        // Ensure product is flagged as variable after generation
        if ($created > 0 && ! $product->has_variants) {
            $product->update([
                'has_variants' => true,
                'product_type' => 'variable',
            ]);
        }

        return response()->json([
            'message'            => "{$created} variante(s) générée(s), {$skipped} ignorée(s) (déjà existantes).",
            'created'            => $created,
            'skipped'            => $skipped,
            'total_combinations' => count($combinations),
        ]);
    }

    public function store(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $data = $request->validate([
            'sku'            => ['nullable', 'string', 'max:100'],
            'name'           => ['nullable', 'string', 'max:255'],
            'attributes'     => ['nullable', 'array'],
            'price_amount'   => ['nullable', 'integer', 'min:0'],
            'price_currency' => ['nullable', 'string', 'size:3'],
            'cost_amount'    => ['nullable', 'integer', 'min:0'],
            'barcode'        => ['nullable', 'string', 'max:50'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $variant = $this->catalog->createVariant($product, $data);

        return response()->json(['data' => new ProductVariantResource($variant->load('product'))], 201);
    }

    public function update(Request $request, string $productId, string $variantId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('tenant_id', $tenantId)
            ->find($variantId);

        if (! $variant) {
            return response()->json(['message' => 'Variante introuvable.'], 404);
        }

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'attributes'     => ['nullable', 'array'],
            'price_amount'   => ['nullable', 'integer', 'min:0'],
            'price_currency' => ['nullable', 'string', 'size:3'],
            'barcode'        => ['nullable', 'string', 'max:50'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $variant->update($data);

        return response()->json(['data' => new ProductVariantResource($variant->fresh('product'))]);
    }

    public function destroy(Request $request, string $productId, string $variantId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $productId);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('tenant_id', $tenantId)
            ->find($variantId);

        if (! $variant) {
            return response()->json(['message' => 'Variante introuvable.'], 404);
        }

        $variant->delete();

        return response()->json(['message' => 'Variante supprimée.']);
    }

    /**
     * Sync N-axis builder axes to normalized product_attributes / product_attribute_values tables.
     *
     * This bridges the gap between:
     *   - The N-axis builder (writes JSON blobs on product_variants.attributes)
     *   - The normalized attribute system (product_attributes / product_attribute_values)
     *
     * Rules:
     * - If an attribute with the same code already exists for this product → add missing values only
     * - If it doesn't exist → create it
     * - Existing values are never deleted (additive-only to avoid breaking labels/history)
     *
     * @param array  $axes     [['name' => 'Couleur', 'values' => ['Rouge', 'Bleu']], ...]
     * @param string $productId
     * @param string $tenantId
     */
    private function syncAxesToNormalizedAttributes(array $axes, string $productId, string $tenantId): void
    {
        foreach ($axes as $position => $axis) {
            $name = trim($axis['name']);
            if (empty($name)) continue;

            // Normalize code: "Couleur" → "couleur", "RAM (Go)" → "ram_go"
            $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
            $code = trim($code, '_') ?: 'attr_' . ($position + 1);

            // Find or create the attribute axis for this product
            $attr = ProductAttribute::firstOrCreate(
                ['product_id' => $productId, 'code' => $code],
                [
                    'id'        => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name'      => $name,
                    'type'      => 'select',
                    'position'  => $position,
                ]
            );

            // Add missing values (additive — never delete existing values)
            $existingValues = $attr->values()->pluck('value')->toArray();

            foreach (($axis['values'] ?? []) as $valPos => $rawVal) {
                $label = trim((string) $rawVal);
                if (empty($label)) continue;

                // Normalize value key: "Rouge" → "rouge", "30L" → "30l"
                $valueKey = strtolower(preg_replace('/\s+/', '_', $label));

                if (! in_array($valueKey, $existingValues, true)) {
                    ProductAttributeValue::create([
                        'id'           => (string) Str::uuid(),
                        'attribute_id' => $attr->id,
                        'label'        => $label,
                        'value'        => $valueKey,
                        'position'     => $valPos,
                    ]);
                    $existingValues[] = $valueKey; // avoid duplicate in same request
                }
            }
        }
    }
}
