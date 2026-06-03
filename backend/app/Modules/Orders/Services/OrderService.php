<?php

namespace App\Modules\Orders\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\StockLockException;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Exceptions\OrderNotFoundException;
use App\Modules\Orders\Exceptions\OrderStateException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly AuditService $auditService,
    ) {}

    // ── Queries ────────────────────────────────────────────────────────────

    public function findById(string $id, string $tenantId): Order
    {
        $order = Order::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->with('lines')
            ->first();

        if (! $order) {
            throw new OrderNotFoundException($id);
        }

        return $order;
    }

    public function paginate(string $tenantId, int $perPage = 20, ?string $status = null): LengthAwarePaginator
    {
        return Order::where('tenant_id', $tenantId)
            ->when($status, fn($q) => $q->where('status', $status))
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
        $order = DB::transaction(function () use ($data, $tenantId, $userId) {
            $number = $this->nextOrderNumber($tenantId);

            $order = Order::create([
                'tenant_id'    => $tenantId,
                'customer_id'  => $data['customer_id'] ?? null,
                'number'       => $number,
                'status'       => Order::STATUS_DRAFT,
                'currency'     => 'XOF',
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
     * @throws OrderStateException
     * @throws InsufficientStockException
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
                $stock = $this->stockService->findOrCreate(
                    $order->tenant_id,
                    $line->product_id,
                    $line->variant_id,
                );
                // throws InsufficientStockException or StockLockException
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
            }

            $order->update([
                'status'       => Order::STATUS_FULFILLED,
                'performed_by' => $userId,
                'fulfilled_at' => now(),
            ]);
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
