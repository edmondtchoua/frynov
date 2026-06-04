<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Http\Resources\CustomerResource;
use App\Modules\Customers\Services\CustomerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    // ── GET /api/customers ────────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = $this->service->list(
            tenantId: $request->user()->tenant_id,
            filters:  $request->only(['search', 'per_page']),
        );

        return CustomerResource::collection($customers);
    }

    // ── GET /api/customers/search?q=... ───────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();

        if (strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $results = $this->service->search($term, $request->user()->tenant_id);

        return response()->json(['data' => CustomerResource::collection($results)]);
    }

    // ── GET /api/customers/{id} ───────────────────────────────────────────────

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $customer = $this->service->findOrFail($id, $request->user()->tenant_id);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Client introuvable.'], 404);
        }

        $customer->loadCount('orders');

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    // ── POST /api/customers ───────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'address'           => ['nullable', 'array'],
            'address.street'    => ['nullable', 'string', 'max:255'],
            'address.city'      => ['nullable', 'string', 'max:100'],
            'address.zip'       => ['nullable', 'string', 'max:20'],
            'address.country'   => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string'],
        ]);

        $customer = $this->service->create($data, $request->user()->tenant_id);

        try {
            app(\App\Modules\Platform\Services\AuditService::class)->log(
                "customer.created",
                $request->user()->tenant_id,
                $request->user()->id,
                $customer,
                [],
                ["name" => $customer->name, "email" => $customer->email ?? null],
                null, null,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (\Throwable) {}

        return response()->json(['data' => new CustomerResource($customer)], 201);
    }

    // ── PUT /api/customers/{id} ───────────────────────────────────────────────

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $customer = $this->service->findOrFail($id, $request->user()->tenant_id);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Client introuvable.'], 404);
        }

        $data = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'address'           => ['nullable', 'array'],
            'address.street'    => ['nullable', 'string', 'max:255'],
            'address.city'      => ['nullable', 'string', 'max:100'],
            'address.zip'       => ['nullable', 'string', 'max:20'],
            'address.country'   => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string'],
        ]);

        $customer = $this->service->update($customer, $data);

        try {
            app(\App\Modules\Platform\Services\AuditService::class)->log(
                'customer.updated',
                $request->user()->tenant_id,
                $request->user()->id,
                $customer,
                array_keys($data),
                ['name' => $customer->name, 'email' => $customer->email ?? null],
                null, null,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (\Throwable) {}

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    // ── DELETE /api/customers/{id} ────────────────────────────────────────────

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $customer = $this->service->findOrFail($id, $request->user()->tenant_id);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Client introuvable.'], 404);
        }

        $this->service->delete($customer);

        return response()->json(null, 204);
    }

    // ── GET /api/customers/{id}/orders ────────────────────────────────────────

    public function orders(Request $request, string $id): JsonResponse
    {
        try {
            $customer = $this->service->findOrFail($id, $request->user()->tenant_id);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Client introuvable.'], 404);
        }

        $orders = $customer->orders()
            ->select(['id', 'number', 'status', 'total_amount', 'currency', 'created_at'])
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }
}
