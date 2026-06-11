<?php

namespace App\Modules\Orders\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InsufficientUnitsException;
use App\Modules\Inventory\Exceptions\StockLockException;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\SerializedAllocationService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Exceptions\OrderNotFoundException;
use App\Modules\Orders\Exceptions\OrderStateException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Platform\Services\AuditService;
use App\Modules\Warranties\Services\WarrantyService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly AuditService $auditService,
        private readonly SerializedAllocationService $allocation,
        private readonly WarrantyService $warranties,
    ) {}

    // ── Queries ────────────────────────────────────────────────────────────

    /**
     * @param  array<int,string>|null  $warehouseIds  null = no site restriction (admin/manager);
     *                                 array = the order must belong to one of these warehouses
     *                                 ([] = deny all). Mirrors the scoping applied by paginate()
     *                                 so a warehouse-restricted operator can never reach another
     *                                 site's order via a known UUID (Sprint 20 — GET unitaires).
     */
    public function findById(string $id, string $tenantId, ?array $warehouseIds = null): Order
    {
        $order = Order::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->when($warehouseIds !== null, fn($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->with('lines')
            ->first();

        if (! $order) {
            throw new OrderNotFoundException($id);
        }

        return $order;
    }

    /**
     * @param  array<int,string>|null  $warehouseIds  null = all warehouses; array = restrict to these ([] = none).
     */
    public function paginate(string $tenantId, int $perPage = 20, ?string $status = null, ?array $warehouseIds = null): LengthAwarePaginator
    {
        return Order::where('tenant_id', $tenantId)
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($warehouseIds !== null, fn($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->with('lines')
            ->latest()
            ->paginate($perPage);
    }

    // ── Commands ───────────────────────────────────────────────────────────

    /**
     * Create a draft order with its lines.
     * Products/variants must belong to the same tenant.
     *
     * @param  array{items: array<array{product_id: string, variant_id?: string|null, quantity: int, unit_price_cents?: int}>, note?: string, customer_id?: string} $data
     */
    public function create(array $data, string $tenantId, string $userId): Order
    {
        // Use the tenant's configured currency (settings['currency']), not a hardcoded
        // 'XOF'. A tenant in Cameroon (XAF) or elsewhere otherwise got mislabeled orders.
        $tenant   = \App\Modules\Tenants\Models\Tenant::withoutGlobalScopes()->find($tenantId);
        $currency = $tenant?->settings['currency'] ?? 'XOF';

        $order = DB::transaction(function () use ($data, $tenantId, $userId, $currency) {
            $number = $this->nextOrderNumber($tenantId);

            $order = Order::create([
                'tenant_id'    => $tenantId,
                'customer_id'  => $data['customer_id'] ?? null,
                'number'       => $number,
                'status'       => Order::STATUS_DRAFT,
                'currency'     => $currency,
                'note'         => $data['note'] ?? null,
                'performed_by' => $userId,
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {
                [$sku, $name, $dbPrice] = $this->resolveProduct($item, $tenantId);

                // SECURITY: unit_price_cents from the client payload is IGNORED.
                // Price is ALWAYS resolved from the database (products/variants table).
                // This prevents price manipulation attacks (OWASP API6).
                $priceCents = $dbPrice;

                OrderLine::create([
                    'order_id'         => $order->id,
                    'tenant_id'        => $tenantId,
                    'product_id'       => $item['product_id'],
                    'variant_id'       => $item['variant_id'] ?? null,
                    'sku'              => $sku,
                    'name'             => $name,
                    'quantity'         => $item['quantity'],
                    'unit_price_cents' => $priceCents,
                ]);

                $total += $item['quantity'] * $priceCents;
            }

            $order->update(['total_amount' => $total]);

            return $order->load('lines');
        });

        $this->auditService->log(
            action: 'order.created',
            tenantId: $order->tenant_id,
            userId: $userId,
            subject: $order,
        );

        return $order;
    }

    /**
     * Confirm a draft order — reserves stock for each line.
     *
     * Pour une ligne de produit sérialisé (RC-5C), on réserve EN PLUS des unités précises (IMEI/VIN) :
     * le stock agrégé garde la cohérence des vues existantes, l'allocation unitaire garantit la
     * traçabilité et empêche la double-vente d'une même unité.
     *
     * @throws OrderStateException
     * @throws InsufficientStockException
     * @throws InsufficientUnitsException
     * @throws StockLockException
     */
    public function confirm(Order $order, string $userId): Order
    {
        if (! $order->canBeConfirmed()) {
            throw new OrderStateException($order->id, 'confirm', $order->status);
        }

        DB::transaction(function () use ($order, $userId) {
            $order->load('lines');

            foreach ($order->lines as $line) {
                // RC-5C — produit sérialisé : réserver D'ABORD des unités précises (autorité serveur du
                // stock sérialisé → message d'erreur clair sur l'unitaire avant la réservation agrégée).
                if ($this->isSerializedLine($line, $order->tenant_id)) {
                    $this->allocation->allocate(
                        $order->tenant_id,
                        $order->id,
                        $line->id,
                        $line->product_id,
                        $line->variant_id,
                        $line->quantity,
                        $order->warehouse_id,
                    ); // throws InsufficientUnitsException
                }

                $stock = $this->stockService->findOrCreate(
                    $order->tenant_id,
                    $line->product_id,
                    $line->variant_id,
                );
                // Miroir agrégé : garde les vues de stock cohérentes (throws InsufficientStockException / StockLockException).
                $this->stockService->reserve($stock, $line->quantity);
            }

            $order->update([
                'status'       => Order::STATUS_CONFIRMED,
                'performed_by' => $userId,
            ]);
        });

        $confirmed = $order->fresh('lines');

        $this->auditService->log(
            action: 'order.confirmed',
            tenantId: $confirmed->tenant_id,
            userId: $userId,
            subject: $confirmed,
        );

        return $confirmed;
    }

    /**
     * Fulfill a confirmed order — consumes reserved stock and marks delivered.
     *
     * Pour les produits sous garantie (RC-5D), génère les contrats de garantie après la vente
     * (date de fin calculée, rattachés au client/à l'unité).
     *
     * @throws OrderStateException
     * @throws InsufficientStockException
     * @throws StockLockException
     */
    public function fulfill(Order $order, string $userId): Order
    {
        if (! $order->canBeFulfilled()) {
            throw new OrderStateException($order->id, 'fulfill', $order->status);
        }

        DB::transaction(function () use ($order, $userId) {
            $order->load('lines');

            foreach ($order->lines as $line) {
                $stock = $this->stockService->findOrCreate(
                    $order->tenant_id,
                    $line->product_id,
                    $line->variant_id,
                );
                // Release the reservation FIRST so available() rises back to the
                // physical quantity. Otherwise, when this order fully reserves the
                // stock (available == 0), moveOut()'s availability check would throw
                // InsufficientStockException on the order's OWN reserved stock.
                $this->stockService->release($stock, $line->quantity);
                // Then consume the physical stock (decrements quantity).
                $this->stockService->moveOut(
                    $stock,
                    $line->quantity,
                    StockMovement::REASON_SALE,
                    $order->number,
                    null,
                    $userId,
                );

                // RC-5C — produit sérialisé : marquer vendues les unités réservées + rattacher le client.
                if ($this->isSerializedLine($line, $order->tenant_id)) {
                    $this->allocation->markSold($order->tenant_id, $line->id, $order->customer_id);
                }
            }

            $order->update([
                'status'       => Order::STATUS_FULFILLED,
                'performed_by' => $userId,
                'fulfilled_at' => now(),
            ]);

            // RC-5D — garanties : générer les contrats après la vente (date de vente = fulfilled_at,
            // rattachés au client et, pour le sérialisé, à chaque unité vendue).
            $this->warranties->issueForOrder($order->fresh('lines'), $userId);
        });

        $fulfilled = $order->fresh('lines');

        $this->auditService->log(
            action: 'order.fulfilled',
            tenantId: $fulfilled->tenant_id,
            userId: $userId,
            subject: $fulfilled,
        );

        return $fulfilled;
    }

    /**
     * Cancel an order — releases stock reservations if order was confirmed.
     *
     * @throws OrderStateException
     */
    public function cancel(Order $order, string $userId): Order
    {
        if (! $order->canBeCancelled()) {
            throw new OrderStateException($order->id, 'cancel', $order->status);
        }

        $oldStatus = $order->status;

        DB::transaction(function () use ($order, $userId) {
            if ($order->isConfirmed()) {
                $order->load('lines');

                foreach ($order->lines as $line) {
                    $stock = $this->stockService->findOrCreate(
                        $order->tenant_id,
                        $line->product_id,
                        $line->variant_id,
                    );
                    $this->stockService->release($stock, $line->quantity);

                    // RC-5C — produit sérialisé : relâcher les unités réservées (redeviennent disponibles).
                    if ($this->isSerializedLine($line, $order->tenant_id)) {
                        $this->allocation->release($order->tenant_id, $line->id);
                    }
                }
            }

            $order->update([
                'status'       => Order::STATUS_CANCELLED,
                'performed_by' => $userId,
                'cancelled_at' => now(),
            ]);
        });

        $cancelled = $order->fresh('lines');

        $this->auditService->log(
            action: 'order.cancelled',
            tenantId: $cancelled->tenant_id,
            userId: $userId,
            subject: $cancelled,
            oldValues: ['status' => $oldStatus],
        );

        return $cancelled;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** @var array<string,string> cache product_id → stock_tracking (par opération de commande) */
    private array $trackingCache = [];

    /**
     * Une ligne porte-t-elle un produit à suivi sérialisé (RC-5C) ? Résolu via `stock_tracking`
     * (autorité serveur), avec un petit cache pour éviter une requête par ligne répétée.
     */
    private function isSerializedLine(OrderLine $line, string $tenantId): bool
    {
        if (! array_key_exists($line->product_id, $this->trackingCache)) {
            $this->trackingCache[$line->product_id] = (string) (Product::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('id', $line->product_id)
                ->value('stock_tracking') ?? Product::STOCK_TRACKING_AGGREGATE);
        }

        return $this->trackingCache[$line->product_id] === Product::STOCK_TRACKING_SERIALIZED;
    }

    /**
     * Generate the next order number atomically using the sku_sequences table.
     * Replaces the old COUNT+1 approach which had a race condition window.
     * Must be called inside a DB::transaction (which create() already ensures).
     */
    private function nextOrderNumber(string $tenantId): string
    {
        DB::table('sku_sequences')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'prefix'    => 'ORD',
            'last_seq'  => 0,
        ]);

        $row = DB::table('sku_sequences')
            ->where('tenant_id', $tenantId)
            ->where('prefix', 'ORD')
            ->lockForUpdate()
            ->first();

        $nextSeq = $row->last_seq + 1;

        DB::table('sku_sequences')
            ->where('tenant_id', $tenantId)
            ->where('prefix', 'ORD')
            ->update(['last_seq' => $nextSeq]);

        return 'ORD-' . str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
    }

    /** @return array{string, string, int} [sku, name, unit_price_cents] */
    private function resolveProduct(array $item, string $tenantId): array
    {
        if (! empty($item['variant_id'])) {
            $variant = ProductVariant::where('id', $item['variant_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $product = $variant->product;

            return [
                $variant->sku,
                $product->name . ' – ' . $variant->label,
                $variant->price_amount ?? $product->price_amount,
            ];
        }

        $product = Product::where('id', $item['product_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return [
            $product->sku,
            $product->name,
            $product->price_amount,
        ];
    }
}
