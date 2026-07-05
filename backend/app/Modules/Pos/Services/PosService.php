<?php

namespace App\Modules\Pos\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Services\OrderReturnService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Platform\Services\AuditService;
use App\Modules\Pos\Models\CashMovement;
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
 *
 * RC-16 "caisse approfondie" adds the drawer primitives shared by the Desktop and
 * mobile tills: split payments (cash + Mobile Money in one sale), cash-drawer
 * movements (pay-in / pay-out), and refunds at the till (delegated to the RMA
 * service, with the cash leg reflected in the drawer).
 */
class PosService
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
        private OrderReturnService $returns,
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
     * Ring up a sale: create → confirm → fulfill the order, record the payment(s),
     * and attach everything to the session. Atomic: any failure rolls the whole
     * sale back (no phantom stock movement or orphan payment).
     *
     * Payment shapes (backward compatible):
     *   - Legacy single payment: ['method' => 'cash', 'reference'? => ...]
     *     → one payment for the full total.
     *   - Split payment (RC-16): ['payments' => [
     *         ['method' => 'cash',         'amount_cents' => 30000, 'reference'? => ...],
     *         ['method' => 'mobile_money', 'amount_cents' => 20000, 'reference'? => ...],
     *     ]] → the amounts must sum EXACTLY to the order total.
     *
     * RC-22 — `$idempotencyKey` (en-tête X-Idempotency-Key) : la MÊME clé rejouée renvoie la vente
     * déjà enregistrée (retry réseau / resynchronisation offline) au lieu d'en créer une seconde.
     * Le rejeu est vérifié AVANT toute validation d'état : une vente déjà actée reste renvoyée
     * même si la session a été fermée entre-temps.
     *
     * @param  array  $data  ['items' => [...], 'customer_id'?, 'note'?, and one of method|payments]
     * @return array{order: Order, payments: Payment[], payment: ?Payment}
     *
     * @throws ValidationException                                       session not open / bad method / bad split
     * @throws \App\Modules\Inventory\Exceptions\InsufficientStockException out of stock
     */
    public function checkout(CashRegisterSession $session, array $data, string $tenantId, string $userId, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey !== null && ($replay = $this->findCheckoutByReference($tenantId, $idempotencyKey))) {
            return $replay;
        }

        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => ['La session de caisse est fermée.'],
            ]);
        }

        // Normalise to a list of payment legs. Legacy single-method calls become a
        // one-element list whose amount is resolved to the order total below.
        $split = ! empty($data['payments']);
        $legs  = $split
            ? array_values($data['payments'])
            : [[
                'method'       => $data['method'] ?? Payment::METHOD_CASH,
                'amount_cents' => null,                         // = full total (resolved after order creation)
                'reference'    => $data['reference'] ?? null,
            ]];

        foreach ($legs as $leg) {
            if (! in_array($leg['method'] ?? null, Payment::METHODS, true)) {
                throw ValidationException::withMessages([
                    'method' => ['Moyen de paiement invalide.'],
                ]);
            }
        }

        try {
            $result = $this->performCheckout($session, $data, $tenantId, $userId, $legs, $split, $idempotencyKey);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // RC-22 — course entre deux rejeux de la même clé : le premier a gagné, on renvoie
            // sa vente (la nôtre vient d'être annulée par le rollback de la transaction).
            if ($idempotencyKey !== null && ($replay = $this->findCheckoutByReference($tenantId, $idempotencyKey))) {
                return $replay;
            }
            throw $e;
        }

        // RC-26 — après commit : signale la vente au moteur d'imputation comptable (idempotent).
        // `legs` = part encaissée par moyen de paiement (source de l'écriture de trésorerie).
        $paymentLegs = array_map(
            fn (Payment $pmt) => ['method' => $pmt->method, 'amount' => (int) $pmt->amount_cents],
            $result['payments'],
        );
        event(new \App\Modules\Pos\Events\PosSaleCompleted($result['order'], $paymentLegs));

        return $result;
    }

    /** @return array{order: Order, payments: Payment[], payment: ?Payment} */
    private function performCheckout(CashRegisterSession $session, array $data, string $tenantId, string $userId, array $legs, bool $split, ?string $idempotencyKey): array
    {
        return DB::transaction(function () use ($session, $data, $tenantId, $userId, $legs, $split, $idempotencyKey) {
            // 1. Create the order (prices resolved server-side from the catalog).
            $order = $this->orders->create([
                'items'       => $data['items'],
                'customer_id' => $data['customer_id'] ?? null,
                'note'        => $data['note'] ?? null,
            ], $tenantId, $userId);

            // 2. Tie it to this session BEFORE state changes so a rollback is clean.
            $order->cash_register_session_id = $session->id;
            $order->warehouse_id = $order->warehouse_id ?? $session->warehouse_id;
            // RC-22 — la clé d'idempotence est posée AVANT confirm/fulfill : l'unicité
            // (tenant, pos_reference) neutralise tout doublon concurrent au commit.
            $order->pos_reference = $idempotencyKey;
            $order->save();

            // 3. Confirm (reserves stock — throws if insufficient) then fulfill
            //    (consumes stock). A POS sale leaves the shop immediately.
            $order = $this->orders->confirm($order, $userId);
            $order = $this->orders->fulfill($order, $userId);

            $total = (int) $order->total_amount;

            // 4. Resolve leg amounts and validate the split covers the total exactly.
            if ($split) {
                $sum = array_sum(array_map(fn ($l) => (int) $l['amount_cents'], $legs));
                if ($sum !== $total) {
                    throw ValidationException::withMessages([
                        'payments' => ["La somme des paiements ({$sum}) doit égaler le total de la vente ({$total})."],
                    ]);
                }
            } else {
                $legs[0]['amount_cents'] = $total;              // single leg pays the whole total
            }

            // 5. Record each payment leg and tally the cash portion for the drawer.
            $payments    = [];
            $cashPortion = 0;
            foreach ($legs as $leg) {
                $amount = (int) $leg['amount_cents'];
                if ($amount <= 0) {
                    continue;                                    // skip zero legs defensively
                }
                $payments[] = $this->payments->record([
                    'order_id'     => $order->id,
                    'amount_cents' => $amount,
                    'currency'     => $order->currency,
                    'method'       => $leg['method'],
                    'reference'    => $leg['reference'] ?? null,
                    'note'         => $data['note'] ?? null,
                ], $tenantId, $userId);

                if ($leg['method'] === Payment::METHOD_CASH) {
                    $cashPortion += $amount;
                }
            }

            // 6. Update the session's running tallies.
            $session->total_sales_cents += $total;
            $session->cash_sales_cents  += $cashPortion;
            $session->sales_count       += 1;
            $session->save();

            $this->audit->log(
                action: 'pos.sale',
                tenantId: $tenantId,
                userId: $userId,
                subject: $order,
            );

            return [
                'order'    => $order->fresh('lines'),
                'payments' => $payments,
                'payment'  => $payments[0] ?? null,             // BC: first leg exposed as `payment`
            ];
        });
    }

    /**
     * RC-22 — vente déjà enregistrée pour cette clé d'idempotence (rejeu offline/retry) :
     * reconstitue la réponse checkout d'origine (commande + paiements + premier leg).
     *
     * @return array{order: Order, payments: Payment[], payment: ?Payment}|null
     */
    private function findCheckoutByReference(string $tenantId, string $idempotencyKey): ?array
    {
        $order = Order::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('pos_reference', $idempotencyKey)
            ->first();

        if (! $order) {
            return null;
        }

        $payments = Payment::where('order_id', $order->id)->orderBy('paid_at')->get()->all();

        return [
            'order'    => $order->load('lines'),
            'payments' => $payments,
            'payment'  => $payments[0] ?? null,
        ];
    }

    /**
     * Record a cash-drawer movement (mouvement de caisse): a pay-in (float top-up,
     * change added) or a pay-out (withdrawal, petty expense). Reflected immediately
     * in the session's expected cash. A pay-out cannot exceed the cash on hand.
     *
     * @param  array  $data  ['direction' => 'in'|'out', 'amount_cents' => int>0, 'reason', 'note'?, 'order_id'?]
     *
     * @throws ValidationException session closed / non-positive amount / pay-out over cash on hand
     */
    public function recordCashMovement(CashRegisterSession $session, array $data, string $tenantId, string $userId): CashMovement
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => ['La session de caisse est fermée.'],
            ]);
        }

        $direction = $data['direction'];
        $amount    = (int) $data['amount_cents'];

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Le montant doit être strictement positif.'],
            ]);
        }

        if ($direction === CashMovement::DIRECTION_OUT && $amount > $session->expectedCashNow()) {
            throw ValidationException::withMessages([
                'amount_cents' => ['Retrait supérieur au fond de caisse disponible.'],
            ]);
        }

        $movement = CashMovement::create([
            'tenant_id'    => $tenantId,
            'session_id'   => $session->id,
            'direction'    => $direction,
            'amount_cents' => $amount,
            'reason'       => $data['reason'] ?? ($direction === CashMovement::DIRECTION_IN ? CashMovement::REASON_FLOAT_ADD : CashMovement::REASON_WITHDRAWAL),
            'note'         => $data['note'] ?? null,
            'order_id'     => $data['order_id'] ?? null,
            'performed_by' => $userId,
        ]);

        $this->audit->log(
            action: 'pos.cash_movement',
            tenantId: $tenantId,
            userId: $userId,
            subject: $movement,
        );

        // RC-26 — écriture comptable (float_add / withdrawal / expense ; refund est porté par pos.refund).
        event(new \App\Modules\Pos\Events\PosCashMovementRecorded($movement));

        return $movement;
    }

    /**
     * Refund a sale at the till: creates an RMA return, approves it for the
     * requested quantities, restocks resalable items (undoing serialized/warranty/
     * digital artifacts via OrderReturnService), and — for a cash refund — records
     * the cash leg as a drawer pay-out so the expected cash drops accordingly.
     *
     * @param  array  $lines  [['order_line_id','quantity','condition'?]]
     * @return array{return: OrderReturn, movement: ?CashMovement}
     *
     * @throws ValidationException session closed / empty return
     */
    public function refundSale(
        CashRegisterSession $session,
        Order $order,
        array $lines,
        string $reason,
        string $tenantId,
        string $userId,
        string $refundMethod = Payment::METHOD_CASH,
    ): array {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'session' => ['La session de caisse est fermée.'],
            ]);
        }
        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => ['Sélectionnez au moins une ligne à rembourser.'],
            ]);
        }

        $result = DB::transaction(function () use ($session, $order, $lines, $reason, $tenantId, $userId, $refundMethod) {
            // 1. RMA lifecycle: create → approve (full requested qty) → restock.
            $return = $this->returns->create($order, $lines, $reason, $userId, null, OrderReturn::RESOLUTION_REFUND);
            $this->returns->approve($return, $userId);
            $this->returns->restock($return->fresh('lines'), $userId, $session->warehouse_id);

            $return = $return->fresh();
            $refund = (int) $return->refund_amount_cents;

            // 2. Cash leg: a cash refund physically leaves the drawer → pay-out.
            //    Non-cash refunds (Mobile Money reversal, etc.) do not touch the drawer.
            $movement = null;
            if ($refundMethod === Payment::METHOD_CASH && $refund > 0) {
                $movement = CashMovement::create([
                    'tenant_id'    => $tenantId,
                    'session_id'   => $session->id,
                    'direction'    => CashMovement::DIRECTION_OUT,
                    'amount_cents' => $refund,
                    'reason'       => CashMovement::REASON_REFUND,
                    'note'         => "Remboursement {$return->number}",
                    'order_id'     => $order->id,
                    'performed_by' => $userId,
                ]);
            }

            $this->audit->log(
                action: 'pos.refund',
                tenantId: $tenantId,
                userId: $userId,
                subject: $return,
            );

            return ['return' => $return, 'movement' => $movement];
        });

        // RC-26 — écriture comptable du remboursement (le leg caisse est inclus, pas de doublon
        // via cash.movement grâce au filtre reason=refund du subscriber).
        event(new \App\Modules\Pos\Events\PosSaleRefunded($result['return'], $refundMethod));

        return $result;
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

        $fresh = $session->fresh();

        // RC-26 — un écart de caisse (manquant/surplus) génère une écriture d'ajustement (658/758).
        event(new \App\Modules\Pos\Events\PosSessionClosed($fresh));

        return $fresh;
    }
}
