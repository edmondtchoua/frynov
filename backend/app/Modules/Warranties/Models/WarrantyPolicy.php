<?php

namespace App\Modules\Warranties\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-5D — politique de garantie réutilisable (durée + couverture), attachable à un produit.
 */
class WarrantyPolicy extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'duration_months', 'coverage', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'is_active'       => 'boolean',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'duration_months' => $this->duration_months,
            'coverage'        => $this->coverage,
            'is_active'       => $this->is_active,
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
