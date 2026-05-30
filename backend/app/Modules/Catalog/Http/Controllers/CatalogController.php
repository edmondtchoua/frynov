<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\CreateCatalogRequest;
use App\Modules\Catalog\Http\Requests\UpdateCatalogRequest;
use App\Modules\Catalog\Http\Resources\CatalogResource;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CatalogController extends Controller
{
    public function __construct(
private readonly CatalogService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
$items = $this->service->list(
    tenantId: $request->user()->tenant_id,
    filters:  $request->query(),
);

return CatalogResource::collection($items);
    }

    public function show(Request $request, string $id): CatalogResource
    {
$item = $this->service->findOrFail($id, $request->user()->tenant_id);

return new CatalogResource($item);
    }

    public function store(CreateCatalogRequest $request): JsonResponse
    {
$item = $this->service->create(
    data:     $request->validated(),
    tenantId: $request->user()->tenant_id,
);

return (new CatalogResource($item))
    ->response()
    ->setStatusCode(201);
    }

    public function update(UpdateCatalogRequest $request, string $id): CatalogResource
    {
$item = $this->service->update($id, $request->validated(), $request->user()->tenant_id);

return new CatalogResource($item);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
$this->service->delete($id, $request->user()->tenant_id);

return response()->json(null, 204);
    }
}