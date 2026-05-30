<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
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

    public function isDraft(): bool     { return $this->status === self::STATUS_DRAFT; }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
    public function isFulfilled(): bool { return $this->status === self::STATUS_FULFILLED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }

    public function canBeConfirmed(): bool  { return $this->isDraft(); }
    public function canBeFulfilled(): bool  { return $this->isConfirmed(); }
    public function canBeCancelled(): bool  { return $this->isDraft() || $this->isConfirmed(); }
}
