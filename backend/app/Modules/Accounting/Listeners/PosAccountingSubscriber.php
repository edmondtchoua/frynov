<?php

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Services\ImputationEngine;
use App\Modules\Pos\Events\PosCashMovementRecorded;
use App\Modules\Pos\Events\PosSaleCompleted;
use App\Modules\Pos\Events\PosSaleRefunded;
use App\Modules\Pos\Events\PosSessionClosed;
use App\Modules\Pos\Models\CashMovement;
use Illuminate\Events\Dispatcher;

/**
 * RC-26 — le module Comptabilité ÉCOUTE les événements POS et les enregistre dans son outbox
 * (idempotent). Dépendance à sens unique (Accounting → Pos) : le POS ignore la comptabilité.
 * Un tenant sans module comptable → `record()` ne fait rien (garde interne de l'engine).
 */
class PosAccountingSubscriber
{
    public function __construct(private readonly ImputationEngine $engine) {}

    public function onSale(PosSaleCompleted $e): void
    {
        $order = $e->order;
        $this->engine->record($order->tenant_id, 'pos.sale', 'Order', $order->id, [
            'order_id'     => $order->id,
            'order_number' => $order->number,
            'total'        => (int) $order->total_amount,
            'legs'         => $e->legs,
            'currency'     => $order->currency,
            'date'         => ($order->fulfilled_at ?? $order->created_at)?->toDateString(),
        ]);
    }

    public function onRefund(PosSaleRefunded $e): void
    {
        $return = $e->return;
        $this->engine->record($return->tenant_id, 'pos.refund', 'OrderReturn', $return->id, [
            'return_id'     => $return->id,
            'return_number' => $return->number,
            'amount'        => (int) $return->refund_amount_cents,
            'method'        => $e->refundMethod,
            'date'          => now()->toDateString(),
        ]);
    }

    public function onCashMovement(PosCashMovementRecorded $e): void
    {
        $m = $e->movement;
        // Le leg caisse d'un remboursement est déjà porté par pos.refund → ne pas doubler.
        if ($m->reason === CashMovement::REASON_REFUND) {
            return;
        }
        $this->engine->record($m->tenant_id, 'cash.movement', 'CashMovement', $m->id, [
            'movement_id' => $m->id,
            'reason'      => $m->reason,
            'direction'   => $m->direction,
            'amount'      => (int) $m->amount_cents,
            'date'        => $m->created_at?->toDateString(),
        ]);
    }

    public function onSessionClosed(PosSessionClosed $e): void
    {
        $s = $e->session;
        if ((int) $s->difference_cents === 0) {
            return; // caisse juste : aucune écriture d'ajustement
        }
        $this->engine->record($s->tenant_id, 'pos.session_gap', 'CashRegisterSession', $s->id, [
            'session_id'       => $s->id,
            'session_label'    => $s->label,
            'difference_cents' => (int) $s->difference_cents,
            'date'             => ($s->closed_at ?? now())?->toDateString(),
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            PosSaleCompleted::class        => 'onSale',
            PosSaleRefunded::class         => 'onRefund',
            PosCashMovementRecorded::class => 'onCashMovement',
            PosSessionClosed::class        => 'onSessionClosed',
        ];
    }
}
