<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

        $existing = ProductVariant::where('product_id', $productId)->count();
        $created  = 0;
        $skipped  = 0;

        foreach ($combinations as $attrs) {
            $label  = implode(' / ', array_values($attrs));
            $exists = ProductVariant::where('product_id', $productId)
                ->where('label', $label)->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            ProductVariant::create([
                'tenant_id'      => $tenantId,
                'product_id'     => $productId,
                'label'          => $label,
                'sku'            => $product->sku . '-V' . ($existing + $created + 1),
                'price_amount'   => $data['base_price']    ?? $product->price_amount,
                'price_currency' => $data['base_currency'] ?? $product->price_currency,
                'attributes'     => $attrs,
            ]);
            $created++;
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
}
