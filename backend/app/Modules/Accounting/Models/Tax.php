<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-23 — taxe d'un tenant. Taux en POINTS DE BASE (1800 = 18 %) pour rester en arithmétique
 * entière (convention projet : jamais de flottants sur les montants).
 */
class Tax extends Model
{
    use HasTenant, HasUuids;

    protected $table = 'accounting_taxes';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'rate_bp',
        'collected_account_id', 'deductible_account_id',
        'is_inclusive', 'country', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_bp'      => 'integer',
            'is_inclusive' => 'boolean',
            'is_active'    => 'boolean',
        ];
    }

    /** Montant de taxe (centimes) pour une base HT donnée — arrondi demi-supérieur déterministe. */
    public function amountFor(int $baseMinor): int
    {
        return intdiv($baseMinor * $this->rate_bp + 5000, 10000);
    }
}
