<?php

namespace App\Modules\ImportExport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportSession extends Model
{
    use HasUuids, SoftDeletes;

    // ── Status constants ──────────────────────────────────────────────────
    const STATUS_DRAFT             = 'draft';
    const STATUS_ANALYZING         = 'analyzing';
    const STATUS_ANALYZED          = 'analyzed';
    const STATUS_AWAITING_APPROVAL = 'awaiting_approval';
    const STATUS_IMPORTING         = 'importing';
    const STATUS_COMPLETED         = 'completed';
    const STATUS_PARTIAL           = 'partial';
    const STATUS_FAILED            = 'failed';
    const STATUS_CANCELLED         = 'cancelled';

    // ── Type constants ────────────────────────────────────────────────────
    const TYPE_PRODUCTS  = 'products';
    const TYPE_CUSTOMERS = 'customers';
    const TYPE_SUPPLIERS = 'suppliers';

    // ── Mode constants ────────────────────────────────────────────────────
    const MODE_CREATE_ONLY   = 'create_only';
    const MODE_UPDATE_ONLY   = 'update_only';
    const MODE_CREATE_UPDATE = 'create_update';
    const MODE_SIMULATE      = 'simulate';

    protected $fillable = [
        'tenant_id',
        'performed_by',
        'type',
        'status',
        'mode',
        'original_filename',
        'stored_path',
        'total_rows',
        'valid_rows',
        'error_rows',
        'warning_rows',
        'imported_rows',
        'skipped_rows',
        'column_mapping',
        'summary',
        'error_message',
        'analyzed_at',
        'approved_at',
        'completed_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'summary'        => 'array',
            'analyzed_at'    => 'datetime',
            'approved_at'    => 'datetime',
            'completed_at'   => 'datetime',
        ];
    }

    // ── Status helpers ────────────────────────────────────────────────────

    public function isDraft(): bool            { return $this->status === self::STATUS_DRAFT; }
    public function isAnalyzing(): bool        { return $this->status === self::STATUS_ANALYZING; }
    public function isAnalyzed(): bool         { return $this->status === self::STATUS_ANALYZED; }
    public function isAwaitingApproval(): bool { return $this->status === self::STATUS_AWAITING_APPROVAL; }
    public function isImporting(): bool        { return $this->status === self::STATUS_IMPORTING; }
    public function isCompleted(): bool        { return $this->status === self::STATUS_COMPLETED; }
    public function isPartial(): bool          { return $this->status === self::STATUS_PARTIAL; }
    public function isFailed(): bool           { return $this->status === self::STATUS_FAILED; }
    public function isCancelled(): bool        { return $this->status === self::STATUS_CANCELLED; }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_PARTIAL,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_AWAITING_APPROVAL;
    }

    public function canBeCancelled(): bool
    {
        return !$this->isTerminal();
    }

    public function canUpdateMapping(): bool
    {
        return in_array($this->status, [
            self::STATUS_ANALYZED,
            self::STATUS_AWAITING_APPROVAL,
        ]);
    }

    public function isSimulate(): bool
    {
        return $this->mode === self::MODE_SIMULATE;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'session_id');
    }

    public function validRows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'session_id')->where('status', ImportRow::STATUS_VALID);
    }

    public function errorRows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'session_id')->where('status', ImportRow::STATUS_ERROR);
    }
}
