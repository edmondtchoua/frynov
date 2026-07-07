<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** RC-23 — journal comptable d'un tenant (VT, AC, CA, BQ, OD, ST, AV, RG). */
class Journal extends Model
{
    use HasTenant, HasUuids;

    public const TYPE_SALES        = 'sales';
    public const TYPE_PURCHASES    = 'purchases';
    public const TYPE_CASH         = 'cash';
    public const TYPE_BANK         = 'bank';
    public const TYPE_MISC         = 'misc';
    public const TYPE_STOCK        = 'stock';
    public const TYPE_CREDIT_NOTES = 'credit_notes';
    public const TYPE_ADJUSTMENTS  = 'adjustments';

    public const TYPES = [
        self::TYPE_SALES, self::TYPE_PURCHASES, self::TYPE_CASH, self::TYPE_BANK,
        self::TYPE_MISC, self::TYPE_STOCK, self::TYPE_CREDIT_NOTES, self::TYPE_ADJUSTMENTS,
    ];

    protected $table = 'accounting_journals';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'type', 'sequence_prefix',
        'is_system', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }
}
