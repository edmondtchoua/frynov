<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\Payments;
use App\Modules\Payments\Repositories\PaymentsRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentsService
{
    public function __construct(
private readonly PaymentsRepositoryInterface $repository,
    ) {}

    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return $this->repository->all($tenantId, $filters);
    }

    public function findOrFail(string $id, string $tenantId): Payments
    {
$model = $this->repository->findById($id, $tenantId);

if (! $model) {
    abort(404);
}

return $model;
    }

    public function create(array $data, string $tenantId): Payments
    {
return $this->repository->create([
    ...$data,
    'tenant_id' => $tenantId,
]);
    }

    public function update(string $id, array $data, string $tenantId): Payments
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