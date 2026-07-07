<?php

namespace App\Modules\Billing\Models;

use App\Modules\Tenants\Models\Tenant;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2b — notification in-app d'abonnement (cloche). Historisée, marquée lue/non-lue, isolée par tenant.
 */
class SubscriptionNotification extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'change_request_id', 'type', 'severity', 'title', 'body', 'is_read', 'read_at',
    ];

    protected function casts(): array
    {
        return ['is_read' => 'boolean', 'read_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Payload aligné sur la forme attendue par le centre de notifications du front. */
    public function toApiArray(): array
    {
        return [
            'id'              => $this->id,
            'severity'        => $this->severity,
            'type'            => $this->type,
            'message'         => $this->title,
            'context'         => ['body' => $this->body, 'change_request_id' => $this->change_request_id],
            'is_read'         => $this->is_read,
            'requires_action' => false,
            'source'          => 'subscription',
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
