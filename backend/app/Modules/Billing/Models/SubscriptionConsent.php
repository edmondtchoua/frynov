<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\Tenants\Models\Tenant;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consentement d'abonnement (P2, Phase 7) — enregistrement immuable d'un accord. Deux origines :
 *  - AUTOMATIQUE : l'utilisateur coche une case obligatoire (source `platform`) ;
 *  - MANUEL : un admin trace un accord reçu hors plateforme (source `admin`/`email`/`phone`/`document`).
 *
 * Immuable après création (aucune méthode d'update exposée ; consultable dans l'historique).
 */
class SubscriptionConsent extends Model
{
    use HasTenant, HasUuids;

    public const SOURCE_PLATFORM = 'platform';
    public const SOURCE_ADMIN    = 'admin';
    public const SOURCE_EMAIL    = 'email';
    public const SOURCE_PHONE    = 'phone';
    public const SOURCE_DOCUMENT = 'document';
    public const SOURCE_OTHER    = 'other';

    public const ACTION_PLAN_CHANGE = 'plan_change';

    protected $fillable = [
        'tenant_id', 'user_id', 'action_type', 'consent_text', 'consent_version',
        'accepted_at', 'ip_address', 'user_agent', 'source',
        'related_entity_type', 'related_entity_id', 'comment', 'proof_path', 'created_by',
    ];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,mixed> */
    public function toApiArray(): array
    {
        return [
            'id'              => $this->id,
            'action_type'     => $this->action_type,
            'consent_version' => $this->consent_version,
            'accepted_at'     => $this->accepted_at,
            'source'          => $this->source,
            'related_entity'  => $this->related_entity_type ? [
                'type' => $this->related_entity_type,
                'id'   => $this->related_entity_id,
            ] : null,
        ];
    }
}
