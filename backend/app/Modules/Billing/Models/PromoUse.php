<?php

namespace App\Modules\Billing\Models;

use App\Modules\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoUse extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'tenant_id',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
