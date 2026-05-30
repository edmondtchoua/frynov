<?php

namespace App\Modules\Catalog\Repositories;

use App\Modules\Catalog\Models\Catalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentCatalogRepository implements CatalogRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return Catalog::query()
    ->where('tenant_id', $tenantId)
    ->paginate(20);
    }

    public function findById(string $id, string $tenantId): ?Catalog
    {
return Catalog::query()
    ->where('tenant_id', $tenantId)
    ->find($id);
    }

    public function create(array $data): Catalog
    {
return Catalog::create($data);
    }

    public function update(Catalog $model, array $data): Catalog
    {
$model->update($data);

return $model->fresh();
    }

    public function delete(Catalog $model): void
    {
$model->delete();
    }
}