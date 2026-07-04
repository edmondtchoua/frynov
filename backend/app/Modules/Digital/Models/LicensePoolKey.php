<?php

namespace App\Modules\Digital\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-6E — clé de licence éditeur importée, consommée FIFO à la vente.
 */
class LicensePoolKey extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ASSIGNED  = 'assigned';

    protected $table = 'license_pool_keys';

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'license_key',
        'status', 'entitlement_id', 'imported_by', 'assigned_at',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }
}
