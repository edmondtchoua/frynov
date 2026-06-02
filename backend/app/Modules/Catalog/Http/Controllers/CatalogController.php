<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CatalogResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $request->user()->tenant_id;

        $products = $this->catalog->listProducts($tenantId, $request->only([
            'status', 'category_id', 'search', 'per_page',
        ]));

        return CatalogResource::collection($products);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        return response()->json(['data' => new CatalogResource($product)]);
    }

    public function findBySku(Request $request, string $sku): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProductBySku($tenantId, $sku);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        return response()->json(['data' => new CatalogResource($product)]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'sku'                     => ['nullable', 'string', 'max:100',
                Rule::unique('products')->where('tenant_id', $tenantId)],
            'sku_prefix'              => ['nullable', 'string', 'max:5'],
            'description'             => ['nullable', 'string'],
            'price_amount'            => ['required', 'integer', 'min:0'],
            'price_currency'          => ['required', 'string', 'size:3'],
            'compare_at_price_amount' => ['nullable', 'integer', 'min:0'],
            'cost_amount'             => ['nullable', 'integer', 'min:0'],
            'status'                  => ['nullable', 'in:draft,active,archived'],
            'category_id'             => ['nullable', 'uuid'],
            'barcode'                 => ['nullable', 'string', 'max:50'],
            'internal_barcode'        => ['nullable', 'string', 'max:50'],
            'gtin'                    => ['nullable', 'string', 'max:20'],
            'barcode_type'            => ['nullable', 'in:INTERNAL,EAN13,UPC_A,GTIN_13,GTIN_14,CODE128,QR'],
            'weight_kg'               => ['nullable', 'numeric', 'min:0'],
            'metadata'                => ['nullable', 'array'],
            'has_variants'            => ['nullable', 'boolean'],
        ]);

        $product  = $this->catalog->createProduct($tenantId, $data);

        return response()->json(['data' => new CatalogResource($product)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $data = $request->validate([
            'name'                    => ['sometimes', 'string', 'max:255'],
            'description'             => ['nullable', 'string'],
            'price_amount'            => ['sometimes', 'integer', 'min:0'],
            'price_currency'          => ['sometimes', 'string', 'size:3'],
            'compare_at_price_amount' => ['nullable', 'integer', 'min:0'],
            'cost_amount'             => ['nullable', 'integer', 'min:0'],
            'status'                  => ['sometimes', 'in:draft,active,archived'],
            'category_id'             => ['nullable', 'uuid'],
            'barcode'                 => ['nullable', 'string', 'max:50'],
            'weight_kg'               => ['nullable', 'numeric', 'min:0'],
            'metadata'                => ['nullable', 'array'],
        ]);

        $product = $this->catalog->updateProduct($product, $data);

        return response()->json(['data' => new CatalogResource($product)]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $this->catalog->archiveProduct($product);

        return response()->json(['message' => 'Produit archivé.']);
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $product  = $this->catalog->findProduct($tenantId, $id);

        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $product = $this->catalog->activateProduct($product);

        return response()->json(['data' => new CatalogResource($product)]);
    }
}
