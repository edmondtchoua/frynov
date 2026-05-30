<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Http\Requests\CreateOrdersRequest;
use App\Modules\Orders\Http\Requests\UpdateOrdersRequest;
use App\Modules\Orders\Http\Resources\OrdersResource;
use App\Modules\Orders\Services\OrdersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class OrdersController extends Controller
{
    public function __construct(
private readonly OrdersService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
$items = $this->service->list(
    tenantId: $request->user()->tenant_id,
    filters:  $request->query(),
);

return OrdersResource::collection($items);
    }

    public function show(Request $request, string $id): OrdersResource
    {
$item = $this->service->findOrFail($id, $request->user()->tenant_id);

return new OrdersResource($item);
    }

    public function store(CreateOrdersRequest $request): JsonResponse
    {
$item = $this->service->create(
    data:     $request->validated(),
    tenantId: $request->user()->tenant_id,
);

return (new OrdersResource($item))
    ->response()
    ->setStatusCode(201);
    }

    public function update(UpdateOrdersRequest $request, string $id): OrdersResource
    {
$item = $this->service->update($id, $request->validated(), $request->user()->tenant_id);

return new OrdersResource($item);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
$this->service->delete($id, $request->user()->tenant_id);

return response()->json(null, 204);
    }
}