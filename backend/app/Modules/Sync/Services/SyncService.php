<?php

namespace App\Modules\Sync\Services;

use App\Modules\Sync\Models\Sync;
use App\Modules\Sync\Repositories\SyncRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SyncService
{
    public function __construct(
private readonly SyncRepositoryInterface $repository,
    ) {}

    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return $this->repository->all($tenantId, $filters);
    }

    public function findOrFail(string $id, string $tenantId): Sync
    {
$model = $this->repository->findById($id, $tenantId);

if (! $model) {
    abort(404);
}

return $model;
    }

    public function create(array $data, string $tenantId): Sync
    {
return $this->repository->create([
    ...$data,
    'tenant_id' => $tenantId,
]);
    }

    public function update(string $id, array $data, string $tenantId): Sync
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