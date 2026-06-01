<?php

namespace App\Modules\Payments\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Record a payment and return it.
     * Automatically links to an order when order_id is provided.
     */
    public function record(array $data, string $tenantId, string $userId): Payment
    {
        return DB::transaction(function () use ($data, $tenantId, $userId) {
            // Sprint 11 security fix: cap amount_cents to remaining order balance.
            // ORDER IS LOCKED via SELECT FOR UPDATE to prevent concurrent double-payment
            // race conditions — the balance check and Payment::create() are now atomic.
            if (!empty($data['order_id'])) {
                $order = \App\Modules\Orders\Models\Order::where('tenant_id', $tenantId)
                             ->lockForUpdate()
                             ->findOrFail($data['order_id']);

                $paid      = $this->balance($order);
                $remaining = max(0, $order->total_amount - $paid);

                // Allow a 1% tolerance for rounding/fees (configurable)
                $tolerance = (int) ceil($order->total_amount * 0.01);

                if ($data['amount_cents'] > $remaining + $tolerance) {
                    throw new \Illuminate\Validation\ValidationException(
                        \Illuminate\Validation\Validator::make([], []),
                        response()->json(['message' => 'Montant supérieur au solde restant de l\'ordre (' . $remaining . ' centimes).'], 422)
                    );
                }
            }

            $payment = Payment::create([
                'tenant_id'       => $tenantId,
                'order_id'        => $data['order_id']        ?? null,
                'amount_cents'    => $data['amount_cents'],
                'currency'        => $data['currency']        ?? 'EUR',
                'method'          => $data['method'],
                'reference'       => $data['reference']       ?? null,
                'note'            => $data['note']             ?? null,
                'paid_at'         => $data['paid_at']          ?? now(),
                'performed_by'    => $userId,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            try {
                app(\App\Modules\Platform\Services\AuditService::class)->log(
                    'payment.recorded',
                    $tenantId,
                    $userId,
                    $payment,
                    [],
                    ['amount_cents' => $payment->amount_cents, 'method' => $payment->method, 'order_id' => $payment->order_id],
                    'low',
                );
            } catch (\Throwable) {}

            return $payment;
        });
    }

    /**
     * Total paid amount (in cents) for an order.
     */
    public function balance(Order $order): int
    {
        return (int) Payment::where('order_id', $order->id)
            ->whereNull('deleted_at')
            ->sum('amount_cents');
    }

    /**
     * True when the total paid ≥ order total_amount.
     */
    public function isFullyPaid(Order $order): bool
    {
        return $this->balance($order) >= $order->total_amount;
    }

    /**
     * All payments for a given order.
     */
    public function listForOrder(Order $order): Collection
    {
        return Payment::where('order_id', $order->id)
            ->latest('paid_at')
            ->get();
    }

    /**
     * Paginated list of payments for a tenant.
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = Payment::where('tenant_id', $tenantId)->with('order');

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }
        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('paid_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('paid_at', '<=', $filters['to']);
        }

        return $query->latest('paid_at')->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Find a payment for a tenant or fail.
     */
    public function findOrFail(string $id, string $tenantId): Payment
    {
        return Payment::where('tenant_id', $tenantId)->findOrFail($id);
    }

    /**
     * Void (soft-delete) a payment.
     */
    public function void(Payment $payment): void
    {
        $payment->delete();
    }
}
