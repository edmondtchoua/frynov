<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Shared\Traits\HasTenant;

class StockAdjustmentRequest extends Model
{
    use HasTenant, HasUuids;

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXECUTED = 'executed';

    const REASONS = [
        'loss',
        'count',
        'manual',
        'damage',
        'theft',
        'correction',
        'donation',
        'expiry',
    ];

    protected $fillable = [
        'tenant_id',
        'stock_id',
        'product_id',
        'variant_id',
        'quantity_before',
        'quantity_requested',
        'delta',
        'value_cents',
        'reason',
        'note',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'executed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before'    => 'integer',
            'quantity_requested' => 'integer',
            'delta'              => 'integer',
            'value_cents'        => 'integer',
            'reviewed_at'        => 'datetime',
            'executed_at'        => 'datetime',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\Product::class);
    }

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }
    public function isExecuted(): bool { return $this->status === self::STATUS_EXECUTED; }
}
