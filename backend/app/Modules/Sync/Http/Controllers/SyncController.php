<?php

namespace App\Modules\Sync\Http\Controllers;

use App\Modules\Sync\Http\Requests\CreateSyncRequest;
use App\Modules\Sync\Http\Requests\UpdateSyncRequest;
use App\Modules\Sync\Http\Resources\SyncResource;
use App\Modules\Sync\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class SyncController extends Controller
{
    public function __construct(
private readonly SyncService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
$items = $this->service->list(
    tenantId: $request->user()->tenant_id,
    filters:  $request->query(),
);

return SyncResource::collection($items);
    }

    public function show(Request $request, string $id): SyncResource
    {
$item = $this->service->findOrFail($id, $request->user()->tenant_id);

return new SyncResource($item);
    }

    public function store(CreateSyncRequest $request): JsonResponse
    {
$item = $this->service->create(
    data:     $request->validated(),
    tenantId: $request->user()->tenant_id,
);

return (new SyncResource($item))
    ->response()
    ->setStatusCode(201);
    }

    public function update(UpdateSyncRequest $request, string $id): SyncResource
    {
$item = $this->service->update($id, $request->validated(), $request->user()->tenant_id);

return new SyncResource($item);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
$this->service->delete($id, $request->user()->tenant_id);

return response()->json(null, 204);
    }
}