<?php

namespace App\Modules\Inventory\Repositories;

use App\Modules\Inventory\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentInventoryRepository implements InventoryRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return Inventory::query()
    ->where('tenant_id', $tenantId)
    ->paginate(20);
    }

    public function findById(string $id, string $tenantId): ?Inventory
    {
return Inventory::query()
    ->where('tenant_id', $tenantId)
    ->find($id);
    }

    public function create(array $data): Inventory
    {
return Inventory::create($data);
    }

    public function update(Inventory $model, array $data): Inventory
    {
$model->update($data);

return $model->fresh();
    }

    public function delete(Inventory $model): void
    {
$model->delete();
    }
}