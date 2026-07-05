<?php

namespace App\Modules\Orders\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Services\DigitalService;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\PeriodLockService;
use App\Modules\Inventory\Services\SerializedAllocationService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Models\OrderReturnLine;
use App\Modules\Warranties\Services\WarrantyService;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 10 — Order return / RMA service.
 * State machine: pending → approved → restocked (+ optional stock replenishment)
 * On restock: only 'resalable' items are re-added to stock via StockService::moveIn().
 *
 * RC-5H — au restock, les artefacts spéciaux de la ligne retournée sont défaits : unités sérialisées
 * remises en stock/marquées retournées, contrats de garantie annulés (void), accès digitaux révoqués.
 */
class OrderReturnService
{
    public function __construct(
        private readonly StockService $stocks,
        private readonly SerializedAllocationService $allocation,
        private readonly WarrantyService $warranties,
        private readonly DigitalService $digital,
    ) {}

    // ── 1. CREATE (pending) ───────────────────────────────────────────────

    public function create(
        Order   $order,
        array   $lines,      // [['order_line_id','quantity','reason','condition']]
        string  $reason,
        string  $requestedBy,
        ?string $customerNote = null,
        string  $resolution  = OrderReturn::RESOLUTION_REFUND,
    ): OrderReturn {
        // RC-21 (R-2) — seule une commande HONORÉE est retournable : sur un brouillon/confirmé le
        // stock n'a jamais été décrémenté → un restock créerait du stock fantôme (et un
        // remboursement sans vente réelle).
        if ($order->status !== Order::STATUS_FULFILLED) {
            throw new \DomainException('Seule une commande honorée (livrée) peut faire l\'objet d\'un retour.');
        }

        // RC-21 (R-1) — borne de sur-retour PAR LIGNE : quantité achetée − déjà demandée/retournée
        // sur les retours non refusés de la même ligne. Sans cette borne : retour de 50 sur une
        // ligne de 2 → stock fantôme au restock + remboursement supérieur au payé.
        foreach ($lines as $l) {
            $orderLine = $order->lines()->findOrFail($l['order_line_id']);

            $alreadyClaimed = (int) OrderReturnLine::query()
                ->where('order_line_id', $orderLine->id)
                ->whereHas('orderReturn', fn ($q) => $q->whereIn('status', [
                    OrderReturn::STATUS_PENDING,
                    OrderReturn::STATUS_APPROVED,
                    OrderReturn::STATUS_PROCESSING,
                    OrderReturn::STATUS_RESTOCKED,
                ]))
                // Retour tranché (approved/restocked) → quantité approuvée fait foi ; en attente →
                // la demande réserve la quantité (évite deux demandes concurrentes sur le même solde).
                ->sum(DB::raw('COALESCE(quantity_approved, quantity_requested)'));

            $returnable = max(0, (int) $orderLine->quantity - $alreadyClaimed);
            if ((int) $l['quantity'] > $returnable) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lines' => ["Quantité retournable dépassée pour « {$orderLine->name} » : {$returnable} restant(s) sur {$orderLine->quantity} acheté(s)."],
                ]);
            }
        }

        return DB::transaction(function () use ($order, $lines, $reason, $requestedBy, $customerNote, $resolution) {
            // RC-20 (C-9) — séquence verrouillée (plus de course count()+1 → numéros dupliqués).
            $number = app(\App\Shared\Services\SequenceService::class)->next(
                $order->tenant_id, 'RET', 6,
                fn () => OrderReturn::withoutTenantScope()->where('tenant_id', $order->tenant_id)->withTrashed()->count(),
            );

            $return = OrderReturn::create([
                'tenant_id'      => $order->tenant_id,
                'order_id'       => $order->id,
                'number'         => $number,
                'status'         => OrderReturn::STATUS_PENDING,
                'reason'         => $reason,
                'resolution'     => $resolution,
                'customer_note'  => $customerNote,
                'requested_by'   => $requestedBy,
                'refund_currency' => $order->currency ?? 'XOF',
            ]);

            foreach ($lines as $l) {
                $orderLine = $order->lines()->findOrFail($l['order_line_id']);
                $return->lines()->create([
                    'order_line_id'      => $orderLine->id,
                    'product_id'         => $orderLine->product_id,
                    'variant_id'         => $orderLine->variant_id,
                    'quantity_requested' => (int) $l['quantity'],
                    'condition'          => $l['condition'] ?? 'resalable',
                    'reason'             => $l['reason'] ?? $reason,
                    'unit_price_cents'   => $orderLine->unit_price_cents,
                ]);
            }

            return $return->load('lines');
        });
    }

    // ── 2. APPROVE ────────────────────────────────────────────────────────

    public function approve(
        OrderReturn $return,
        string $approvedBy,
        array  $approvedQtys = [],
        ?string $internalNote = null,
    ): void {
        $this->assertState($return, [OrderReturn::STATUS_PENDING], 'approve');

        DB::transaction(function () use ($return, $approvedBy, $approvedQtys, $internalNote) {
            $refundTotal = 0;

            foreach ($return->lines as $line) {
                $qty = (int) ($approvedQtys[$line->id] ?? $line->quantity_requested);
                $qty = min($qty, $line->quantity_requested);
                $line->update(['quantity_approved' => $qty]);
                $refundTotal += $qty * $line->unit_price_cents;
            }

            $return->update([
                'status'              => OrderReturn::STATUS_APPROVED,
                'approved_by'         => $approvedBy,
                'approved_at'         => now(),
                'refund_amount_cents' => $refundTotal,
                'internal_note'       => $internalNote,
            ]);
        });

        // RC-20 (C-8) — l'appel positionnel était dans le désordre (TypeError avalé par le catch) :
        // AUCUN journal `return.approved` n'était écrit. Arguments nommés = signature garantie.
        try {
            app(\App\Modules\Platform\Services\AuditService::class)->log(
                action: 'return.approved',
                tenantId: $return->tenant_id,
                userId: $approvedBy,
                subject: $return,
                oldValues: ['status' => OrderReturn::STATUS_PENDING],
                newValues: ['status' => OrderReturn::STATUS_APPROVED, 'approved_by' => $approvedBy, 'refund_amount_cents' => $return->fresh()->refund_amount_cents],
                ipAddress: request()?->ip(),
                userAgent: request()?->userAgent(),
            );
        } catch (\Throwable) {}
    }

    // ── 3. RESTOCK (approved → restocked) ────────────────────────────────

    public function restock(
        OrderReturn $return,
        string $processedBy,
        ?string $warehouseId = null,
    ): void {
        $this->assertState($return, [OrderReturn::STATUS_APPROVED, OrderReturn::STATUS_PROCESSING], 'restock');

        // Period lock guard (same protection as forward movements)
        PeriodLockService::assertOperationAllowed($return->tenant_id);

        DB::transaction(function () use ($return, $processedBy, $warehouseId) {
            foreach ($return->lines->where('quantity_approved', '>', 0) as $line) {
                $resalable = $line->condition === 'resalable';

                // RC-5H — défaire les artefacts spéciaux de la ligne retournée (quelle que soit la
                // condition : le client ne possède plus l'article).
                // 1) Unités sérialisées → in_stock (resalable) ou returned. Renvoie les unités traitées.
                $unitIds = $this->allocation->returnUnits($return->tenant_id, $line->order_line_id, $line->quantity_approved, $resalable);
                // 2) Garanties → void (cible les unités si sérialisé, sinon la ligne).
                $this->warranties->voidForReturn($return->tenant_id, $line->order_line_id, $unitIds);
                // 3) Accès digitaux → révocation AU PRORATA (RC-7D : un accès par exemplaire). On ne
                //    garde actifs que les exemplaires NON encore retournés (qty ligne − cumul retourné).
                //    Un retour partiel révoque autant d'accès que d'exemplaires rendus ; le client
                //    conserve l'accès des exemplaires payés qu'il garde. Idempotent, cumule les retours.
                $orderLine = \App\Modules\Orders\Models\OrderLine::withoutTenantScope()
                    ->where('tenant_id', $return->tenant_id)
                    ->find($line->order_line_id);
                if ($orderLine) {
                    // Ne compter que les retours EFFECTIVEMENT restockés + celui en cours (recette QA —
                    // AR-2). Compter les retours seulement APPROUVÉS sur-révoquerait, et un rejet
                    // ultérieur de ce retour ne réactiverait pas l'accès (perte irréversible).
                    $restockedElsewhere = (int) OrderReturnLine::query()
                        ->where('order_line_id', $line->order_line_id)
                        ->where('return_id', '!=', $return->id)
                        ->whereHas('orderReturn', fn ($q) => $q->where('status', OrderReturn::STATUS_RESTOCKED))
                        ->sum('quantity_approved');
                    $returnedTotal = $restockedElsewhere + (int) $line->quantity_approved;
                    $keepActive = max(0, (int) $orderLine->quantity - $returnedTotal);
                    $this->digital->revokeDownToActive($return->tenant_id, $line->order_line_id, $keepActive);
                }

                // Only resalable items are returned to stock (le miroir agrégé du sérialisé suit aussi).
                // RC-5H — un produit non stockable (service/digital) n'a pas de stock à réabonder.
                $product = Product::withoutTenantScope()
                    ->where('tenant_id', $return->tenant_id)
                    ->where('id', $line->product_id)
                    ->first(['id', 'product_type', 'stock_tracking']);
                if (! $resalable || ($product && ! $product->isStockable())) {
                    continue;
                }

                $stock = $this->resolveStock(
                    $return->tenant_id,
                    $line->product_id,
                    $line->variant_id,
                    $warehouseId,
                );

                $this->stocks->moveIn(
                    $stock,
                    $line->quantity_approved,
                    StockMovement::REASON_RETURN,
                    $return->number,
                    "Retour {$return->number} — {$return->reason}",
                    $processedBy,
                    $line->unit_price_cents,
                );

                $line->update(['quantity_restocked' => $line->quantity_approved]);
            }

            $return->update([
                'status'       => OrderReturn::STATUS_RESTOCKED,
                'processed_by' => $processedBy,
                'restocked_at' => now(),
            ]);
        });
    }

    // ── 4. REJECT ────────────────────────────────────────────────────────

    public function reject(OrderReturn $return, string $rejectedBy, string $reason): void
    {
        $this->assertState($return, [OrderReturn::STATUS_PENDING, OrderReturn::STATUS_APPROVED], 'reject');

        DB::transaction(function () use ($return, $rejectedBy, $reason) {
            $return->update([
                'status'           => OrderReturn::STATUS_REJECTED,
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
                'approved_by'      => $rejectedBy,
            ]);
        });
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function resolveStock(
        string $tenantId,
        string $productId,
        ?string $variantId,
        ?string $warehouseId,
    ): Stock {
        $query = Stock::withoutTenantScope()
            ->where('tenant_id',  $tenantId)
            ->where('product_id', $productId)
            ->when(
                $variantId,
                fn ($q) => $q->where('variant_id', $variantId),
                fn ($q) => $q->whereNull('variant_id'),
            );

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        } else {
            $query->whereHas('warehouse', fn ($q) => $q->where('is_default', true));
        }

        return $query->firstOrFail();
    }

    private function assertState(OrderReturn $r, array $allowed, string $action): void
    {
        if (! in_array($r->status, $allowed, true)) {
            throw new \DomainException(
                "Impossible d'exécuter '{$action}' sur un retour en état '{$r->status}'."
            );
        }
    }
}
