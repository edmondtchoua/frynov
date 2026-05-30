<?php

namespace App\Modules\Sync\Repositories;

use App\Modules\Sync\Models\Sync;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentSyncRepository implements SyncRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator
    {
return Sync::query()
    ->where('tenant_id', $tenantId)
    ->paginate(20);
    }

    public function findById(string $id, string $tenantId): ?Sync
    {
return Sync::query()
    ->where('tenant_id', $tenantId)
    ->find($id);
    }

    public function create(array $data): Sync
    {
return Sync::create($data);
    }

    public function update(Sync $model, array $data): Sync
    {
$model->update($data);

return $model->fresh();
    }

    public function delete(Sync $model): void
    {
$model->delete();
    }
}