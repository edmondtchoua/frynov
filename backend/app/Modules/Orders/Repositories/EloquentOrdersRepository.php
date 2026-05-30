<?php

namespace App\Modules\Orders\Repositories;

use App\Modules\Orders\Models\Orders;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentOrdersRepository implements OrdersRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return Orders::query()
    ->where('tenant_id', $tenantId)
    ->paginate(20);
    }

    public function findById(string $id, string $tenantId): ?Orders
    {
return Orders::query()
    ->where('tenant_id', $tenantId)
    ->find($id);
    }

    public function create(array $data): Orders
    {
return Orders::create($data);
    }

    public function update(Orders $model, array $data): Orders
    {
$model->update($data);

return $model->fresh();
    }

    public function delete(Orders $model): void
    {
$model->delete();
    }
}