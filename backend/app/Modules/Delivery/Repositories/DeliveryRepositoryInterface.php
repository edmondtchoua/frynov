<?php

namespace App\Modules\Delivery\Repositories;

use App\Modules\Delivery\Models\Delivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeliveryRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator;

    public function findById(string $id, string $tenantId): ?Delivery;

    public function create(array $data): Delivery;

    public function update(Delivery $model, array $data): Delivery;

    public function delete(Delivery $model): void;
}