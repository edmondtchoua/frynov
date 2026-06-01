<?php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Shared\Traits\HasTenant;

class Payment extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    public const METHOD_CASH         = 'cash';
    public const METHOD_MOBILE_MONEY = 'mobile_money';
    public const METHOD_CARD         = 'card';
    public const METHOD_TRANSFER     = 'transfer';
    public const METHOD_CHEQUE       = 'cheque';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_MOBILE_MONEY,
        self::METHOD_CARD,
        self::METHOD_TRANSFER,
        self::METHOD_CHEQUE,
    ];

    protected $fillable = [
        'tenant_id',
        'order_id',
        'amount_cents',
        'currency',
        'method',
        'reference',
        'note',
        'paid_at',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at'      => 'datetime',
        ];
    }

    // â”€â”€ Relations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Orders\Models\Order::class);
    }
}
