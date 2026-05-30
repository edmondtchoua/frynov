<?php

namespace App\Modules\Orders\Repositories;

use App\Modules\Orders\Models\Orders;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrdersRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator;

    public function findById(string $id, string $tenantId): ?Orders;

    public function create(array $data): Orders;

    public function update(Orders $model, array $data): Orders;

    public function delete(Orders $model): void;
}