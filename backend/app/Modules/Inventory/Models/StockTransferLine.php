<?php
namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'transfer_id','product_id','variant_id',
        'quantity_requested','quantity_shipped','quantity_received',
        'quantity_discrepancy','discrepancy_reason','line_status',
        'unit_cost_cents_at_transfer',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested'          => 'integer',
            'quantity_shipped'            => 'integer',
            'quantity_received'           => 'integer',
            'quantity_discrepancy'        => 'integer',
            'unit_cost_cents_at_transfer' => 'integer',
        ];
    }

    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'transfer_id'); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo  { return $this->belongsTo(ProductVariant::class); }

    public function hasDiscrepancy(): bool { return $this->quantity_discrepancy !== 0; }
}
