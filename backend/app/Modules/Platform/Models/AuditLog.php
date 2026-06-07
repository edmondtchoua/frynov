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
            // Pin created_at NOW so the value used in the hash is exactly the one persisted
            // (verify-chain recomputes from the stored created_at). Previously the hash used
            // now()->toISOString() while the row stored a different created_at → the chain
            // could never be verified as intact.
            $log->created_at = $log->created_at ?? now();

            // Chain: each entry hashes its own content + the previous entry's hash.
            $prev = static::latest('created_at')->value('integrity_hash') ?? 'GENESIS';
            $log->integrity_hash = static::integrityHashFor($log->integrityFields(), $prev);
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

    // ── Integrity chain (shared by the creating hook AND verify-chain) ──────────

    /** Hashable field set extracted from this row (excludes the chained prev hash). */
    public function integrityFields(): array
    {
        return [
            'action'       => $this->action,
            'ip_address'   => $this->ip_address,
            'new_values'   => $this->new_values,
            'old_values'   => $this->old_values,
            'subject_id'   => $this->subject_id,
            'subject_type' => $this->subject_type,
            'tenant_id'    => $this->tenant_id,
            // Second-precision unix timestamp — round-trips identically through the DB
            // (created_at is stored without microseconds), unlike an ISO string.
            'ts'           => $this->created_at?->getTimestamp(),
            'user_id'      => $this->user_id,
        ];
    }

    /** Deterministic HMAC of a field set chained onto the previous entry's hash. */
    public static function integrityHashFor(array $fields, string $prev): string
    {
        $payload = json_encode([
            'action'       => $fields['action'] ?? null,
            'ip_address'   => $fields['ip_address'] ?? null,
            'new_values'   => $fields['new_values'] ?? null,
            'old_values'   => $fields['old_values'] ?? null,
            'prev'         => $prev,
            'subject_id'   => $fields['subject_id'] ?? null,
            'subject_type' => $fields['subject_type'] ?? null,
            'tenant_id'    => $fields['tenant_id'] ?? null,
            'ts'           => $fields['ts'] ?? null,
            'user_id'      => $fields['user_id'] ?? null,
        ], \JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $payload, (string) config('app.key', 'fallback-key'));
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
