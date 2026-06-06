<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Shared\Traits\HasTenant;

class Order extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'cash_register_session_id',   // set for POS sales (null otherwise)
        'customer_id',
        'number',
        'status',
        'total_amount',
        'currency',
        'note',
        'performed_by',
        'fulfilled_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Customers\Models\Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(\App\Modules\Payments\Models\Payment::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(\App\Modules\Delivery\Models\Delivery::class);
    }

    public function isDraft(): bool     { return $this->status === self::STATUS_DRAFT; }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
    public function isFulfilled(): bool { return $this->status === self::STATUS_FULFILLED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }

    public function canBeConfirmed(): bool  { return $this->isDraft(); }
    public function canBeFulfilled(): bool  { return $this->isConfirmed(); }
    public function canBeCancelled(): bool  { return $this->isDraft() || $this->isConfirmed(); }
}
