<?php

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Models\Customers;
use App\Modules\Customers\Repositories\CustomersRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomersService
{
    public function __construct(
private readonly CustomersRepositoryInterface $repository,
    ) {}

    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return $this->repository->all($tenantId, $filters);
    }

    public function findOrFail(string $id, string $tenantId): Customers
    {
$model = $this->repository->findById($id, $tenantId);

if (! $model) {
    abort(404);
}

return $model;
    }

    public function create(array $data, string $tenantId): Customers
    {
return $this->repository->create([
    ...$data,
    'tenant_id' => $tenantId,
]);
    }

    public function update(string $id, array $data, string $tenantId): Customers
    {
$model = $this->findOrFail($id, $tenantId);

return $this->repository->update($model, $data);
    }

    public function delete(string $id, string $tenantId): void
    {
$model = $this->findOrFail($id, $tenantId);
$this->repository->delete($model);
    }
}