<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CatalogService;
use App\Modules\Catalog\Services\ProductDuplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId   = $request->user()->tenant_id;
        $categories = $this->catalog->listCategories($tenantId);

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255'],
            // Security: a parent category must belong to the SAME tenant (no cross-tenant linking).
            'parent_id'   => ['nullable', 'uuid', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $category = $this->catalog->createCategory($tenantId, $data);

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $category = Category::where('tenant_id', $tenantId)->find($id);

        if (! $category) {
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            // Security: a parent category must belong to the SAME tenant (no cross-tenant linking).
            'parent_id'   => ['nullable', 'uuid', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $category = $this->catalog->updateCategory($category, $data);

        return response()->json(['data' => new CategoryResource($category)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        // Belt-and-suspenders role check (in addition to route middleware role:manager|admin)
        if (! $request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Action réservée aux managers et administrateurs.'], 403);
        }

        $tenantId = $request->user()->tenant_id;
        $category = Category::where('tenant_id', $tenantId)->find($id);

        if (! $category) {
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }

    /**
     * POST /api/catalog/categories/{id}/duplicate-preview
     * Aperçu NON persisté (nœud seul, slug régénéré, sans produits ni sous-catégories).
     */
    public function duplicatePreview(Request $request, string $id): JsonResponse
    {
        $category = Category::where('tenant_id', $request->user()->tenant_id)->find($id);

        if (! $category) {
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        return response()->json(['data' => app(ProductDuplicationService::class)->previewCategory($category)]);
    }

    /**
     * POST /api/catalog/categories/{id}/duplicate
     * Duplique le NŒUD catégorie seul (nom + « (copie) », parent identique, slug régénéré).
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $category = Category::where('tenant_id', $request->user()->tenant_id)->find($id);

        if (! $category) {
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        $new = app(ProductDuplicationService::class)->duplicateCategory($category);

        return response()->json(['data' => new CategoryResource($new)], 201);
    }
}
