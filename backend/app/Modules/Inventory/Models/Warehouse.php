<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'type', 'address',
        'phone', 'email', 'currency', 'is_active', 'is_default',
        'sells_online', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'address'      => 'array',
            'is_active'    => 'boolean',
            'is_default'   => 'boolean',
            'sells_online' => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
