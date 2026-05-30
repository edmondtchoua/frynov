<?php

namespace App\Modules\Customers\Repositories;

use App\Modules\Customers\Models\Customers;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomersRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator;

    public function findById(string $id, string $tenantId): ?Customers;

    public function create(array $data): Customers;

    public function update(Customers $model, array $data): Customers;

    public function delete(Customers $model): void;
}