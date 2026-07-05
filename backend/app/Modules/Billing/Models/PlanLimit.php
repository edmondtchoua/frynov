<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanLimit extends Model
{
    use HasUuids;

    protected $fillable = [
        'plan_id',
        'max_products',
        'max_monthly_orders',
        'max_customers',
        'max_branches',
        'max_warehouses',
        'max_imports_per_month',
        'max_api_calls_per_month',
        'storage_mb',
    ];

    protected function casts(): array
    {
        return [
            'max_products' => 'integer',
            'max_monthly_orders' => 'integer',
            'max_customers' => 'integer',
            'max_branches' => 'integer',
            'max_warehouses' => 'integer',
            'max_imports_per_month' => 'integer',
            'max_api_calls_per_month' => 'integer',
            'storage_mb' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
