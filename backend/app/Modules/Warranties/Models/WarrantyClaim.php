<?php

namespace App\Modules\Warranties\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-5F — réclamation SAV rattachée à un contrat de garantie.
 */
class WarrantyClaim extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    public const STATUS_OPEN      = 'open';
    public const STATUS_IN_REPAIR = 'in_repair';
    public const STATUS_RESOLVED  = 'resolved';
    public const STATUS_REPLACED  = 'replaced';
    public const STATUS_REJECTED  = 'rejected';

    /** Statuts terminaux : aucune transition n'en sort. */
    public const TERMINAL = [self::STATUS_RESOLVED, self::STATUS_REPLACED, self::STATUS_REJECTED];

    public const REASONS = ['defect', 'breakage', 'malfunction', 'other'];

    protected $fillable = [
        'tenant_id', 'warranty_contract_id', 'customer_id', 'inventory_unit_id',
        'reason', 'description', 'diagnostic',
        'status', 'resolution', 'resolution_note', 'out_of_warranty',
        'opened_at', 'resolved_at', 'opened_by', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'out_of_warranty' => 'boolean',
            'opened_at'       => 'datetime',
            'resolved_at'     => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(WarrantyContract::class, 'warranty_contract_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'                   => $this->id,
            'warranty_contract_id' => $this->warranty_contract_id,
            'customer_id'          => $this->customer_id,
            'inventory_unit_id'    => $this->inventory_unit_id,
            'reason'               => $this->reason,
            'description'          => $this->description,
            'diagnostic'           => $this->diagnostic,
            'status'               => $this->status,
            'resolution'           => $this->resolution,
            'resolution_note'      => $this->resolution_note,
            'out_of_warranty'      => $this->out_of_warranty,
            'opened_at'            => $this->opened_at?->toISOString(),
            'resolved_at'          => $this->resolved_at?->toISOString(),
        ];
    }
}
