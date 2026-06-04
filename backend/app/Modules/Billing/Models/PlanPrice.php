<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    use HasUuids;

    protected $fillable = [
        'plan_id',
        'market_code',
        'country_code',
        'currency',
        'interval',
        'base_amount_minor',
        'included_users',
        'extra_user_amount_minor',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_amount_minor' => 'integer',
            'included_users' => 'integer',
            'extra_user_amount_minor' => 'integer',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
