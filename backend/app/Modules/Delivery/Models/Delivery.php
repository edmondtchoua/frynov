<?php

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use HasUuids, SoftDeletes;

    const STATUS_PENDING    = 'pending';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED  = 'delivered';
    const STATUS_FAILED     = 'failed';

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DISPATCHED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'tenant_id',
        'order_id',
        'status',
        'address',
        'carrier',
        'tracking_number',
        'notes',
        'dispatched_at',
        'delivered_at',
        'failed_at',
        'failed_reason',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'address'       => 'array',
            'dispatched_at' => 'datetime',
            'delivered_at'  => 'datetime',
            'failed_at'     => 'datetime',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Orders\Models\Order::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ── Status helpers ─────────────────────────────────────────────────────────

    public function isPending(): bool    { return $this->status === self::STATUS_PENDING; }
    public function isDispatched(): bool { return $this->status === self::STATUS_DISPATCHED; }
    public function isInTransit(): bool  { return $this->status === self::STATUS_IN_TRANSIT; }
    public function isDelivered(): bool  { return $this->status === self::STATUS_DELIVERED; }
    public function isFailed(): bool     { return $this->status === self::STATUS_FAILED; }

    public function canBeDispatched(): bool { return $this->isPending(); }
    public function canBeDelivered(): bool  { return in_array($this->status, [self::STATUS_DISPATCHED, self::STATUS_IN_TRANSIT]); }
    public function canBeFailed(): bool     { return !$this->isDelivered(); }
}
