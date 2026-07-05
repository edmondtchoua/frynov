<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RC-25 — écriture comptable (partie double). `posted` = immuable : toute correction passe par
 * l'extourne (`reversal_of_id`/`reversed_by_id`). La traçabilité (source_*, rule_code,
 * inputs_snapshot) rend chaque écriture automatique rejouable et explicable.
 */
class Entry extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_POSTED   = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $table = 'accounting_entries';

    protected $fillable = [
        'tenant_id', 'journal_id', 'period_id', 'fiscal_year_id', 'number',
        'entry_date', 'label', 'currency', 'status',
        'source_type', 'source_id', 'rule_code', 'inputs_snapshot',
        'reversal_of_id', 'reversed_by_id', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date'      => 'date:Y-m-d',
            'inputs_snapshot' => 'array',
            'posted_at'       => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EntryLine::class, 'entry_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function totalDebit(): int
    {
        return (int) $this->lines->sum('debit_minor');
    }

    public function totalCredit(): int
    {
        return (int) $this->lines->sum('credit_minor');
    }
}
