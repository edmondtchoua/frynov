<?php

namespace App\Modules\Catalog\Repositories;

use App\Modules\Catalog\Models\Catalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CatalogRepositoryInterface
{
    public function all(string $tenantId, array $filters = []): LengthAwarePaginator;

    public function findById(string $id, string $tenantId): ?Catalog;

    public function create(array $data): Catalog;

    public function update(Catalog $model, array $data): Catalog;

    public function delete(Catalog $model): void;
}