<?php
namespace App\Modules\Inventory\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Inventory\Models\Warehouse;

class StockTransfer extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id','number','source_warehouse_id','destination_warehouse_id',
        'status','notes','metadata',
        'requested_by','shipped_by','received_by','dispute_resolved_by',
        'shipped_at','expected_at','received_at','completed_at',
        'disputed_at','dispute_resolved_at',
        'dispute_reason','dispute_resolution',
    ];

    protected function casts(): array
    {
        return [
            'metadata'             => 'array',
            'shipped_at'           => 'datetime',
            'expected_at'          => 'datetime',
            'received_at'          => 'datetime',
            'completed_at'         => 'datetime',
            'disputed_at'          => 'datetime',
            'dispute_resolved_at'  => 'datetime',
        ];
    }

    public function lines(): HasMany       { return $this->hasMany(StockTransferLine::class, 'transfer_id'); }
    public function sourceWarehouse(): BelongsTo      { return $this->belongsTo(Warehouse::class, 'source_warehouse_id'); }
    public function destinationWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'destination_warehouse_id'); }

    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isInTransit(): bool { return $this->status === 'in_transit'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function hasDispute(): bool  { return in_array($this->status, ['partial','disputed']); }
}
