<?php

namespace App\Modules\Delivery\Repositories;

use App\Modules\Delivery\Models\Delivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentDeliveryRepository implements DeliveryRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return Delivery::query()
    ->where('tenant_id', $tenantId)
    ->paginate(20);
    }

    public function findById(string $id, string $tenantId): ?Delivery
    {
return Delivery::query()
    ->where('tenant_id', $tenantId)
    ->find($id);
    }

    public function create(array $data): Delivery
    {
return Delivery::create($data);
    }

    public function update(Delivery $model, array $data): Delivery
    {
$model->update($data);

return $model->fresh();
    }

    public function delete(Delivery $model): void
    {
$model->delete();
    }
}