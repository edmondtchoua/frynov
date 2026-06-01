<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManualPaymentService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Submit a new manual payment request from a tenant.
     */
    public function submit(
        Tenant        $tenant,
        Plan          $plan,
        int           $amountCents,
        string        $currency,
        string        $paymentMethod,
        ?UploadedFile $proof,
        ?string       $notes,
        ?string       $promoCode = null,
    ): ManualPayment {
        $proofPath             = null;
        $proofOriginalFilename = null;

        if ($proof) {
            $proofOriginalFilename = $proof->getClientOriginalName();
            $proofPath             = $proof->store("payment-proofs/{$tenant->id}", 'public');
        }

        return ManualPayment::create([
            'tenant_id'              => $tenant->id,
            'plan_id'                => $plan->id,
            'amount_cents'           => $amountCents,
            'currency'               => $currency,
            'payment_method'         => $paymentMethod,
            'proof_path'             => $proofPath,
            'proof_original_filename' => $proofOriginalFilename,
            'notes'                  => $notes,
            'promo_code_used'        => $promoCode,
            'status'                 => ManualPayment::STATUS_PENDING,
        ]);
    }

    /**
     * Approve a pending manual payment and activate the subscription.
     */
    public function approve(ManualPayment $payment, User $admin): ManualPayment
    {
        return DB::transaction(function () use ($payment, $admin) {
            $payment->update([
                'status'      => ManualPayment::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // Activate the subscription with the new plan
            $this->subscriptions->changePlan(
                $payment->tenant,
                $payment->plan,
                $admin,
            );

            return $payment->fresh(['tenant', 'plan', 'reviewer']);
        });
    }

    /**
     * Reject a pending manual payment.
     */
    public function reject(ManualPayment $payment, User $admin, string $reason): ManualPayment
    {
        $payment->update([
            'status'           => ManualPayment::STATUS_REJECTED,
            'reviewed_by'      => $admin->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $payment->fresh(['tenant', 'plan']);
    }

    /**
     * List payments with optional status filter, paginated.
     */
    public function list(?string $status = null, int $perPage = 25)
    {
        // withoutTenantScope: admin operations must see ALL tenants' payments
        $query = ManualPayment::withoutTenantScope()
            ->with(['tenant:id,name,slug', 'plan:id,code,name'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * List all payments for a specific tenant.
     */
    public function forTenant(Tenant $tenant)
    {
        return ManualPayment::with('plan:id,code,name')
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->get();
    }
}
