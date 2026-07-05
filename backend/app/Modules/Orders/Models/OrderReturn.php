<?php

namespace App\Modules\Orders\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderReturn extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    const STATUS_PENDING    = 'pending';
    const STATUS_APPROVED   = 'approved';
    const STATUS_PROCESSING = 'processing';
    const STATUS_RESTOCKED  = 'restocked';
    const STATUS_REJECTED   = 'rejected';
    const STATUS_CANCELLED  = 'cancelled';

    const REASON_DEFECTIVE    = 'defective';
    const REASON_WRONG_ITEM   = 'wrong_item';
    const REASON_CHANGED_MIND = 'changed_mind';
    const REASON_DAMAGED      = 'damaged';
    const REASON_OTHER        = 'other';

    const RESOLUTION_REFUND       = 'refund';
    const RESOLUTION_EXCHANGE     = 'exchange';
    const RESOLUTION_STORE_CREDIT = 'store_credit';

    protected $fillable = [
        'tenant_id', 'order_id', 'number', 'status', 'reason',
        'customer_note', 'internal_note', 'resolution',
        'refund_amount_cents', 'refund_currency',
        'requested_by', 'approved_by', 'processed_by',
        'approved_at', 'restocked_at', 'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount_cents' => 'integer',
            'approved_at'         => 'datetime',
            'restocked_at'        => 'datetime',
            'rejected_at'         => 'datetime',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function lines(): HasMany   { return $this->hasMany(OrderReturnLine::class, 'return_id'); }

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool  { return $this->status === self::STATUS_APPROVED; }
    public function isCompleted(): bool { return in_array($this->status, [self::STATUS_RESTOCKED, self::STATUS_REJECTED, self::STATUS_CANCELLED]); }
}
