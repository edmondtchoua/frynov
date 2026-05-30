<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Payments\Http\Requests\CreatePaymentsRequest;
use App\Modules\Payments\Http\Requests\UpdatePaymentsRequest;
use App\Modules\Payments\Http\Resources\PaymentsResource;
use App\Modules\Payments\Services\PaymentsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class PaymentsController extends Controller
{
    public function __construct(
private readonly PaymentsService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
$items = $this->service->list(
    tenantId: $request->user()->tenant_id,
    filters:  $request->query(),
);

return PaymentsResource::collection($items);
    }

    public function show(Request $request, string $id): PaymentsResource
    {
$item = $this->service->findOrFail($id, $request->user()->tenant_id);

return new PaymentsResource($item);
    }

    public function store(CreatePaymentsRequest $request): JsonResponse
    {
$item = $this->service->create(
    data:     $request->validated(),
    tenantId: $request->user()->tenant_id,
);

return (new PaymentsResource($item))
    ->response()
    ->setStatusCode(201);
    }

    public function update(UpdatePaymentsRequest $request, string $id): PaymentsResource
    {
$item = $this->service->update($id, $request->validated(), $request->user()->tenant_id);

return new PaymentsResource($item);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
$this->service->delete($id, $request->user()->tenant_id);

return response()->json(null, 204);
    }
}