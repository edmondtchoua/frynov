<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Http\Requests\CreateDeliveryRequest;
use App\Modules\Delivery\Http\Requests\UpdateDeliveryRequest;
use App\Modules\Delivery\Http\Resources\DeliveryResource;
use App\Modules\Delivery\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class DeliveryController extends Controller
{
    public function __construct(
private readonly DeliveryService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
$items = $this->service->list(
    tenantId: $request->user()->tenant_id,
    filters:  $request->query(),
);

return DeliveryResource::collection($items);
    }

    public function show(Request $request, string $id): DeliveryResource
    {
$item = $this->service->findOrFail($id, $request->user()->tenant_id);

return new DeliveryResource($item);
    }

    public function store(CreateDeliveryRequest $request): JsonResponse
    {
$item = $this->service->create(
    data:     $request->validated(),
    tenantId: $request->user()->tenant_id,
);

return (new DeliveryResource($item))
    ->response()
    ->setStatusCode(201);
    }

    public function update(UpdateDeliveryRequest $request, string $id): DeliveryResource
    {
$item = $this->service->update($id, $request->validated(), $request->user()->tenant_id);

return new DeliveryResource($item);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
$this->service->delete($id, $request->user()->tenant_id);

return response()->json(null, 204);
    }
}