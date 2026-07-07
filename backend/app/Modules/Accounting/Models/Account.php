<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RC-23 — compte du plan comptable SYSCOHADA d'un tenant.
 *
 * `is_system` marque les comptes requis par le moteur d'imputation (571 caisse, 411 clients,
 * 701 ventes…) : désactivables mais jamais supprimés. Les comptes auxiliaires (tiers) pointent
 * leur entité via (auxiliary_type, auxiliary_of_id).
 */
class Account extends Model
{
    use HasTenant, HasUuids;

    public const KIND_ASSET     = 'asset';
    public const KIND_LIABILITY = 'liability';
    public const KIND_EQUITY    = 'equity';
    public const KIND_REVENUE   = 'revenue';
    public const KIND_EXPENSE   = 'expense';

    public const KINDS = [
        self::KIND_ASSET, self::KIND_LIABILITY, self::KIND_EQUITY,
        self::KIND_REVENUE, self::KIND_EXPENSE,
    ];

    protected $table = 'accounting_accounts';

    protected $fillable = [
        'tenant_id', 'class_code', 'code', 'name', 'parent_id', 'kind',
        'is_auxiliary', 'auxiliary_type', 'auxiliary_of_id',
        'is_system', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'class_code'   => 'integer',
            'is_auxiliary' => 'boolean',
            'is_system'    => 'boolean',
            'is_active'    => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
