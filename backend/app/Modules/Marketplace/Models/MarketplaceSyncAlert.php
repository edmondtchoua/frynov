<?php
namespace App\Modules\Marketplace\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSyncAlert extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'listing_id', 'severity', 'type',
        'message', 'context', 'is_read', 'requires_action', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context'         => 'array',
            'is_read'         => 'boolean',
            'requires_action' => 'boolean',
            'resolved_at'     => 'datetime',
        ];
    }

    public function listing(): BelongsTo { return $this->belongsTo(MarketplaceListing::class); }
}
