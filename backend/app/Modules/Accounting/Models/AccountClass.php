<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-23 — classe SYSCOHADA (1–9). Référentiel GLOBAL seedé, identique pour tous les tenants.
 */
class AccountClass extends Model
{
    use HasUuids;

    public const TYPE_BALANCE = 'bilan';    // classes 1–5
    public const TYPE_PNL     = 'gestion';  // classes 6–8 (9 = analytique/engagements)

    protected $table = 'accounting_account_classes';

    protected $fillable = ['code', 'name', 'type'];

    protected function casts(): array
    {
        return ['code' => 'integer'];
    }
}
