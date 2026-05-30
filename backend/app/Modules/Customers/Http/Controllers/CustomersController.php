<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Http\Requests\CreateCustomersRequest;
use App\Modules\Customers\Http\Requests\UpdateCustomersRequest;
use App\Modules\Customers\Http\Resources\CustomersResource;
use App\Modules\Customers\Services\CustomersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CustomersController extends Controller
{
    public function __construct(
private readonly CustomersService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
$items = $this->service->list(
    tenantId: $request->user()->tenant_id,
    filters:  $request->query(),
);

return CustomersResource::collection($items);
    }

    public function show(Request $request, string $id): CustomersResource
    {
$item = $this->service->findOrFail($id, $request->user()->tenant_id);

return new CustomersResource($item);
    }

    public function store(CreateCustomersRequest $request): JsonResponse
    {
$item = $this->service->create(
    data:     $request->validated(),
    tenantId: $request->user()->tenant_id,
);

return (new CustomersResource($item))
    ->response()
    ->setStatusCode(201);
    }

    public function update(UpdateCustomersRequest $request, string $id): CustomersResource
    {
$item = $this->service->update($id, $request->validated(), $request->user()->tenant_id);

return new CustomersResource($item);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
$this->service->delete($id, $request->user()->tenant_id);

return response()->json(null, 204);
    }
}