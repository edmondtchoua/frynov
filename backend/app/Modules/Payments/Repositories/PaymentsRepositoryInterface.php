<?php

namespace App\Modules\Payments\Repositories;

use App\Modules\Payments\Models\Payments;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentsRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator;

    public function findById(string $id, string $tenantId): ?Payments;

    public function create(array $data): Payments;

    public function update(Payments $model, array $data): Payments;

    public function delete(Payments $model): void;
}