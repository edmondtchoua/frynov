<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-23 — paramétrage comptable du tenant (1 ligne / tenant).
 *
 * `default_accounts` mappe les références symboliques du moteur d'imputation vers des CODES de
 * comptes ('@cash' => '571', '@sales' => '701', '@customers' => '411', '@mobile_money' => '585'…) :
 * les templates d'écritures restent portables, le tenant reste libre de son plan.
 */
class AccountingSettings extends Model
{
    use HasTenant, HasUuids;

    protected $table = 'tenant_accounting_settings';

    protected $fillable = [
        'tenant_id', 'country', 'currency', 'fiscal_year_start_month',
        'numbering_rules', 'default_accounts', 'auto_post', 'locked_until',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
            'numbering_rules'         => 'array',
            'default_accounts'        => 'array',
            'auto_post'               => 'boolean',
            'locked_until'            => 'date',
        ];
    }
}
