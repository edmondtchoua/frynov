<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Http\Requests\CreateInventoryRequest;
use App\Modules\Inventory\Http\Requests\UpdateInventoryRequest;
use App\Modules\Inventory\Http\Resources\InventoryResource;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class InventoryController extends Controller
{
    public function __construct(
private readonly InventoryService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
$items = $this->service->list(
    tenantId: $request->user()->tenant_id,
    filters:  $request->query(),
);

return InventoryResource::collection($items);
    }

    public function show(Request $request, string $id): InventoryResource
    {
$item = $this->service->findOrFail($id, $request->user()->tenant_id);

return new InventoryResource($item);
    }

    public function store(CreateInventoryRequest $request): JsonResponse
    {
$item = $this->service->create(
    data:     $request->validated(),
    tenantId: $request->user()->tenant_id,
);

return (new InventoryResource($item))
    ->response()
    ->setStatusCode(201);
    }

    public function update(UpdateInventoryRequest $request, string $id): InventoryResource
    {
$item = $this->service->update($id, $request->validated(), $request->user()->tenant_id);

return new InventoryResource($item);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
$this->service->delete($id, $request->user()->tenant_id);

return response()->json(null, 204);
    }
}