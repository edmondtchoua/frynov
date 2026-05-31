<?php

namespace App\Modules\Platform\Models;

use App\Modules\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModule extends Model
{
    use HasUuids;

    protected $table = 'tenant_modules';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_INACTIVE  = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TRIAL     = 'trial';

    protected $fillable = [
        'tenant_id',
        'module_id',
        'status',
        'activated_at',
        'expires_at',
        'activated_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expires_at'   => 'datetime',
            'metadata'     => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE || $this->status === self::STATUS_TRIAL;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ErpModule::class, 'module_id');
    }
}
