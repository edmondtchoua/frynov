<?php

namespace App\Modules\Inventory\Repositories;

use App\Modules\Inventory\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventoryRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator;

    public function findById(string $id, string $tenantId): ?Inventory;

    public function create(array $data): Inventory;

    public function update(Inventory $model, array $data): Inventory;

    public function delete(Inventory $model): void;
}