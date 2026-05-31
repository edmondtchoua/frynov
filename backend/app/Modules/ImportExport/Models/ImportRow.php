<?php

namespace App\Modules\ImportExport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use HasUuids;

    const STATUS_PENDING  = 'pending';
    const STATUS_VALID    = 'valid';
    const STATUS_ERROR    = 'error';
    const STATUS_WARNING  = 'warning';
    const STATUS_IMPORTED = 'imported';
    const STATUS_SKIPPED  = 'skipped';

    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_SKIP   = 'skip';

    protected $fillable = [
        'session_id',
        'row_number',
        'status',
        'raw_data',
        'mapped_data',
        'errors',
        'warnings',
        'action',
        'entity_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_data'    => 'array',
            'mapped_data' => 'array',
            'errors'      => 'array',
            'warnings'    => 'array',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isValid(): bool   { return $this->status === self::STATUS_VALID; }
    public function isError(): bool   { return $this->status === self::STATUS_ERROR; }
    public function isWarning(): bool { return $this->status === self::STATUS_WARNING; }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class, 'session_id');
    }
}
