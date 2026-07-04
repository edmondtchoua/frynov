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

    // RC-6F — unités de durée (duration_months porte la VALEUR dans l'unité choisie ; nom conservé
    // pour compat de colonne).
    public const UNIT_DAY   = 'day';
    public const UNIT_MONTH = 'month';
    public const UNIT_YEAR  = 'year';
    public const UNITS = [self::UNIT_DAY, self::UNIT_MONTH, self::UNIT_YEAR];

    protected $fillable = [
        'tenant_id', 'name', 'duration_months', 'duration_unit', 'coverage', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'is_active'       => 'boolean',
        ];
    }

    /** RC-6F — échéance à partir d'un début, selon la valeur ET l'unité de la politique. */
    public function endsAtFrom(\Illuminate\Support\Carbon $startsAt): \Illuminate\Support\Carbon
    {
        return match ($this->duration_unit ?? self::UNIT_MONTH) {
            self::UNIT_DAY  => $startsAt->copy()->addDays($this->duration_months),
            self::UNIT_YEAR => $startsAt->copy()->addYears($this->duration_months),
            default         => $startsAt->copy()->addMonths($this->duration_months),
        };
    }

    public function toApiArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'duration_months' => $this->duration_months,
            'duration_unit'   => $this->duration_unit ?? self::UNIT_MONTH,
            'coverage'        => $this->coverage,
            'is_active'       => $this->is_active,
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
