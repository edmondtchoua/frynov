<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Repositories\DeliveryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryService
{
    public function __construct(
private readonly DeliveryRepositoryInterface $repository,
    ) {}

    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return $this->repository->all($tenantId, $filters);
    }

    public function findOrFail(string $id, string $tenantId): Delivery
    {
$model = $this->repository->findById($id, $tenantId);

if (! $model) {
    abort(404);
}

return $model;
    }

    public function create(array $data, string $tenantId): Delivery
    {
return $this->repository->create([
    ...$data,
    'tenant_id' => $tenantId,
]);
    }

    public function update(string $id, array $data, string $tenantId): Delivery
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