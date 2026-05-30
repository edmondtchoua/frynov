<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Catalog;
use App\Modules\Catalog\Repositories\CatalogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatalogService
{
    public function __construct(
private readonly CatalogRepositoryInterface $repository,
    ) {}

    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return $this->repository->all($tenantId, $filters);
    }

    public function findOrFail(string $id, string $tenantId): Catalog
    {
$model = $this->repository->findById($id, $tenantId);

if (! $model) {
    abort(404);
}

return $model;
    }

    public function create(array $data, string $tenantId): Catalog
    {
return $this->repository->create([
    ...$data,
    'tenant_id' => $tenantId,
]);
    }

    public function update(string $id, array $data, string $tenantId): Catalog
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