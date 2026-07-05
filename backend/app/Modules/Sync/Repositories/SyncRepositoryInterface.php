<?php

namespace App\Modules\Sync\Repositories;

use App\Modules\Sync\Models\Sync;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SyncRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator;

    public function findById(string $id, string $tenantId): ?Sync;

    public function create(array $data): Sync;

    public function update(Sync $model, array $data): Sync;

    public function delete(Sync $model): void;
}