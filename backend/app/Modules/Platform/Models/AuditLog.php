<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null; // immutable — no updated_at

    protected $fillable = [
        'user_id',
        'tenant_id',
        'actor_role',
        'action',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'notes',
        'risk_level',
        'integrity_hash',
    ];

    // ── Immutability enforcement ───────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // ── Compute integrity hash on creation (blockchain-lite chaining) ───
        static::creating(function (AuditLog $log) {
            // Chain: each entry hashes its own content + the previous entry's hash
            $prev  = static::latest('created_at')->value('integrity_hash') ?? 'GENESIS';
            $data  = [
                'action'       => $log->action,
                'ip_address'   => $log->ip_address,
                'new_values'   => $log->new_values,
                'old_values'   => $log->old_values,
                'prev'         => $prev,
                'subject_id'   => $log->subject_id,
                'subject_type' => $log->subject_type,
                'tenant_id'    => $log->tenant_id,
                'ts'           => now()->toISOString(),
                'user_id'      => $log->user_id,
            ];
            // Keys already sorted alphabetically above — ensures deterministic hash
            // (PHP has no JSON_SORT_KEYS constant; manual sort is equivalent)
            $payload = json_encode($data, \JSON_UNESCAPED_UNICODE);

            $log->integrity_hash = hash_hmac(
                'sha256',
                $payload,
                config('app.key', 'fallback-key'),
            );
        });

        // ── Block any UPDATE on audit entries ─────────────────────────────
        static::updating(function (AuditLog $log) {
            throw new \DomainException(
                'AuditLog entries are immutable. Attempted to modify ID: ' . $log->id
            );
        });

        // ── Block any DELETE on audit entries ─────────────────────────────
        static::deleting(function (AuditLog $log) {
            throw new \DomainException(
                'AuditLog entries cannot be deleted. Attempted to delete ID: ' . $log->id
            );
        });
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
