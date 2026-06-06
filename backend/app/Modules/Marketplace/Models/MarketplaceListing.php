<?php
namespace App\Modules\Marketplace\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceListing extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'warehouse_id',
        'platform', 'external_product_id', 'external_variant_id',
        'external_sku', 'external_url',
        'sync_status', 'last_synced_at', 'last_sync_error',
        'sync_retry_count', 'sync_error_count',
        'is_auto_close_enabled', 'is_auto_reopen_enabled',
        'close_threshold', 'is_price_sync_enabled',
        'platform_config',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at'         => 'datetime',
            'last_sync_error'        => 'array',
            'platform_config'        => 'array',
            'is_auto_close_enabled'  => 'boolean',
            'is_auto_reopen_enabled' => 'boolean',
            'is_price_sync_enabled'  => 'boolean',
            'close_threshold'        => 'integer',
            'sync_retry_count'       => 'integer',
            'sync_error_count'       => 'integer',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
    public function alerts(): HasMany    { return $this->hasMany(MarketplaceSyncAlert::class, 'listing_id'); }

    public function isActive(): bool    { return $this->sync_status === 'active'; }
    public function isClosed(): bool    { return $this->sync_status === 'closed'; }
    public function hasError(): bool    { return $this->sync_status === 'error'; }
    public function needsAction(): bool { return $this->sync_status === 'pending_manual'; }
}
