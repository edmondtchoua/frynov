<?php

namespace App\Modules\Pos\Services;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Tenants\Models\Tenant;

/**
 * RC-19 — ticket de caisse (receipt).
 *
 * Construit le payload STRUCTURÉ d'un ticket pour une vente POS : en-tête boutique (settings du
 * tenant), lignes, paiements (y compris les splits RC-16), totaux et contexte de caisse. Le rendu
 * (80 mm / A4) et l'impression sont faits côté client — le serveur reste la source de vérité des
 * montants. Tous les montants en centimes.
 */
class ReceiptService
{
    /** @return array le ticket structuré, prêt à rendre */
    public function forOrder(Order $order): array
    {
        $order->loadMissing('lines');

        $tenant   = Tenant::withoutGlobalScopes()->find($order->tenant_id);
        $settings = $tenant?->settings ?? [];

        $payments = Payment::where('order_id', $order->id)
            ->orderBy('paid_at')
            ->get(['id', 'method', 'amount_cents', 'reference', 'paid_at']);

        $cashier = $order->performed_by ? User::find($order->performed_by) : null;

        $session = $order->cash_register_session_id
            ? \App\Modules\Pos\Models\CashRegisterSession::withoutTenantScope()
                ->where('tenant_id', $order->tenant_id)
                ->find($order->cash_register_session_id)
            : null;

        return [
            'business' => [
                'name'     => $tenant?->name ?? '',
                'address'  => $settings['address'] ?? null,
                'phone'    => $settings['phone'] ?? null,
                'currency' => $settings['currency'] ?? $order->currency ?? 'XOF',
            ],
            'order' => [
                'id'     => $order->id,
                'number' => $order->number,
                'date'   => ($order->fulfilled_at ?? $order->created_at)?->toISOString(),
                'status' => $order->status,
            ],
            'session' => $session ? [
                'id'    => $session->id,
                'label' => $session->label,
            ] : null,
            'cashier' => $cashier?->name,
            'lines'   => $order->lines->map(fn ($l) => [
                'name'             => $l->name,
                'sku'              => $l->sku,
                'quantity'         => (int) $l->quantity,
                'unit_price_cents' => (int) $l->unit_price_cents,
                'total_cents'      => $l->lineTotalCents(),
            ])->values()->all(),
            'payments' => $payments->map(fn ($p) => [
                'method'       => $p->method,
                'amount_cents' => (int) $p->amount_cents,
                'reference'    => $p->reference,
            ])->values()->all(),
            'totals' => [
                'total_cents' => (int) $order->total_amount,
                'paid_cents'  => (int) $payments->sum('amount_cents'),
            ],
        ];
    }
}
