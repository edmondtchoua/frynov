<?php

namespace App\Modules\Payments\Repositories;

use App\Modules\Payments\Models\Payments;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPaymentsRepository implements PaymentsRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return Payments::query()
    ->where('tenant_id', $tenantId)
    ->paginate(20);
    }

    public function findById(string $id, string $tenantId): ?Payments
    {
return Payments::query()
    ->where('tenant_id', $tenantId)
    ->find($id);
    }

    public function create(array $data): Payments
    {
return Payments::create($data);
    }

    public function update(Payments $model, array $data): Payments
    {
$model->update($data);

return $model->fresh();
    }

    public function delete(Payments $model): void
    {
$model->delete();
    }
}