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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly StockService $stockService) {}

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
        return DB::transaction(function () use ($data, $tenantId, $userId) {
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
                [$sku, $name, $unitPrice] = $this->resolveProduct($item, $tenantId);

                $priceCents = $item['unit_price_cents'] ?? $unitPrice;

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

        return $order->fresh('lines');
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
                // Consume reserved stock
                $this->stockService->moveOut(
                    $stock,
                    $line->quantity,
                    StockMovement::REASON_SALE,
                    $order->number,
                    null,
                    $userId,
                );
                // Release reservation counter (moveOut decrements quantity, not reserved_quantity)
                $this->stockService->release($stock, $line->quantity);
            }

            $order->update([
                'status'       => Order::STATUS_FULFILLED,
                'performed_by' => $userId,
                'fulfilled_at' => now(),
            ]);
        });

        return $order->fresh('lines');
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

        return $order->fresh('lines');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function nextOrderNumber(string $tenantId): string
    {
        $count = Order::where('tenant_id', $tenantId)->withTrashed()->count();

        return 'ORD-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
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
