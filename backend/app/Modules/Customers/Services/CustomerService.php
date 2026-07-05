<?php

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    /**
     * Paginated list, optionally filtered by search query.
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = Customer::forTenant($tenantId)->withCount('orders');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Find a customer for a given tenant or throw 404.
     */
    public function findOrFail(string $id, string $tenantId): Customer
    {
        return Customer::forTenant($tenantId)->findOrFail($id);
    }

    /**
     * Create a new customer.
     */
    public function create(array $data, string $tenantId): Customer
    {
        return Customer::create([...$data, 'tenant_id' => $tenantId]);
    }

    /**
     * Update an existing customer.
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->fresh();
    }

    /**
     * Soft-delete a customer.
     */
    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * Typeahead search — returns up to $limit results.
     */
    public function search(string $term, string $tenantId, int $limit = 10): Collection
    {
        return Customer::forTenant($tenantId)
            ->search($term)
            ->limit($limit)
            ->orderBy('name')
            ->get();
    }
}
