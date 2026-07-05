<?php

namespace App\Modules\Suppliers\Services;

use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SupplierService
{
    // ── Queries ────────────────────────────────────────────────────────────────

    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = Supplier::forTenant($tenantId)->withCount('products');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('name')->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function findOrFail(string $id, string $tenantId): Supplier
    {
        return Supplier::forTenant($tenantId)->findOrFail($id);
    }

    public function search(string $term, string $tenantId, int $limit = 10): Collection
    {
        return Supplier::forTenant($tenantId)
            ->active()
            ->search($term)
            ->limit($limit)
            ->orderBy('name')
            ->get();
    }

    // ── Commands ───────────────────────────────────────────────────────────────

    public function create(array $data, string $tenantId): Supplier
    {
        // RC-20 (P-3) — génération du code dans une transaction : la séquence est verrouillée le
        // temps de l'insert (plus de course count()+1 → codes dupliqués en concurrence).
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $tenantId) {
            if (empty($data['code'])) {
                $data['code'] = $this->nextCode($tenantId);
            }

            $data['status'] ??= 'active';

            return Supplier::create([...$data, 'tenant_id' => $tenantId]);
        });
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    /** Find or create by name (used during import). */
    public function findOrCreateByName(string $name, string $tenantId): Supplier
    {
        return Supplier::forTenant($tenantId)
            ->where('name', $name)
            ->firstOr(fn () => $this->create(['name' => $name], $tenantId));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function nextCode(string $tenantId): string
    {
        return app(\App\Shared\Services\SequenceService::class)->next(
            $tenantId, 'SUP', 4,
            fn () => Supplier::forTenant($tenantId)->withTrashed()->count(),
        );
    }
}
