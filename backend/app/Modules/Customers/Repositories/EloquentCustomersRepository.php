<?php

namespace App\Modules\Customers\Repositories;

use App\Modules\Customers\Models\Customers;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentCustomersRepository implements CustomersRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return Customers::query()
    ->where('tenant_id', $tenantId)
    ->paginate(20);
    }

    public function findById(string $id, string $tenantId): ?Customers
    {
return Customers::query()
    ->where('tenant_id', $tenantId)
    ->find($id);
    }

    public function create(array $data): Customers
    {
return Customers::create($data);
    }

    public function update(Customers $model, array $data): Customers
    {
$model->update($data);

return $model->fresh();
    }

    public function delete(Customers $model): void
    {
$model->delete();
    }
}