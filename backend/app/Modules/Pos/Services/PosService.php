<?php

namespace App\Modules\Pos\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Platform\Services\AuditService;
use App\Modules\Pos\Models\CashRegisterSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Point-of-sale orchestration.
 *
 * The POS does not own orders, stock or payments — it composes the existing
 * Orders and Payments services so a single "checkout" produces a fully paid,
 * stock-decremented sale, and ties it to a cash-register session for end-of-day
 * reconciliation. All amounts are integer centimes (×100).
 */
class PosService
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
        private AuditService $audit,
    ) {}

    /** The cashier's currently-open session, if any (tenant auto-scoped). */
    public function currentSession(string $tenantId, string $userId): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->where('opened_by', $userId)
            ->where('status', CashRegisterSession::STATUS_OPEN)
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open a new cash-register session.
     *
     * @throws ValidationException if the cashier already has an open session.
     */
    public function openSession(array $data, string $tenantId, string $userId): CashRegisterSession
    {
        if ($this->currentSession($tenantId, $userId)) {
            throw ValidationException::withMessages([
                'session' => ['Une session de caisse est déjà ouverte. Fermez-la avant d’en ouvrir une nouvelle.'],
            ]);
        }

        $session = CashRegisterSession::create([
            'tenant_id'           => $tenantId,
            'warehouse_id'        => $data['warehouse_id'] ?? null,
            'label'               => $data['label'] ?? null,
            'status'              => CashRegisterSession::STATUS_OPEN,
            'opening_float_cents' => $data['opening_float_cents'] ?? 0,
            'opened_by'           => $userId,
            'opened_at'           => now(),
        ]);

        $this->audit->log(
            action: 'pos.session.opened',
            tenantId: $tenantId,
            userId: $userId,
            subject: $session,
        );

        return $session;
    }

    /**
     * Ring up a sale: create → confirm → fulfill the order, record the payment,
     * and attach everything to the session. Atomic: any failure rolls the whole
     * sale back (no phantom stock movement or orphan payment).
     *
     * @param  array  $data  ['items' => [...], 'customer_id'? , 'method', 'reference'?, 'note'?]
     * @return array{order: Order, payment: Payment}
     *
     * @throws ValidationException                                       session not open / bad method
     * @throws \App\Modules\Inventory\Exceptions\InsufficientStockException out of stock
     */
    public function checkout(CashRegisterSession $session, array $data, string $tenantId, string $userId): array
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => ['La session de caisse est fermée.'],
            ]);
        }

        $method = $data['method'] ?? Payment::METHOD_CASH;
        if (! in_array($method, Payment::METHODS, true)) {
            throw ValidationException::withMessages([
                'method' => ['Moyen de paiement invalide.'],
            ]);
        }

        return DB::transaction(function () use ($session, $data, $tenantId, $userId, $method) {
            // 1. Create the order (prices resolved server-side from the catalog).
            $order = $this->orders->create([
                'items'       => $data['items'],
                'customer_id' => $data['customer_id'] ?? null,
                'note'        => $data['note'] ?? null,
            ], $tenantId, $userId);

            // 2. Tie it to this session BEFORE state changes so a rollback is clean.
            $order->cash_register_session_id = $session->id;
            $order->warehouse_id = $order->warehouse_id ?? $session->warehouse_id;
            $order->save();

            // 3. Confirm (reserves stock — throws if insufficient) then fulfill
            //    (consumes stock). A POS sale leaves the shop immediately.
            $order = $this->orders->confirm($order, $userId);
            $order = $this->orders->fulfill($order, $userId);

            // 4. Record the payment for the full total (capped to balance internally).
            $payment = $this->payments->record([
                'order_id'     => $order->id,
                'amount_cents' => $order->total_amount,
                'currency'     => $order->currency,
                'method'       => $method,
                'reference'    => $data['reference'] ?? null,
                'note'         => $data['note'] ?? null,
            ], $tenantId, $userId);

            // 5. Update the session's running tallies.
            $session->total_sales_cents += $order->total_amount;
            if ($method === Payment::METHOD_CASH) {
                $session->cash_sales_cents += $order->total_amount;
            }
            $session->sales_count += 1;
            $session->save();

            $this->audit->log(
                action: 'pos.sale',
                tenantId: $tenantId,
                userId: $userId,
                subject: $order,
            );

            return ['order' => $order->fresh('lines'), 'payment' => $payment];
        });
    }

    /**
     * Close the session: compute expected cash, store the counted amount and the
     * signed difference (écart). Idempotent guard: a closed session cannot reclose.
     *
     * @throws ValidationException if already closed.
     */
    public function closeSession(CashRegisterSession $session, array $data, string $tenantId, string $userId): CashRegisterSession
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => ['Cette session est déjà fermée.'],
            ]);
        }

        $expected = $session->expectedCashNow();
        $counted  = $data['counted_cash_cents'] ?? $expected;

        $session->update([
            'status'              => CashRegisterSession::STATUS_CLOSED,
            'expected_cash_cents' => $expected,
            'counted_cash_cents'  => $counted,
            'difference_cents'    => $counted - $expected,
            'closed_by'           => $userId,
            'closed_at'           => now(),
            'notes'               => $data['notes'] ?? $session->notes,
        ]);

        $this->audit->log(
            action: 'pos.session.closed',
            tenantId: $tenantId,
            userId: $userId,
            subject: $session,
        );

        return $session->fresh();
    }
}
