<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use App\Shared\Traits\HasTenant;

class ManualPayment extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'amount_cents',
        'currency',
        'payment_method',
        'proof_path',
        'proof_original_filename',
        'notes',
        'promo_code_used',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'reviewed_at'  => 'datetime',
        ];
    }

    // â”€â”€ Relations â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function hasProof(): bool
    {
        return (bool) $this->proof_path;
    }

    /**
     * Short-lived SIGNED URL to the authenticated proof download (admin review).
     * Never a public-disk URL — the proof lives on the private disk. Null if no proof.
     */
    public function proofUrl(): ?string
    {
        return $this->proof_path
            ? URL::temporarySignedRoute('admin.manual-payments.proof', now()->addMinutes(30), ['manualPayment' => $this->id])
            : null;
    }

    /** Tenant-facing payload — deliberately omits any proof URL (private document). */
    public function toApiArray(): array
    {
        return [
            'id'                     => $this->id,
            'tenant_id'              => $this->tenant_id,
            'tenant_name'            => $this->tenant?->name,
            'plan_code'              => $this->plan?->code,
            'plan_name'              => $this->plan?->name,
            'amount_cents'           => $this->amount_cents,
            'currency'               => $this->currency,
            'payment_method'         => $this->payment_method,
            'has_proof'              => $this->hasProof(),
            'proof_original_filename' => $this->proof_original_filename,
            'notes'                  => $this->notes,
            'promo_code_used'        => $this->promo_code_used,
            'status'                 => $this->status,
            'rejection_reason'       => $this->rejection_reason,
            'reviewed_at'            => $this->reviewed_at?->toISOString(),
            'created_at'             => $this->created_at?->toISOString(),
        ];
    }

    /** Admin-facing payload — adds the short-lived signed proof download URL. */
    public function toAdminArray(): array
    {
        return array_merge($this->toApiArray(), [
            'proof_url' => $this->proofUrl(),
        ]);
    }
}
