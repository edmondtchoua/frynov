<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId   = $request->user()?->tenant_id ?? $request->header('X-Tenant-ID');
        $categories = $this->catalog->listCategories($tenantId);

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255'],
            'parent_id'   => ['nullable', 'uuid'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-ID');
        $category = $this->catalog->createCategory($tenantId, $data);

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-ID');
        $category = Category::where('tenant_id', $tenantId)->find($id);

        if (! $category) {
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'parent_id'   => ['nullable', 'uuid'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $category = $this->catalog->updateCategory($category, $data);

        return response()->json(['data' => new CategoryResource($category)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? $request->header('X-Tenant-ID');
        $category = Category::where('tenant_id', $tenantId)->find($id);

        if (! $category) {
            return response()->json(['message' => 'Catégorie introuvable.'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }
}
