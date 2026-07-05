<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Product attribute management per product.
 * Attributes define the axes of variation (Taille, Couleur, Matière…)
 * and their values (S/M/L, Rouge/Bleu, Coton/Soie…).
 */
class ProductAttributeController extends Controller
{
    /** GET /api/catalog/products/{productId}/attributes */
    public function index(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = Product::where('tenant_id', $tenantId)->findOrFail($productId);

        $attrs = ProductAttribute::where('product_id', $product->id)
            ->with('values')
            ->orderBy('position')
            ->get();

        return response()->json(['data' => $attrs]);
    }

    /** POST /api/catalog/products/{productId}/attributes */
    public function store(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = Product::where('tenant_id', $tenantId)->findOrFail($productId);

        $data = $request->validate([
            'name'     => 'required|string|max:80',
            'code'     => 'nullable|string|max:40|regex:/^[a-z0-9_]+$/',
            'type'     => 'nullable|in:text,color,size,image',
            'position' => 'nullable|integer|min:0',
            'values'   => 'nullable|array',
            'values.*.label'     => 'required_with:values|string|max:80',
            'values.*.value'     => 'nullable|string|max:80',
            'values.*.color_hex' => 'nullable|string|max:7',
            'values.*.position'  => 'nullable|integer|min:0',
        ]);

        $attr = ProductAttribute::create([
            'tenant_id'  => $tenantId,
            'product_id' => $product->id,
            'name'       => $data['name'],
            'code'       => $data['code'] ?? strtolower(str_replace(' ', '_', $data['name'])),
            'type'       => $data['type'] ?? 'text',
            'position'   => $data['position'] ?? 0,
        ]);

        foreach ($data['values'] ?? [] as $i => $v) {
            $attr->values()->create([
                'label'     => $v['label'],
                'value'     => $v['value'] ?? $v['label'],
                'color_hex' => $v['color_hex'] ?? null,
                'position'  => $v['position'] ?? $i,
            ]);
        }

        return response()->json(['data' => $attr->load('values')], 201);
    }

    /** PUT /api/catalog/products/{productId}/attributes/{attributeId} */
    public function update(Request $request, string $productId, string $attributeId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        Product::where('tenant_id', $tenantId)->findOrFail($productId);

        $attr = ProductAttribute::where('product_id', $productId)->findOrFail($attributeId);

        $data = $request->validate([
            'name'     => 'sometimes|string|max:80',
            'position' => 'nullable|integer|min:0',
        ]);

        $attr->update($data);

        return response()->json(['data' => $attr->fresh('values')]);
    }

    /** DELETE /api/catalog/products/{productId}/attributes/{attributeId} */
    public function destroy(Request $request, string $productId, string $attributeId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        Product::where('tenant_id', $tenantId)->findOrFail($productId);

        $attr = ProductAttribute::where('product_id', $productId)->findOrFail($attributeId);
        $attr->delete();

        return response()->json(['message' => 'Attribut supprimé.']);
    }

    /** POST /api/catalog/products/{productId}/attributes/{attributeId}/values */
    public function addValue(Request $request, string $productId, string $attributeId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        Product::where('tenant_id', $tenantId)->findOrFail($productId);

        $attr = ProductAttribute::where('product_id', $productId)->findOrFail($attributeId);

        $data = $request->validate([
            'label'     => 'required|string|max:80',
            'value'     => 'nullable|string|max:80',
            'color_hex' => 'nullable|string|max:7',
            'position'  => 'nullable|integer|min:0',
        ]);

        $value = $attr->values()->create([
            'label'     => $data['label'],
            'value'     => $data['value'] ?? $data['label'],
            'color_hex' => $data['color_hex'] ?? null,
            'position'  => $data['position'] ?? $attr->values()->count(),
        ]);

        return response()->json(['data' => $value], 201);
    }

    /** DELETE /api/catalog/products/{productId}/attributes/{attributeId}/values/{valueId} */
    public function removeValue(Request $request, string $productId, string $attributeId, string $valueId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        Product::where('tenant_id', $tenantId)->findOrFail($productId);

        ProductAttribute::where('product_id', $productId)->findOrFail($attributeId);

        ProductAttributeValue::where('attribute_id', $attributeId)->findOrFail($valueId)->delete();

        return response()->json(['message' => 'Valeur supprimée.']);
    }
}
