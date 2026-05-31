<?php

namespace App\Modules\Suppliers\Http\Controllers;

use App\Modules\Suppliers\Http\Resources\SupplierResource;
use App\Modules\Suppliers\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $service) {}

    // ── GET /api/suppliers ────────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        return SupplierResource::collection(
            $this->service->list($request->user()->tenant_id, $request->query())
        );
    }

    // ── GET /api/suppliers/search?q= ──────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $term      = $request->query('q', '');
        $suppliers = $this->service->search($term, $request->user()->tenant_id);

        return response()->json(['data' => SupplierResource::collection($suppliers)]);
    }

    // ── GET /api/suppliers/{id} ───────────────────────────────────────────────

    public function show(Request $request, string $id): SupplierResource|JsonResponse
    {
        try {
            return new SupplierResource(
                $this->service->findOrFail($id, $request->user()->tenant_id)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }
    }

    // ── POST /api/suppliers ───────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'          => 'nullable|string|max:50',
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:30',
            'contact_name'  => 'nullable|string|max:255',
            'address'       => 'nullable|array',
            'address.street'=> 'nullable|string|max:255',
            'address.city'  => 'nullable|string|max:100',
            'address.zip'   => 'nullable|string|max:20',
            'address.country'=> 'nullable|string|max:100',
            'payment_terms' => 'nullable|string|max:100',
            'notes'         => 'nullable|string',
            'status'        => 'nullable|in:active,inactive',
        ]);

        $supplier = $this->service->create($data, $request->user()->tenant_id);

        return (new SupplierResource($supplier))
            ->response()
            ->setStatusCode(201);
    }

    // ── PUT /api/suppliers/{id} ───────────────────────────────────────────────

    public function update(Request $request, string $id): SupplierResource|JsonResponse
    {
        try {
            $supplier = $this->service->findOrFail($id, $request->user()->tenant_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $data = $request->validate([
            'code'          => 'nullable|string|max:50',
            'name'          => 'sometimes|required|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:30',
            'contact_name'  => 'nullable|string|max:255',
            'address'       => 'nullable|array',
            'payment_terms' => 'nullable|string|max:100',
            'notes'         => 'nullable|string',
            'status'        => 'nullable|in:active,inactive',
        ]);

        return new SupplierResource($this->service->update($supplier, $data));
    }

    // ── DELETE /api/suppliers/{id} ────────────────────────────────────────────

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $supplier = $this->service->findOrFail($id, $request->user()->tenant_id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Supplier not found.'], 404);
        }

        $this->service->delete($supplier);

        return response()->json(null, 204);
    }
}
