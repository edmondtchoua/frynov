<?php
namespace App\Modules\Inventory\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySnapshot extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id','warehouse_id','product_id','variant_id',
        'period_type','period_start','period_end',
        'closing_quantity','closing_reserved_quantity',
        'closing_unit_cost_cents','closing_total_value_cents',
        'total_in','total_out','total_adjusted',
        'total_transferred_in','total_transferred_out',
        'low_stock_alert_count','stockout_days',
        'is_finalized','generated_at',
    ];

    protected function casts(): array {
        return [
            'period_start'  => 'date',
            'period_end'    => 'date',
            'is_finalized'  => 'boolean',
            'generated_at'  => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
}
